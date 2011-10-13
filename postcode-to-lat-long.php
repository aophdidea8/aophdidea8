<?php

require_once('library/http.php');
require_once('library/debug.php');
require_once('library/facebook/facebook.php');

// get the data
$pcode = HttpData::getQueryAsString('postcode');

// normalise the postcode
$postcode = normalisePostcode($postcode);



// return a json response with the lat and long
$response = new StdClass;
$response->lat = $lat;
$response->long = $long;

return json_encode($response);