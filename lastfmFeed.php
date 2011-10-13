<?php
//http://localhost/aophdidea8/lastfmFeed.php?lat=51.51288236796371&long=-0.14621257781982422
//http://localhost/aophdidea8/lastfmFeed.php?location=London
// Include the API
require 'library/lastfmapi/lastfmapi.php';

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

// Setup the variables

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
            'distance ' => 10,
            'limit' => 10
    );

}
else
{
    die('<b>Error - </b><i>No Lat & Long or Location (city) defined</i>');
}
    
if ( $events = $geoClass->getEvents($methodVars) ) {
    echo json_encode($events);
}
else {
    die('<b>Error '.$geoClass->error['code'].' - </b><i>'.$geoClass->error['desc'].'</i>');
}