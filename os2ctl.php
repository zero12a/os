<?php
header("Content-Type: application/json; charset=UTF-8"); //SVRCTL
header("Cache-Control:no-cache");
header("Pragma:no-cache");


$_RTIME = array();
array_push($_RTIME,array("[TIME 00.START]",microtime(true)));
$CFG = require_once('../common/include/incConfig.php');//CG CONFIG

//print_r($CFG);
//exit;
require_once($CFG["CFG_LIBS_VENDOR"]);
require_once('os2mng.php');

array_push($_RTIME,array("[TIME 10.INCLUDE SERVICE]",microtime(true)));
require_once('../common/include/incUtil.php');//CG UTIL
require_once('../common/include/incRequest.php');//CG REQUEST
require_once('../common/include/incDB.php');//CG DB
require_once('../common/include/incSec.php');//CG SEC
require_once('../common/include/incAuth.php');//CG AUTH
require_once('../common/include/incUser.php');//CG USER
require_once('../common/include/incLdap.php');//CG LDAP

//하위에서 LOADDING LIB 처리
array_push($_RTIME,array("[TIME 20.IMPORT]",microtime(true)));

$reqToken = reqGetString("TOKEN",37);
$resToken = uniqid();

$log = getLogger(
	array(
	"LIST_NM"=>"log_CG"
	, "PGM_ID"=>"os2ctl"
	, "REQTOKEN" => $reqToken
	, "RESTOKEN" => $resToken
	, "LOG_LEVEL" => Monolog\Logger::DEBUG
	)
);
$log->info("Os2Control___________________________start");

array_push($_RTIME,array("[TIME 30.GET LOGGER]",microtime(true)));

//컨트롤 명령 받기

$ctl = reqGetString("CTL",50);

$REQ["client_id"] = reqPostString("client_id",100);
$REQ["client_secret"] = reqPostString("client_secret",100);
$REQ["username"] = reqPostString("username",30);
$REQ["password"] = reqPostString("password",30);
$REQ["access_token"] = reqGetString("access_token",100);
$REQ["remote_addr"] = $_SERVER["REMOTE_ADDR"];

//var_dump($REQ);
/*
{
	"client_id": "svcfront",
	"client_secret": "frontoffice",
	"username": "zero12a",
	"password": "3333"
}
*/
array_push($_RTIME,array("[TIME 40.CTL switch]",microtime(true)));

$os2Obj = new os2Mng();
$rtnArr = array();
switch ($ctl){
    case "getResource" :
        //echo "111\n";
        $rtnArr = $os2Obj->getResource($REQ); //컨디션1, 저장
        break;
    case "newToken" :
        //echo "222\n";
        $rtnArr = $os2Obj->newToken($REQ); //폼뷰1, 삭제
        break;
    case "refreshToken" :
        //echo "333\n";
        $rtnArr = $os2Obj->refreshToken($REQ); //폼뷰1, 삭제
        break;            
    default:
        //echo "444\n";
        $rtnArr = array("RTN_CD"=>500,"ERR_CD"=>110,"RTN_MSG"=>"처리 명령을 찾을 수 없습니다. (Don't exist CTL request " . $pathCtl . ")");
        break;
}

header("Access-Control-Allow-Origin:*");//다른 도메인에서 호출 허용
echo json_encode($rtnArr);


array_push($_RTIME,array("[TIME 50.SVC]",microtime(true)));


if($PGM_CFG["SECTYPE"] == "POWER" || $PGM_CFG["SECTYPE"] == "PI") $objAuth->logUsrAuthD($reqToken,$resToken);;	//권한변경 로그 저장
	array_push($_RTIME,array("[TIME 60.AUGHD_LOG]",microtime(true)));
//실행시간 검사
for($j=1;$j<sizeof($_RTIME);$j++){
	$log->debug( $_RTIME[$j][0] . " " . number_format($_RTIME[$j][1]-$_RTIME[$j-1][1],4) );

	if($j == sizeof($_RTIME)-1) $log->debug( "RUN TIME : " . number_format($_RTIME[$j][1]-$_RTIME[0][1],4) );
}
//서비스 클래스 비우기
unset($os2Obj);

$log->info("Os2Control___________________________end");
$log->close(); unset($log);
?>
