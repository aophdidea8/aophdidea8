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
	
	<link rel="stylesheet" href="css/formalize.css" />
	<script src="js/jquery.formalize.js"></script>

	<script src="js/libs/jquery.js"></script>
	<script src="js/libs/storage.js"></script>
	<script src="js/geolocation.js"></script>
	<script src="js/formSubmit.js"></script>
	<script src="js/sectionSlide.js"></script>
	<script src="js/map.js"></script>
	<script>
		$(document).ready(function() {
			$('body').trigger('mapLoad');
			$('#displayAs a').click(function() {
				$('#displayAs').children().removeClass('active');
				$(this).addClass('active');
				newDiv = $(this).attr('href').replace('/','');
				$('.optionBox').hide();
				$('#events'+newDiv).show();
				return false;
			});
		});
	</script>
</head>
<body>

<div id="container">
	<div id="main" role="main">
		
		<form id="locationInput">
			<input type="hidden" name="latitude"  id="lat" value="" />
			<input type="hidden" name="longitude" id="lon" value="" />
			<header>
				<h1>Will I make it?</h1>
			</header>
			<div class="searchSection"><label for="postcodeInput" class="postcodeInput"><span>Location:</span><input type="text" name="postcode" id="postcodeInput" /><input type="submit" id="submitButton"></label></div>
			<div class="clearLine"></div>
		</form>
		
		<section id="userActionsOne">
			<header><h2>Event Listings</h2></header>
			
			<div id="displayAs">
				<a href="/Listing" class="list active">List</a>
				<a href="/Map" class="map">Map</a>
				<div class="clearLine"></div>
			</div>
			
			<div id="eventsListing" class="optionBox">
				<section class="music">
					<header><h3><a href="" class="less">4 Gigs found<span></span></a></h3></header>
					<article class="listItem">
						<section>
							<header><h2>Tom Vek</h2></header>
							<article>
								<p>Blah blah blah</p>
							</article>
						</section>
					</article>
				</section>
				<section class="bowling">
					<header><h3><a href="">1 Social Activity found<span></span></a></h3></header>
					<article class="listItem">
						<section>
							<header><h2>Bowling</h2></header>
							<article>
								<p>Blah blah blah</p>
							</article>
						</section>
					</article>
				</section>
				<section class="rugby">
					<header><h3><a href=""><?php echo rand(2,20) ?> Spectator Sports found<span></span></a></h3></header>
					<article></article>
				</section>
				<section class="television">
					<header><h3><a href=""><?php echo rand(2,15) ?> looting's to be had<span></span></a></h3></header>
					<article></article>
				</section>
			</div>
			<div id="eventsMap"  class="optionBox">
				<div id="map_canvas"></div>
			</div>
		</section>
		
	</div>
	
</div> <!--! end of #container -->
<footer>
	<p><a href="http://twitter.com/aophdidea8">Follow us on Twitter</a> we are at the AOP / Mozilla Hack day</p>
</footer>
</body>
</html>
