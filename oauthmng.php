<?php

class oauthMng
{
    private $DB;
    private $DB2;

	//생성자
	function __construct(){
        global $CFG;
        alog("authLog-__construct");
        

        $this->DB = new Swoole\Coroutine\MySQL();

        $cfgDb = $CFG["CFG_DB"]["OS"];

        $this->DB->connect([
            'host' => $cfgDb["HOST"],
            'user' => $cfgDb["ID"],
            'password' => $cfgDb["PW"],
            'database' => $cfgDb["DBNM"],
            'port' => $cfgDb["PORT"]
        ]);


    }
    //파괴자
	function __destruct(){
        alog("authLog-__destruct");
        
        if($this->DB)$this->DB->close();
        unset($this->DB);

    }
    
    //라우팅
    function newToken($req){
        global $CFG;

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
        $stmt = $this->DB->prepare('select * from oauth_clients where client_id = ? and client_secret = ?');
        if ($stmt == false){
            var_dump($this->DB->errno, $this->DB->error);
            $rtnArr["RTN_CD"] = 500;
            $rtnArr["ERR_CD"] = 510;
            $rtnArr["RTN_MSG"] = "make stmt prepare error : (" . $this->DB->errno . ") " . $this->DB->error ;
            return $rtnArr;
        }
        else
        {
            echo "client_id = " . $req->post["client_id"] . "\n";
            echo "client_secret = " . $req->post["client_secret"] . "\n";
            //echo "sha1(password) = " . sha1($req->post["password"]) . "\n";

            $result = $stmt->execute(array($req->post["client_id"],$req->post["client_secret"]));
            $stmt->close();
            //var_dump($result);

            //
            if(trim($result[0]["client_id"]) == ""){
                $rtnArr["RTN_CD"] = 500;
                $rtnArr["ERR_CD"] = 511;
                $rtnArr["RTN_MSG"] = "유효한 client가 아닙니다.(Invalid client)" ;      
                return $rtnArr; 
            }

            $redirect_uri = $result[0]["redirect_uri"];
        }



        //10 ID/비번이 맞는지 검사
        //$stmt = $this->DB->prepare('select * from oauth_users where username = ? ');
        $stmt = $this->DB->prepare('select * from CMN_USR where USR_ID=? and USR_PWD=sha2(concat(?,?),512)');
        if ($stmt == false){
            var_dump($this->DB->errno, $this->DB->error);
            $rtnArr["RTN_CD"] = 500;
            $rtnArr["ERR_CD"] = 520;
            $rtnArr["RTN_MSG"] = "make stmt prepare error : (" . $this->DB->errno . ") " . $this->DB->error ;
            return $rtnArr;
        }
        else
        {
            echo "username = " . $req->post["username"] . "\n";
            echo "CFG_SEC_SALT = " . $CFG["CFG_SEC_SALT"] . "\n";
            echo "password = " . $req->post["password"] . "\n";
            echo "pwd_hash = " . pwd_hash($req->post["password"],$CFG["CFG_SEC_SALT"]) . "\n";
            

            $result = $stmt->execute(array($req->post["username"],$CFG["CFG_SEC_SALT"],$req->post["password"]));
            $stmt->close();
            //var_dump($result);
        }
        
        $map["user_seq"] = $result[0]["USR_SEQ"];

        //20 토큰 DB넣고 리턴
        if(trim($map["user_seq"]) != ""){

            //31 access token 넣기 
            echo "db accessTOken go\n";
            $accessToken = $this->generateAccessToken(); //토큰 만들기

            $rtnArr["RTN_DATA"]["access_token"] = $accessToken;

            $stmt = $this->DB->prepare("
                insert into oauth_access_tokens (
                    access_token, client_id, user_id, expires, scope
                    ,user_seq
                )values(
                    ?, ?, ?, DATE_ADD(NOW(), INTERVAL 60 MINUTE),''
                    ,?
                )
                ");
            if ($stmt == false){
                var_dump($this->DB->errno, $this->DB->error);return;
            }
            else
            {
                //echo "username = " . $req->post["username"] . "\n";
                echo "password = " . $req->post["password"] . "\n";
                //echo "sha1(password) = " . sha1($req->post["password"]) . "\n";
    
                $result = $stmt->execute(array(
                    $accessToken
                    , $req->post["client_id"]
                    , $req->post["username"]
                    , $map["user_seq"]
                ));
                $stmt->close();
                //var_dump($result);
            }   

            //32 refresh token 넣기
            echo "db refreshToken go\n";
            $refreshToken = $this->generateAccessToken(); //토큰 만들기
            $rtnArr["RTN_DATA"]["refresh_token"] = $refreshToken;

            $stmt = $this->DB->prepare("
                insert into oauth_refresh_tokens (
                    refresh_token, client_id, user_id, expires, scope
                    ,user_seq
                )values(
                    ?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 DAY),''
                    ,?
                )
                ");
            if ($stmt == false){
                var_dump($this->DB->errno, $this->DB->error);return;
            }
            else
            {
                //echo "username = " . $req->post["username"] . "\n";
                echo "password = " . $req->post["password"] . "\n";
                //echo "sha1(password) = " . sha1($req->post["password"]) . "\n";
    
                $result = $stmt->execute(array(
                    $refreshToken
                    , $req->post["client_id"]
                    , $req->post["username"]
                    , $map["user_seq"]
                ));
                $stmt->close();
                //var_dump($result);
            }   

            //모두 성공한 경우 client_id 담아서 리턴
            $rtnArr["RTN_DATA"]["redirect_uri"] = $redirect_uri;

        }else{
            $rtnArr["RTN_CD"] = 500;
            $rtnArr["ERR_CD"] = 520;
            $rtnArr["RTN_MSG"] = "ID 혹은 비밀번호가 일치하지 않습니다." ;
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


        //10 요청토큰이 유효한지 확인
        $access_token = $req->get["access_token"];

        if(strlen($access_token) < 10){
            $rtnArr["RTN_CD"] = 500;
            $rtnArr["ERR_CD"] = 510;
            $rtnArr["RTN_MSG"] = "요청 토큰 정보가 없습니다. (request get 'access_token')";
            return $rtnArr;
        }

        $stmt = $this->DB->prepare("
        select TIMESTAMPDIFF(SECOND,now(),expires) as OVERTM, user_seq
        from oauth_access_tokens 
        where access_token = ?");
        if ($stmt == false){
            var_dump($this->DB->errno, $this->DB->error);return;
        }
        else
        {
            $result = $stmt->execute(array($access_token));
            $stmt->close();
            //var_dump($result);
            
            echo "OVERTM = " . $result[0]["OVERTM"] . "\n";

            //값이 0보자 작으면 오류 리턴
            if(intval($result[0]["OVERTM"]) < 0){
                $rtnArr["RTN_CD"] = 500;
                $rtnArr["ERR_CD"] = 520;
                $rtnArr["RTN_MSG"] = "요청토근이 만료되었습니다.(expires:" . $result[0]["OVERTM"] . ")";
                return $rtnArr;
            }
            
        }   

        //리턴할 사용자 최소정보
        $stmt = $this->DB->prepare("
        select * 
        from CMN_USR 
        where USR_SEQ = ?");
        if ($stmt == false){
            var_dump($this->DB->errno, $this->DB->error);return;
        }
        else
        {
            echo "사용자정보 뽑기 user_seq = " . $result[0]["user_seq"] . "\n";
            $result = $stmt->execute(array($result[0]["user_seq"]));
            $stmt->close();
            //var_dump($result);
        }
        echo "사용자 정보 result[0]\: ";
        var_dump($result[0]);

        $rtnArr["RTN_DATA"]["USER_INFO"] = $result[0];

        $map["user_seq"] = $result[0]["USR_SEQ"];
        $map["remote_addr"] = $req->server["remote_addr"];

        echo "user_seq = " . $map["user_seq"] . "\n";
        echo "remote_addr = " . $map["remote_addr"] . "\n";

        //echo json_encode($map, JSON_PRETTY_PRINT);

        //20 db에서 권한조회해서 리턴하기
        $stmt = $this->DB->prepare("
        select b.PGMID, b.AUTH_ID
        from CMN_GRP_USR a
            join CMN_GRP_AUTH b on a.GRP_SEQ = b.GRP_SEQ
            join CMN_MNU c on b.PGMID = c.PGMID
        where a.USR_SEQ = ?
        and c.PGMTYPE IN (
                select PGMTYPE from CMN_IP where ALLOW_IP = ? or ALLOW_IP = '0.0.0.0'
            )
        order by b.PGMID, b.AUTH_ID
        ");
        if ($stmt == false){
            var_dump($this->DB->errno, $this->DB->error);return;
        }
        else
        {
            //echo "username = " . $req->post["username"] . "\n";
            echo "password = " . $req->post["password"] . "\n";
            //echo "sha1(password) = " . sha1($req->post["password"]) . "\n";

            $result = $stmt->execute(array(
                $map["user_seq"]
                ,$map["remote_addr"]
            ));
            $stmt->close();
            //var_dump($result);
        }   
    
        $lastPgmid = "";
        $rtnVal = null;
        for($i=0;$i<count($result);$i++){
            $tMap = $result[$i];
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