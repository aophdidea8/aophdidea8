<?php

require_once('vars.php');
require_once('library/http.php');
require_once('library/debug.php');
require_once('library/facebook/facebook.php');

// get the data
$pcode = HttpData::getQueryAsString('postcode');

// normalise the postcode
$postcode = strtolower(str_replace(' ', '', $pcode));

/*
$url = "http://where.yahooapis.com/geocode";

$c = new HttpClient("get", $url);
$c->line3 = $postcode;
$c->flags = 'J';
$c->appid= YAHOO_ID;

var_dump($c->execute()); */


$url = 'http://www.nearby.org.uk/api/convert.php';

$request = new HttpClient('get', $url);
$request->key = NEARBY;
$request->p = $postcode;
$request->output = 'text';

$data = $request->execute(); 
$data = explode("\n", $data['body']);

$lines = explode(',', $data[4]);

$lat = $lines[2];
$lon = $lines[3];

// return a json response with the lat and long
$response = new StdClass;
$response->lat = $lat;
$response->lon = $lon;

echo json_encode($response);