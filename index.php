<?php
require_once('vars.php');
require_once('library/http.php');
require_once('library/debug.php');
require_once('library/facebook/facebook.php');

/**

Http Client request:

$file = new HttpClient('get', 'http://www.google.com/');// trailing slasah is important!

$file is an associatvie array of the headers, with the $file['body'] being in the key.

Auto translates to utf8 etc

Additionally:

HttpData::getPostAsString('formname');

Available as Post, Get, Cookie AS Int, Bool, String 

Google API key
ABQIAAAAVpSB4ZP0FjpvpW0IkxhAzBQDimnP5PSCPZPDxCK880mVupYQ6hQsleyQELV68SdW2GbrMMUvgowJIg

nearby.org.uk api key
3055bbe8ba5320

Facebook app id
169367879817491

Facebook secret id
37ec34f0592e31618e629a97edc9171c 

*/
?>

<!doctype html>
<html class="no-js" lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

	<title>Will I make it | AOP / Mozilla Hack day | Team 8</title>
	<meta name="description" content="">
	<meta name="author" content="">

	<meta name="viewport" content="width=device-width,initial-scale=1">

	<link rel="stylesheet" href="css/style.css">

	<script src="js/libs/jquery.js"></script>
	<script src="js/geolocation.js"></script>
	<script src="js/storage.js"></script>
</head>
<body>

<div id="container">
	<header>
		<h1>Will I make it?</h1>
	</header>
	<div id="main" role="main">
		
		<form id="locationInput">
			<label for="postcodeInput" class="postcodeInput"><span>Location:</span><input type="text" name="postcode" id="postcodeInput" /></label>
			<label for="dateInput" class="timeInput"><span>Time:</span><input type=time id="timeInput" value="<?php echo date('G:i'); ?>"></label>
			<label for="distanceInput"><span>Distance:</span>
			<select id="distanceInput">
				<option value="0">0 Miles</option>
				<option value="5">5 Miles</option>
				<option value="10">10 Miles</option>
				<option value="15">15+ Miles</option>
			</select></label>
			<span id="displayViewInput">Display As:</span>
			<label for="listViewInput">List</label><input id="listViewInput" type="radio" name="displayStyle" value="list">
			<label for="mapViewInput">Map</label><input id="mapViewInput" type="radio" name="displayStyle" value="map">
		</form>
		
		<section id="userActionsOne">
			<header><h2>Event Listings</h2></header>
			<div>
				<section class="movies">
					<header><h3><a href="">4 Movies found</a></h3></header>
					<article></article>
				</section>
				<section class="bowling">
					<header><h3><a href=""><?php echo rand(0,10) ?> Social Activities found</a></h3></header>
					<article></article>
				</section>
				<section class="rugby">
					<header><h3><a href=""><?php echo rand(0,10) ?> Spectator Sport found</a></h3></header>
					<article></article>
				</section>
				<section class="television">
					<header><h3><a href=""><?php echo rand(0,10) ?> looting's to be had</a></h3></header>
					<article></article>
				</section>
			</div>
		</section>
		
	</div>
	<footer>
		<p><a href="http://twitter.com/aophdidea8">Follow us on Twitter</a> we are at the AOP / Mozilla Hack day</p>
	</footer>
</div> <!--! end of #container -->

</body>
</html>
