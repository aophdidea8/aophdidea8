<?php 
require_once('vars.php');
require_once('library/http.php');
require_once('library/debug.php');
require_once('library/facebook/facebook.php');


$lat = HttpData::getQueryAsString('lat');
$lon = HTtpData::getQueryAsString('lon');

$url = "http://where.yahooapis.com/geocode";


$c = new HttpClient("get", $url);
$c->gflags = "R";
$c->location = "$lat $lon";
$c->flags = 'J';
$c->appid= YAHOO_ID;

$data = $c->execute();

$d = $data['body'];

$json = json_decode($d);

try
{
	$results = $json->ResultSet->Results;
}
catch (Exception $e)
{
	 header('HTTP/1.1 500 Internal Server Error');
	 echo "Postcode not found";
	 exit;
}
	

$result = array_shift($results);

$response = new stdClass;
$response->postcode = $result->postal;

echo json_encode($response);
