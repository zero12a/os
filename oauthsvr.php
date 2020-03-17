<?php
$CFG = require_once(__DIR__ . "/../common/include/incConfig.php");

require_once(__DIR__ . "/../common/include/incUtil.php");
require_once(__DIR__ . "/../common/include/incSec.php");
require_once(__DIR__ . "/../common/include/incUser.php");

//서비스
require_once(__DIR__ . "/oauthmng.php");

$log = getLoggerSwoole(
	array(
	"LIST_NM"=>"log_CG"
    , "PGM_ID"=>"OAUTHSVR"
    , "LOG_LEVEL" => Monolog\Logger::INFO
	)
);
echo "\n 555";

var_dump($log);


$server = new Swoole\Http\Server("0.0.0.0", $CFG["CFG_OAUTH_PORT"]); //SWOOLE_BASE is deprecated
$server->set([
    'worker_num' => 4,
]);

$server->on('task', function(swoole_server $serv, int $task_id, int $src_worker_id, mixed $data) use(&$log){
    echo "on(task) _______________________________\n";
    $log->debug("on(task) _______________________________");
    //var_dump(get_included_files());
});

$server->on('finish', function(swoole_server $serv, int $task_id, string $data) use(&$log) {
    echo "on(finish) _______________________________\n";
    $log->info("on(finish) _______________________________");
    $log->close(); unset($log);
    //var_dump(get_included_files());
});

$server->on('connect', function(swoole_server $server, int $fd, int $from_id) use(&$log) {
    echo "on(connect) _______________________________\n";
    $log->debug("on(connect) _______________________________");
    //var_dump(get_included_files());
});

$server->on('receive', function(swoole_server $server, int $fd, int $reactor_id, string $data) use(&$log) {
    echo "on(receive) _______________________________\n";
    $log->debug("on(receive) _______________________________");
    //var_dump(get_included_files());
});

$server->on('WorkerStart', function($serv, $workerId) use(&$log) {
    echo "WorkerStart _______________________________\n";
    //$log->info("on(WorkerStart) _______________________________");    
    //var_dump(get_included_files());
});

$server->on('WorkerStop', function($serv, $workerId) use(&$log) {
    echo "WorkerStop _______________________________\n";
    $log->debug("on(WorkerStop) _______________________________");    
    //var_dump(get_included_files());
});

$server->on('Request', function ($req, $res) use($CFG,&$log){
    echo "Request _______________________________\n";
    $log->info("on(Request) _______________________________start");
    $time_start = microtime_float();



    //var_dump($req);
    $logContext = array(
        'SESSIONID' => $s
        , 'URL' => $t
        , 'access_token' => $req->get["access_token"]
        , 'refresh_token' => $req->get["refresh_token"]
        , 'USERID' => $req->post["username"]
        , 'USERSEQ' => getUserSeq()
        , 'REQTOKEN' => $req->get["req_token"]
        , 'RESTOKEN' => uniqid()
    );


    $pathCtl = $req->server["path_info"];
    echo "pathCtl = " . $pathCtl;
    $log->info("pathCtl = " . $pathCtl,$logContext);    
    $oauthObj = new oauthMng($req);
    $rtnArr = array();
    switch ($pathCtl){
        case "/getResource/" :
            echo "111\n";
            $rtnArr = $oauthObj->getResource($req); //컨디션1, 저장
            break;
        case "/newToken/" :
            echo "222\n";
            $rtnArr = $oauthObj->newToken($req); //폼뷰1, 삭제
            break;
        case "/refreshToken/" :
            echo "333\n";
            $rtnArr = $oauthObj->refreshToken($req); //폼뷰1, 삭제
            break;            
        default:
            echo "444\n";
            $rtnArr = array("RTN_CD"=>500,"ERR_CD"=>110,"RTN_MSG"=>"처리 명령을 찾을 수 없습니다. (" . $pathCtl . ")");
            break;
    }
    //var_dump($mysql_res);
    
    $res->header("Access-Control-Allow-Origin","*");//다른 도메인에서 호출 허용
    $res->end(json_encode($rtnArr));

    $time_end = microtime_float();
    $log->info("execute time(seconds) : " . number_format($time_end - $time_start,2));
    $log->info("on(Request) _______________________________end");    
});

$server->on("start", function ($server) use($CFG,&$log){
    $log->info("on(start) _______________________________");
    echo "Swoole http server is started at http://0.0.0.0:" . $CFG["CFG_OAUTH_PORT"] . "\n";
    echo "  master_pid = " . $server->master_pid . "\n";
    echo "  manager_pid = " . $server->manager_pid . "\n";
});


$server->start();

?>