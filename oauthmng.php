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
        //10 ID/비번이 맞는지 검사
        //$this->DB->setDefer();

        //$stmt = $this->DB->prepare('select * from oauth_users where username = ? ');
        $stmt = $this->DB->prepare('select * from oauth_users where username=? and password=sha1(?)');
        if ($stmt == false){
            var_dump($this->DB->errno, $this->DB->error);return;
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
        //20 토큰, 리프토큰 만들고
        if($result[0]["username"] != ""){
            $token = $this->uuidgen4();
        }
        
        //30 토큰 DB넣고 리턴
        if($result[0]["username"] != ""){
            echo "db go\n";
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
    
                $result = $stmt->execute(array($token, $req->get["client_id"], $req->get["username"]));
                var_dump($result);
            }            
        }
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


	function __toString(){
		alog("authLog-__toString");
    }
}

?>