<?php 
require_once('vars.php');
require_once('library/http.php');
require_once('library/debug.php');
require_once('library/facebook/facebook.php');


$lat = HttpData::getQueryAsString('lat');
$lon = HTtpData::getQueryAsString('lon');

$url = "http://where.yahooapis.com/geocode";
$url = 'http://www.uk-postcodes.com/latlng/'.$lat.','.$lon.'.json';
echo $url ;
$c = new HttpClient("get", $url, true);
/*$c->gflags = "R";
$c->location = "$lat $lon";
$c->flags = 'J';
$c->appid= YAHOO_ID; */

$data = $c->execute();

$body = json_decode($data['body']);

$postcode = $body->postcode;
$response = new stdClass;
$response->postcode = $result->postal;

echo json_encode($response);
