<?php
$storage = $_GET['lat'] . "-" . $_GET['long'];
$filename = "tmp/".md5($storage);

if (file_exists($filename)) {
	echo file_get_contents($filename);
	return;
}


//http://localhost/aophdidea8/lastfmFeed.php?lat=51.51288236796371&long=-0.14621257781982422
//http://localhost/aophdidea8/lastfmFeed.php?location=London
// Include the API
require_once('vars.php');
require 'library/lastfmapi/lastfmapi.php';
require_once('library/utils.php');
require_once('library/http.php');

require_once('library/tfl.php');

// Put the auth data into an array
$authVars = array(
        'apiKey' => 'e01ccf0354acdfd9283faa1efc11d939',
        'secret' => '1214595d064a922637523a3aa52f961b',
        'username' => 'aophdidea8',
        'sessionKey' => '',
        'subscriber' => ''
);

// Pass the array to the auth class to eturn a valid auth
$auth = new lastfmApiAuth('setsession', $authVars);

// Call for the album package class with auth data
$apiClass = new lastfmApi();
$geoClass = $apiClass->getPackage($auth, 'geo');

// Setup the variablesLondon

if( isset($_GET['location']) ) 
{

    $methodVars = array(
            //'location' => 'London'
            'location' => $_GET['location'],
            'distance ' => 10,
            'limit' => 10
    );

}
else if( isset($_GET['lat']) && isset($_GET['long']) ) 
{

    $methodVars = array(
            //'lat' => '51.51288236796371',
            //'long' => '-0.14621257781982422',
            'lat' => $_GET['lat'],
            'long' => $_GET['long'],
            'distance ' => 5,
            'limit' => 10
    );

}
else
{
    die('<b>Error - </b><i>No Lat & Long or Location (city) defined</i>');
}
    
if ( $events = $geoClass->getEvents($methodVars) ) {
	$tfl = new Tfl();
	$c = new HttpClient("get", "http://willimakeit-aophdidea8.dotcloud.com/lat-long-to-postcode.php", true);
	$c->lat = $_GET['lat'];
	$c->lon = $_GET['long'];

	$data = $c->execute();

	$d = $data['body'];

	$json = json_decode($d);
	$current_location = $json->postcode;

	foreach($events['events'] as &$event) {
		echo $event['venue']['location']['point']['lat'];
		$c = new HttpClient("get", "http://willimakeit-aophdidea8.dotcloud.com/lat-long-to-postcode.php", true);
		$c->lat = $event['venue']['location']['point']['lat'];
		$c->lon = $event['venue']['location']['point']['long'];

		$data = $c->execute();

		$d = $data['body'];

		$json = json_decode($d);
		$venue_location = $json->postcode;
		//Check distance
		try
		{
			$journey = $tfl->check($current_location, $venue_location, 'locator', 'locator');
		}
		catch (Exception $e)
		{
			continue;
		}
		if (isset($journey->arrive)) {
			preg_match("/(.*):(.*)/si", $journey->arrive, $time);
			if ($time[1] < 19)
				$event['possible'] = true;
			else
				$event['possible'] = false;
		}
	}

	$content =  json_encode($events);
	echo $content;
	file_put_contents($filename, $content);
}
else {
    die('<b>Error '.$geoClass->error['code'].' - </b><i>'.$geoClass->error['desc'].'</i>');
}
?>
