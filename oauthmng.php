<?php

class oauthMng
{
	private $REDIS;
    private $DB;
    public $LAUTH_SEQ;
    private $PREFIX_SESSION_ID;

	//생성자
	function __construct(){
        alog("authLog-__construct");
        
        $this->DB = new Swoole\Coroutine\MySQL();
        $this->DB->connect([
            'host' => '172.17.0.1',
            'user' => 'cg',
            'password' => 'cg1234qwer',
            'database' => 'CG',
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
            echo "client_id = " . $req->get["client_id"] . "\n";
            //echo "client_secret = " . $req->get["client_secret"] . "\n";
            //echo "sha1(password) = " . sha1($req->get["password"]) . "\n";

            $result = $stmt->execute(array($req->get["username"],$req->get["password"]));
            $stmt->close();
            var_dump($result);

            //
            if(trim($result["client_id"]) == ""){
                $rtnArr["RTN_CD"] = 500;
                $rtnArr["ERR_CD"] = 511;
                $rtnArr["RTN_MSG"] = "유효한 client가 아닙니다." ;      
                return $rtnArr; 
            }
        }

        //10 ID/비번이 맞는지 검사
        //$stmt = $this->DB->prepare('select * from oauth_users where username = ? ');
        $stmt = $this->DB->prepare('select * from oauth_users where username=? and password=sha1(?)');
        if ($stmt == false){
            var_dump($this->DB->errno, $this->DB->error);
            $rtnArr["RTN_CD"] = 500;
            $rtnArr["ERR_CD"] = 520;
            $rtnArr["RTN_MSG"] = "make stmt prepare error : (" . $this->DB->errno . ") " . $this->DB->error ;
            return $rtnArr;
        }
        else
        {
            echo "username = " . $req->get["username"] . "\n";
            //echo "password = " . $req->get["password"] . "\n";
            //echo "sha1(password) = " . sha1($req->get["password"]) . "\n";

            $result = $stmt->execute(array($req->get["username"],$req->get["password"]));
            $stmt->close();
            var_dump($result);
        }
        
        //20 토큰 DB넣고 리턴
        if($result[0]["username"] != ""){

            //31 access token 넣기 
            echo "db accessTOken go\n";
            $accessToken = $this->generateAccessToken(); //토큰 만들기

            $rtnArr["RTN_DATA"]["access_token"] = $accessToken;

            $stmt = $this->DB->prepare("
                insert into oauth_access_tokens (
                    access_token, client_id, user_id, expires, scope
                )values(
                    ?, ?, ?, DATE_ADD(NOW(), INTERVAL 60 MINUTE),''
                )
                ");
            if ($stmt == false){
                var_dump($this->DB->errno, $this->DB->error);return;
            }
            else
            {
                //echo "username = " . $req->get["username"] . "\n";
                echo "password = " . $req->get["password"] . "\n";
                //echo "sha1(password) = " . sha1($req->get["password"]) . "\n";
    
                $result = $stmt->execute(array($accessToken, $req->get["client_id"], $req->get["username"]));
                $stmt->close();
                var_dump($result);
            }   

            //32 refresh token 넣기
            echo "db refreshToken go\n";
            $refreshToken = $this->generateAccessToken(); //토큰 만들기
            $rtnArr["RTN_DATA"]["refresh_token"] = $refreshToken;

            $stmt = $this->DB->prepare("
                insert into oauth_refresh_tokens (
                    refresh_token, client_id, user_id, expires, scope
                )values(
                    ?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 DAY),''
                )
                ");
            if ($stmt == false){
                var_dump($this->DB->errno, $this->DB->error);return;
            }
            else
            {
                //echo "username = " . $req->get["username"] . "\n";
                echo "password = " . $req->get["password"] . "\n";
                //echo "sha1(password) = " . sha1($req->get["password"]) . "\n";
    
                $result = $stmt->execute(array($refreshToken, $req->get["client_id"], $req->get["username"]));
                $stmt->close();
                var_dump($result);
            }   


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
        //10 요청토큰이 유효한지 확인
        //20 db에서 권한조회해서 리턴하기


        $this->DB->setDefer();
        $this->DB->query('select * from oauth_access_tokens');

        return $this->DB->recv();
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
		alog("authLog-__toString");
    }
}

?>