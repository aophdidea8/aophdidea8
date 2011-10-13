<?php

require_once('library/http.php');
require_once('library/debug.php');
require_once('library/facebook/facebook.php');

$response = new stdClass;
$response->postcode = "HP6 5JW";

return json_encode($response);