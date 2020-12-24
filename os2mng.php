<?php

class os2Mng
{
    private $DB;
    private $DB2;

	//생성자
	function __construct(){
        global $CFG;
        alog("os2Mng-__construct");
        
        $this->DB["OS"] = getDbConn($CFG["CFG_DB"]["OS"]);

    }
    //파괴자
	function __destruct(){
        alog("os2Mng-__destruct");
        
		if($this->DB["OS"])closeDb($this->DB["OS"]);
		unset($this->DB);

    }
    
    //라우팅
    function newToken($req){
        global $CFG,$log;

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
        $stmt = getStmt($this->DB["OS"],$sqlMap);
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
    

        //10 ID/비번이 맞는지 검사

        $log->info("username = " . $req["username"] );
        $log->info("password = " . $req["password"] );

        $req["CFG_SEC_SALT"] = $CFG["CFG_SEC_SALT"];

        $sql = "select USR_SEQ from CMN_USR where USR_ID= #{username} and USR_PWD=sha2(concat(#{CFG_SEC_SALT},#{password}),512)";
        $sqlMap = getSqlParam($sql,$coltype="sss",$req);
        $stmt = getStmt($this->DB["OS"],$sqlMap);
        $result2 = getStmtArray($stmt);

        closeStmt($stmt);

        $map["user_seq"] = $result2[0]["USR_SEQ"];

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
            $stmt = getStmt($this->DB["OS"],$sqlMap);
    
            if(!$stmt->execute()){
                $rtnArr["RTN_CD"] = 500;
                $rtnArr["ERR_CD"] = 146;
                $rtnArr["RTN_MSG"] = "make stmt prepare error : (" . $stmt->errno . ") " . $stmt->error ;

                closeStmt($stmt);
                return $rtnArr;
            }
            closeStmt($stmt);

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
            $stmt = getStmt($this->DB["OS"],$sqlMap);
            if(!$stmt->execute()){
                $rtnArr["RTN_CD"] = 500;
                $rtnArr["ERR_CD"] = 160;
                $rtnArr["RTN_MSG"] = "make stmt prepare error : (" . $stmt->errno . ") " . $stmt->error ;

                closeStmt($stmt);
                return $rtnArr;
            }
            closeStmt($stmt);
    
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
        $stmt = getStmt($this->DB["OS"],$sqlMap);
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
        $stmt = getStmt($this->DB["OS"],$sqlMap);
        $result = getStmtArray($stmt);

        $rtnArr["RTN_DATA"]["USER_INFO"] = $result[0];

        //echo json_encode($map, JSON_PRETTY_PRINT);

        //20 db에서 권한조회해서 리턴하기
        $sql = "
        select b.PGMID, b.AUTH_ID
        from CMN_GRP_USR a
            join CMN_GRP_AUTH b on a.GRP_SEQ = b.GRP_SEQ
            join CMN_MNU c on b.PGMID = c.PGMID
        where a.USR_SEQ = #{user_seq}
        and c.PGMTYPE IN (
                select PGMTYPE from CMN_IP where ALLOW_IP = #{remote_addr} or ALLOW_IP = '0.0.0.0'
            )
        order by b.PGMID, b.AUTH_ID
        ";
        $sqlMap = getSqlParam($sql,$coltype="is",$req);
        $stmt = getStmt($this->DB["OS"],$sqlMap);
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