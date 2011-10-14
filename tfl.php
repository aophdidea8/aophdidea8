<?php
	/*
	 * Usage:
	 * tfl.php?from=ealing+broadway&to=white+city
	 * tfl.php?from=w139ra&from_type=locator&to=white+city
	 */
	require_once('library/tfl.php');
	$tfl = new Tfl();
	$from = $_GET['from'];
	$from_type = (isset($_GET['from_type']) ? $_GET['from_type'] : 'stop');
	$to = $_GET['to'];
	$to_type = (isset($_GET['to_type']) ? $_GET['to_type'] : 'stop');

	echo json_encode($tfl->check($from, $to, $from_type, $to_type));
?>
