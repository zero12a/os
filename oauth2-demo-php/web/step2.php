<?php
echo "11";

require_once "../../../lib/php/vendor/autoload.php";

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;

echo "22";

//exit;

// Create a client with a base URI
$client = new GuzzleHttp\Client();
// Send a request to https://foo.com/api/test
//     'body' => 'grant_type=password&client_id=demoapp&client_secret=demopass&username=demouser&password=testpass'

try{
    $res = $client->request('POST', 'http://172.17.0.1:8081/lockdin/token', [
        'timeout' => 1,
        'connect_timeout' => 1,
        'read_timeout' => 1,
        'form_params' => [
            'grant_type' => 'refresh_token',
            'client_id' => 'demoapp',
            'client_secret' => 'demopass',
            'refresh_token' => 'aad4eeb0aad17706a60f3c8f7c3a4cdb6787e498'
        ]
    ]);
    
    echo "<hr>" . $res->getStatusCode();
    // "200"
    echo "<hr>" . $res->getHeader('content-type')[0];
    // 'application/json; charset=utf8'
    echo "<hr>" . $res->getBody();

}catch(ClientException $e) {
    echo $e->getMessage() . "\n";
    echo $e->getRequest()->getMethod();
}catch(GuzzleException $e) {
    echo $e->getMessage() . "\n";
    echo $e->getRequest()->getMethod();
}

/*
{
    "access_token":"6760fdfdcc48e0ad153926fd79119c7bc6956bae"
    ,"expires_in":3600,"token_type":"Bearer","scope":null
    ,"refresh_token":"ba67a2912103eb26246eef47f1a84bbd3c461f9f"}

*/


?>