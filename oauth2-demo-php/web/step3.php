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
    $res = $client->request('GET', 'http://172.17.0.1:8081/lockdin/resource', [
        'timeout' => 1,
        'connect_timeout' => 1,
        'read_timeout' => 2,
        'query' => [
            'access_token' => '43af51f689d16005c2ff2b548c23630f85799a09'
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
    

*/


?>