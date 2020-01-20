<?php


$CFG = require_once "../common/include/incConfig.php";
require_once "../common/include/incUtil.php";

require_once "oauthmng.php";



$server = new Swoole\Http\Server("0.0.0.0", $CFG["CFG_OAUTH_PORT"], SWOOLE_BASE);
$server->set([
    'worker_num' => 4,
]);

$server->on('task', function(swoole_server $serv, int $task_id, int $src_worker_id, mixed $data) {
    echo "on(task) _______________________________\n";
    //var_dump(get_included_files());
});
$server->on('finish', function(swoole_server $serv, int $task_id, string $data) {
    echo "on(finish) _______________________________\n";
    //var_dump(get_included_files());
});
$server->on('connect', function(swoole_server $server, int $fd, int $from_id) {
    echo "on(connect) _______________________________\n";
    //var_dump(get_included_files());
});
$server->on('receive', function(swoole_server $server, int $fd, int $reactor_id, string $data) {
    echo "on(receive) _______________________________\n";
    //var_dump(get_included_files());
});

$server->on('WorkerStart', function($serv, $workerId) {
    echo "WorkerStart _______________________________\n";
    //var_dump(get_included_files());
});
$server->on('WorkerStop', function($serv, $workerId) {
    echo "WorkerStop _______________________________\n";
    //var_dump(get_included_files());
});
$server->on('Request', function ($req, $res) {
    echo "Request _______________________________\n";

    //var_dump($req);

    $pathCtl = $req->server["path_info"];
    echo "pathCtl = " . $pathCtl;
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
            echo "222\n";
            $rtnArr = $oauthObj->refreshToken($req); //폼뷰1, 삭제
            break;            
        default:
            echo "333\n";
            $rtnArr = array("RTN_CD"=>500,"ERR_CD"=>110,"RTN_MSG"=>"처리 명령을 찾을 수 없습니다. (" . $pathCtl . ")");
            break;
    }
    //var_dump($mysql_res);
    
    $res->end(json_encode($rtnArr));
});

$server->on("start", function ($server)use($CFG){
    echo "Swoole http server is started at http://0.0.0.0:" . $CFG["CFG_OAUTH_PORT"] . "\n";
    echo "  master_pid = " . $server->master_pid . "\n";
    echo "  manager_pid = " . $server->manager_pid . "\n";
});


$server->start();

?>