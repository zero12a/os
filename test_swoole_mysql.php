<?php
$CFG = require_once(__DIR__ . "/../common/include/incConfig.php");

require_once(__DIR__ . "/../common/include/incUtil.php");
require_once(__DIR__ . "/../common/include/incSec.php");
require_once(__DIR__ . "/../common/include/incUser.php");

$cfgDb = $CFG["CFG_DB"]["OS"];

var_dump($cfgDb);



$s = microtime(true);
Co\run(function() {
    echo 11111 . PHP_EOL;
    for ($c = 1; $c--;) {
        echo "C = " . $c . PHP_EOL;
        go(function () {
            global $cfgDb;
            echo 33333 . PHP_EOL;
            $mysql = new Swoole\Coroutine\MySQL;
            $mysql->connect([
                'host' => $cfgDb["HOST"],
                'user' => $cfgDb["ID"],
                'password' => aesDecrypt($cfgDb["PW"],$CFG["CFG_SEC_KEY"],$CFG["CFG_SEC_IV"]),
                'database' => $cfgDb["DBNM"]
            ]);
            if($mysql->errno) echo "mysql is error =  " . $mysql->error. PHP_EOL;
            $statement = $mysql->prepare("SELECT * FROM CMN_MNU WHERE MNU_SEQ > ? AND MNU_NM like ?");
            if(!$statement) echo "statement is null " . PHP_EOL;
            for ($n = 1; $n--;) {
                echo "  N = " . $n . PHP_EOL;
                $result = $statement->execute(array(0,'%'));
                if(count($result) > 0){
                    echo "SUCCESS = " . count($result) . PHP_EOL;
                }else{
                    echo "FAIL" . PHP_EOL;
                }
            }
            echo 55555 . PHP_EOL;
        });
    }
});
echo 'use ' . (microtime(true) - $s) . ' s';
?>
