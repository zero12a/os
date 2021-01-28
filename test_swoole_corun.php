<?php

echo "00000" . PHP_EOL; 

$task1 = "null";
$task2 = "null";

//동시 작업을 실행하고, 내부로직의 go작업이 모두 종료되면, 99999로 이동함
Co\run(function()use($task1,$task2){
    $task3 = "null";
    $task4 = "null";
    echo 11111 . PHP_EOL; 
    echo "corun task1 = " . $task1  . PHP_EOL;
    echo "corun task2 = " . $task2  . PHP_EOL;
    $task1 = "aaaaa";
    $task2 = "bbbbb";
    go(function() {
        global $task1,$task3;
        Co::sleep(2);
        echo "go1 task1 = " . $task1  . PHP_EOL;        
        echo "go1 task3 = " . $task3  . PHP_EOL;        
        echo "Done 1\n";
        $task1 = "ccccc";
        $task3 = "ccccc";
    });
    echo 22222 . PHP_EOL;     
    go(function() {
        global $task2,$task4;
        Co::sleep(1);
        echo "go2 task2 = " . $task2  . PHP_EOL;    
        echo "go2 task4 = " . $task4  . PHP_EOL;            
        echo "Done 2\n";
        $task2 = "ddddd";
        $task4 = "ccccc";
    });
    echo 33333 . PHP_EOL; 
});

echo "task1 = " . $task1  . PHP_EOL;
echo "task2 = " . $task2  . PHP_EOL;


echo 99999 . PHP_EOL; 
?>