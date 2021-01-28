<?php
$CFG = require_once(__DIR__ . "/../common/include/incConfig.php");

require_once(__DIR__ . "/../common/include/incUtil.php");
require_once(__DIR__ . "/../common/include/incSec.php");
require_once(__DIR__ . "/../common/include/incUser.php");

$cfgDb = $CFG["CFG_DB"]["OS"];

var_dump($cfgDb);

$s = microtime(true);
Co\run(function() {
    $mysql = new Swoole\Coroutine\MySQL;

    $mysql->connect([
        'host' => $cfgDb["HOST"],
        'user' => $cfgDb["ID"],
        'password' => aesDecrypt($cfgDb["PW"],$CFG["CFG_SEC_KEY"],$CFG["CFG_SEC_IV"]),
        'database' => $cfgDb["DBNM"]
    ]);
    $statement = $mysql->prepare("SELECT * FROM CMN_MNU WHERE MNU_SEQ > ? AND MNU_NM like ?");

    $result = $statement->execute(array(0,'%'));
    if(count($result) > 0){
        echo "SUCCESS = " . count($result) . PHP_EOL;
    }else{
        echo "FAIL" . PHP_EOL;
    }
});


echo 'use ' . (microtime(true) - $s) . ' s';
?>
