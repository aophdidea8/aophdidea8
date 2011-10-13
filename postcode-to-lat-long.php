<?php

require_once('vars.php');
require_once('library/http.php');
require_once('library/debug.php');
require_once('library/facebook/facebook.php');

// get the data
#$pcode = HttpData::getQueryAsString('postcode');

// normalise the postcode
#$postcode = normalisePostcode($postcode);

$postcode = 'HP65JW';

$url = 'http://www.nearby.org.uk/api/convert.php';

$request = new HttpClient('get', $url);
$request->key = NEARBY;
$request->p = $postcode;
$request->output = 'text';

var_dump($request->execute()); 

// return a json response with the lat and long
$response = new StdClass;
$response->lat = $lat;
$response->long = $long;

echo json_encode($response);
