<?php

class os2Mng
{
    private $DB;
    private $DB2;

	//생성자
	function __construct(){
        global $CFG;
        alog("os2Mng-__construct");
        
        $this->DB = getDbConn($CFG["CFG_DB"]["RDCOMMON"]);

    }
    //파괴자
	function __destruct(){
        alog("os2Mng-__destruct");
        
		if($this->DB)closeDb($this->DB);
		unset($this->DB);

    }
    
    //라우팅
    function newToken($req){
        global $CFG,$log,$_RTIME;

        //var_dump($CFG);
        //$this->DB->setDefer();

        /*
        //성공시
        {   
            "access_token":"a2e9ea946505e9693aafa0182f98e7ea473e82e7"
            ,"expires_in":3600
            ,"token_type":"Bearer"
            ,"scope":"mymenu1 menu2"
            ,"refresh_token":"3c94f33d797194ac82bf25a5af090a56e7a6a5b0"
        }
        */

        $rtnArr = array(
            "RTN_CD" => 200
            ,"ERR_CD" => 200
            ,"RTN_MSG" => ""
            ,"RTN_DATA" => array(
                "access_token" => ""
                ,"expires_in" => 3600
                ,"token_type" => "Bearer"
                ,"scope" => "hidden"
                ,"refresh_token" => ""
            )
        );

        //05 client가 유효한지 확인하기
        $sql = "select * from oauth_clients where client_id = #{client_id} and client_secret = #{client_secret}";

        $sqlMap = getSqlParam($sql,$coltype="ss",$req);
        $stmt = getStmt($this->DB,$sqlMap);
        $result = getStmtArray($stmt);
    
        $log->info("client_id = " . $req["client_id"] );
        $log->info("client_secret = " . $req["client_secret"] );
        //echo "sha1(password) = " . sha1($req->post["password"]) . "\n";

        if(trim($result[0]["client_id"]) == ""){
            $rtnArr["RTN_CD"] = 500;
            $rtnArr["ERR_CD"] = 511;
            $rtnArr["RTN_MSG"] = "유효한 client가 아닙니다.(Invalid client)" ;      

            closeStmt($stmt);
            return $rtnArr; 
        }
        closeStmt($stmt);
        $redirect_uri = $result[0]["redirect_uri"];

        array_push($_RTIME,array("[TIME 41.SVC CHECK_DB CLIENT]",microtime(true)));

        alog("username = " . $req["username"] );
        alog("password = " . $req["password"] );
        alog("CFG_LDAP_HOST = " . $CFG["CFG_LDAP_HOST"] );
        alog("CFG_LDAP_DOMAIN = " . $CFG["CFG_LDAP_DOMAIN"] );

        //10 CFG에 LDAP이 활성화 된 경우, 사용자가 LDAP로그인 사용자 인지 확인하기
        if(  strlen($CFG["CFG_LDAP_HOST"]) > 0  ){
            //20 사용자 정보에 LDAP_LOGIN_YN = N이면 로컬DB에서 로그인 처리

            $sql = "select USR_SEQ, USR_ID, LDAP_LOGIN_YN from CMN_USR where USR_ID= #{username}";
            $sqlMap = getSqlParam($sql,$coltype="s",$req);
            $stmt = getStmt($this->DB,$sqlMap);
            $resultUser = getStmtArray($stmt)[0];
            closeStmt($stmt);

            if( $resultUser["LDAP_LOGIN_YN"] == "N" ){
                //30 ID/비번이 맞는지 DB에서 검사
                $req["CFG_SEC_SALT"] = $CFG["CFG_SEC_SALT"];

                $sql = "select USR_SEQ from CMN_USR where USR_ID= #{username} and USR_PWD=sha2(concat(#{CFG_SEC_SALT},#{password}),512)";
                $sqlMap = getSqlParam($sql,$coltype="sss",$req);
                $stmt = getStmt($this->DB,$sqlMap);
                $result2 = getStmtArray($stmt);

                closeStmt($stmt);

                $map["user_seq"] = $result2[0]["USR_SEQ"];
                
            }else{
                //40 LDAP 로그인 처리
                $ldap = new ldapClass();
                $conObj = $ldap->connect($CFG["CFG_LDAP_HOST"]);
                //echo "<BR>ldap_error : " . ldap_error($conObj);
                
                if(!$conObj)JsonMsg("500","202","Ldap connect error " .  ldap_error($conObj));

                if( $ldap->login($CFG["CFG_LDAP_DOMAIN"], $req["username"],$req["password"]) ){
                    //echo "<BR>로그인 성공";

                    //ldap서버에서 사용자 정보 조회하기
                    $userLdapMap = $ldap->getUserInfo($CFG["CFG_LDAP_HOST"]);

                    //팀 정보는 신규/재로그인시 모두 반영
                    $req["USR_ID"] = $req["username"];
                    $req["USR_NM"] = $userLdapMap["givenname"];
                    $req["TEAMNM"] = $userLdapMap["department"];
                    $req["TEAMCD"] = $userLdapMap["departmentnumber"];
                    //$req["EMAIL"] = $userLdapMap["mail"];
                    //$req["PHONE"] = $userLdapMap["mobile"];

                    //이미 등록된 사용자 인지 확인하기
                    if( $resultUser["USR_ID"] . "" != "" ){
                        $sql = "update CMN_USR set
                                USR_NM = #{USR_NM}, PHONE = #{PHONE}, TEAMCD = #{TEAMCD}, TEAMNM = #{TEAMNM}, EMAIL = #{EMAIL}
                                , MOD_DT = date_format(sysdate(),'%Y%m%d%H%i%s'), MOD_ID = 0
                            where USR_ID = #{USR_ID}
                        ";
                        $coltype = "sssss s";
                    }else{
                        //로그인 성공시 DB에 사용자 정보 등록(기본 사용자 정보를 바탕으로 로그인 이력도 남겨야하기 때문에)

                        $sql = "insert into CMN_USR (
                            USR_ID, USR_NM, PHONE, USE_YN, USR_PWD
                            , PW_ERR_CNT, LAST_STATUS, LOCK_LIMIT_DT, LOCK_LAST_DT, EXPIRE_DT
                            , PW_CHG_DT, PW_CHG_ID, LDAP_LOGIN_YN, TEAMCD, TEAMNM
                            , EMAIL
                            , ADD_DT, ADD_ID
                            ) values (
                                #{USR_ID}, #{USR_NM}, #{PHONE}, 'Y', null
                                ,0, null, null, null, null
                                , null, null, 'Y', #{TEAMCD}, #{TEAMNM}
                                , #{EMAIL}
                                , date_format(sysdate(),'%Y%m%d%H%i%s'), 0
                            )
                            ";
                        $coltype = "sssss s";

                    }

                    $sqlMap = getSqlParam($sql,$coltype,$req);
                    $stmt = getStmt($this->DB,$sqlMap);
                    if(!$stmt->execute())JsonMsg("500","102","(Save usr error) stmt 실행 실패 " .  $stmt->error);

                    //usr_SEQ 알아오기
                    if( $resultUser["USR_ID"] . "" == "" ){
                        if($stmt instanceof PDOStatement){
                            alog("SEQYN PDO : " . $this->DB->lastInsertId());
                            $map["user_seq"] = $this->DB->lastInsertId(); //insert문인 경우 insert id받기                            
                        }else{
                            alog("SEQYN Mysqli : " . $this->DB->insert_id);
                            $map["user_seq"]= $this->DB->insert_id; //insert문인 경우 insert id받기
                        }
                    }else{
                        $map["user_seq"] = $resultUser["USR_SEQ"];    
                    }

                    closeStmt($stmt);

                }else{
                    JsonMsg("500","103","(Ldap id/pw login error) stmt 실행 실패 " .  $stmt->error);
                }
                $ldap->close();
            }

        }else{
            //50 (LDAP설정이 없을때 로컬인증모드) ID/비번이 맞는지 DB에서 검사
            $req["CFG_SEC_SALT"] = $CFG["CFG_SEC_SALT"];

            $sql = "select USR_SEQ from CMN_USR where USR_ID= #{username} and USR_PWD=sha2(concat(#{CFG_SEC_SALT},#{password}),512)";
            $sqlMap = getSqlParam($sql,$coltype="sss",$req);
            $stmt = getStmt($this->DB,$sqlMap);
            $result2 = getStmtArray($stmt);

            closeStmt($stmt);

            $map["user_seq"] = $result2[0]["USR_SEQ"];

        }


