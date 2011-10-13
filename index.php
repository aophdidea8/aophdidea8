<?php
require_once('library/http.php');
require_once('library/debug.php');
require_once('library/facebook/facebook.php');

/**

Http Client request:

$file = new HttpClient('get', 'http://www.google.com/');// trailing slasah is important!

$file is an associatvie array of the headers, with the $file['data'] being in the key.

Auto translates to utf8 etc

Additionally:

HttpData::getPostAsString('formname');

Available as Post, Get, Cookie AS Int, Bool, String 

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

	</header>
	<div id="main" role="main">
		Hello this is a test!
	</div>
	<footer>
		<p><a href="http://twitter.com/aophdidea8">Follow us on Twitter</a> we are at the AOP / Mozilla Hack day</p>
	</footer>
</div> <!--! end of #container -->

</body>
</html>