        array_push($_RTIME,array("[TIME 42.SVC CHECK_DB USER]",microtime(true)));

        //20 토큰 DB넣고 리턴
        if(trim($map["user_seq"]) != ""){

            //31 access token 넣기 
            $log->info("db accessTOken go");
            $accessToken = $this->generateAccessToken(); //토큰 만들기

            $rtnArr["RTN_DATA"]["access_token"] = $accessToken;

            $sql = "
                insert into oauth_access_tokens (
                    access_token, client_id, user_id, expires, scope
                    ,user_seq
                )values(
                    #{access_token}, #{client_id}, #{user_id}, DATE_ADD(NOW(), INTERVAL 60 MINUTE),''
                    ,#{user_seq}
                )
                ";

            $req["access_token"] = $accessToken;
            $req["user_seq"] = $map["user_seq"];
            $req["user_id"] = $req["username"];

            $sqlMap = getSqlParam($sql,$coltype="sssi",$req);
            $stmt = getStmt($this->DB,$sqlMap);
    
            if(!$stmt->execute()){
                $rtnArr["RTN_CD"] = 500;
                $rtnArr["ERR_CD"] = 146;
                $rtnArr["RTN_MSG"] = "make stmt prepare error : (" . $stmt->errno . ") " . $stmt->error ;

                closeStmt($stmt);
                return $rtnArr;
            }
            closeStmt($stmt);

            array_push($_RTIME,array("[TIME 43.SVC INSERT_DB NEW TOKEN]",microtime(true)));

            //32 refresh token 넣기
            //echo "db refreshToken go\n";
            $refreshToken = $this->generateAccessToken(); //토큰 만들기
            $rtnArr["RTN_DATA"]["refresh_token"] = $refreshToken;

            $sql = "
                insert into oauth_refresh_tokens (
                    refresh_token, client_id, user_id, expires, scope
                    ,user_seq
                )values(
                    #{refresh_token}, #{client_id}, #{user_id}, DATE_ADD(NOW(), INTERVAL 1 DAY),''
                    ,#{user_seq}
                )
                ";
            $req["refresh_token"] = $refreshToken;

            $sqlMap = getSqlParam($sql,$coltype="sssi",$req);
            $stmt = getStmt($this->DB,$sqlMap);
            if(!$stmt->execute()){
                $rtnArr["RTN_CD"] = 500;
                $rtnArr["ERR_CD"] = 160;
                $rtnArr["RTN_MSG"] = "make stmt prepare error : (" . $stmt->errno . ") " . $stmt->error ;

                closeStmt($stmt);
                return $rtnArr;
            }
            closeStmt($stmt);

            array_push($_RTIME,array("[TIME 44.SVC INSERT_DB REFRESH TOKEN]",microtime(true)));



            //모두 성공한 경우 client_id 담아서 리턴
            $rtnArr["RTN_DATA"]["redirect_uri"] = $redirect_uri;

        }else{
            $rtnArr["RTN_CD"] = 500;
            $rtnArr["ERR_CD"] = 520;
            $rtnArr["RTN_MSG"] = "ID 혹은 비밀번호가 일치하지 않습니다. (Id or pwd not equal.)" ;
            return $rtnArr;
        }


        return $rtnArr;

    }


    function uuidgen4() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
           mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
           mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
           mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
         );
     }


    //리프레시토큰
    function refreshToken($res){
        //10 리프토큰이 요효한지 검사
        //20 토큰, 리프토큰 다시 만들고
        //30 구 리프토큰은 db에서 지우기

    }

    //리소스요청
    function getResource($req){ 


        $rtnArr = array(
            "RTN_CD" => 200
            ,"ERR_CD" => 200
            ,"RTN_MSG" => ""
            ,"RTN_DATA" => array(
                "USER_INFO" => array()
                ,"AUTH_INFO" => array()
            )
        );

        //var_dump($req);

        //10 요청토큰이 유효한지 확인
        if(strlen($req["access_token"]) < 10){
            $rtnArr["RTN_CD"] = 500;
            $rtnArr["ERR_CD"] = 510;
            $rtnArr["RTN_MSG"] = "요청 토큰 길이기 잘못되었습니다.(Check access_token's length " . strlen($req["access_token"]) . ")";
            return $rtnArr;
        }

        $sql = "select TIMESTAMPDIFF(SECOND,now(),expires) as OVERTM, user_seq
        from oauth_access_tokens 
        where access_token = #{access_token}";

        $sqlMap = getSqlParam($sql,$coltype="s",$req);
        $stmt = getStmt($this->DB,$sqlMap);
        $result = getStmtArray($stmt);

        closeStmt($stmt);
            
        //echo "OVERTM = " . $result[0]["OVERTM"] . "\n";

        if( !is_numeric($result[0]["user_seq"]) ){
            //토큰이 없으면 오류
            $rtnArr["RTN_CD"] = 500;
            $rtnArr["ERR_CD"] = 252;
            $rtnArr["RTN_MSG"] = "요청한 토큰이 없습니다. (Not exist request token)";
            return $rtnArr;
        }else if(intval($result[0]["OVERTM"]) < 0){
            //값이 0보자 작으면 오류 리턴
            $rtnArr["RTN_CD"] = 500;
            $rtnArr["ERR_CD"] = 520;
            $rtnArr["RTN_MSG"] = "요청토근이 만료되었습니다.(token expires:" . $result[0]["OVERTM"] . ")";
            return $rtnArr;
        }
            


        //리턴할 사용자 최소정보

        //echo "사용자 정보 result[0]\: ";
        //var_dump($result[0]);

        $sql = "select * 
        from CMN_USR 
        where USR_SEQ = #{user_seq}";

        $req["user_seq"] = $result[0]["user_seq"];

        $sqlMap = getSqlParam($sql,$coltype="i",$req);
        $stmt = getStmt($this->DB,$sqlMap);
        $result = getStmtArray($stmt);

        $rtnArr["RTN_DATA"]["USER_INFO"] = $result[0];

        //echo json_encode($map, JSON_PRETTY_PRINT);

        //20 db에서 권한조회해서 리턴하기
        $sql = "
        select
        *
        from
        (
            /* default auth */
            select PGMID, AUTH_ID
            from CMN_DEFAULT_AUTH
            union
            /* group auth */
            select b.PGMID as PGMID, b.AUTH_ID as AUTH_ID
            from CMN_GRP_USR a
                join CMN_GRP_AUTH b on a.GRP_SEQ = b.GRP_SEQ
                join CMN_MNU c on b.PGMID = c.PGMID
            where a.USR_SEQ = #{user_seq}
            and c.PGMTYPE IN (
                    select PGMTYPE from CMN_IP where ALLOW_IP = #{remote_addr} or ALLOW_IP = '0.0.0.0'
                )
            union
            /* team auth */
            select a2.PGMID as PGMID, a2.AUTH_ID as AUTH_ID
            from CMN_TEAM_AUTH a2
                join CMN_MNU b2 on a2.PGMID = b2.PGMID
            where a2.TEAM_SEQ = 
                (
                        select TEAM_SEQ from CMN_TEAM c2 
                            join CMN_USR d2 on c2.TEAMCD = d2.TEAMCD and d2.USR_SEQ = #{user_seq} 
                        where d2.TEAMCD is not null and d2.TEAMCD <> ''
                )
            and b2.PGMTYPE IN (
                    select PGMTYPE from CMN_IP where ALLOW_IP = #{remote_addr} or ALLOW_IP = '0.0.0.0'
                )
        ) uniondata
        order by PGMID, AUTH_ID
        ";
        $sqlMap = getSqlParam($sql,$coltype="isis",$req);
        $stmt = getStmt($this->DB,$sqlMap);
        $result2 = getStmtArray($stmt);

        
        $lastPgmid = "";
        $rtnVal = null;
        for($i=0;$i<count($result2);$i++){
            $tMap = $result2[$i];
            if($lastPgmid != $tMap["PGMID"]){
                $rtnVal[$tMap["PGMID"]] = array();
                $j=0;          
            }else{
                $j++;        
            }
            $rtnVal[$tMap["PGMID"]][$j] = $tMap["AUTH_ID"];
            $lastPgmid = $tMap["PGMID"];
        }
        
        //리턴할 권한 정보
        $rtnArr["RTN_DATA"]["AUTH_INFO"] = $rtnVal;

        return $rtnArr;
    }

    //토큰이 유효한지 확인하기
    function getVerifyToken(){
        
    }


        /**
     * Generates an unique access token.
     *
     * Implementing classes may want to override this function to implement
     * other access token generation schemes.
     *
     * @return
     * An unique access token.
     *
     * @ingroup oauth2_section_4
     */
    protected function generateAccessToken()
    {
        if (function_exists('mcrypt_create_iv')) {
            $randomData = mcrypt_create_iv(20, MCRYPT_DEV_URANDOM);
            if ($randomData !== false && strlen($randomData) === 20) {
                return bin2hex($randomData);
            }
        }
        if (function_exists('openssl_random_pseudo_bytes')) {
            $randomData = openssl_random_pseudo_bytes(20);
            if ($randomData !== false && strlen($randomData) === 20) {
                return bin2hex($randomData);
            }
        }
        if (@file_exists('/dev/urandom')) { // Get 100 bytes of random data
            $randomData = file_get_contents('/dev/urandom', false, null, 0, 20);
            if ($randomData !== false && strlen($randomData) === 20) {
                return bin2hex($randomData);
            }
        }
        // Last resort which you probably should just get rid of:
        $randomData = mt_rand() . mt_rand() . mt_rand() . mt_rand() . microtime(true) . uniqid(mt_rand(), true);

        return substr(hash('sha512', $randomData), 0, 40);
    }

	function __toString(){
		alog("oauthMng-__toString");
    }
}

?>