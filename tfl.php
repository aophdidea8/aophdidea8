<?php
	/*
	 * Usage:
	 * tfl.php?from=ealing+broadway&to=white+city
	 * tfl.php?from=w139ra&from_type=locator&to=white+city
	 */
	require_once('library/http.php');
	require_once('library/utils.php');

	$from = $_GET['from'];
	$from_type = (isset($_GET['from_type']) ? $_GET['from_type'] : 'stop');
	$to = $_GET['to'];
	$to_type = (isset($_GET['to_type']) ? $_GET['to_type'] : 'stop');

	$url = "http://journeyplanner.tfl.gov.uk/user/XSLT_TRIP_REQUEST2";
	$request = new HttpClient('get', $url);
	$request->language = "en";
	$request->name_origin = $from;
	$request->type_origin = $from_type;
	$request->name_destination = $to;
	$request->type_destination = $to_type;
	$request->sessionID = 0;
	$result = $request->execute();

	$html = $result['body'];

	$response = new StdClass;

	/* start parsing */
	preg_match('/<table class="jpresults">.*<\/table>/si', $html, $matches);
	$results_table = $matches[0];

	preg_match_all("/<tr>(.*?)<\/tr>/si", $results_table, $results_table_rows);

	if (count($results_table_rows[0]) == 0) {
		$response->error = "no results";
		echo json_encode($response);
	} else {
		for ($i = 1; $i <= count($results_table_rows[0])-1; $i++) {
			$results_table_row = $results_table_rows[0][$i];
			preg_match_all("/<td(.*?)>(.*?)<\/td>/i", $results_table_row, $results_entry);
			preg_match("/(.*):(.*)/si", $results_entry[0][1], $time);
			$min = $time[2];
			if ($min > date('i')) break;
		}
		/* stop parsing */

		$response->depart = substr($results_entry[0][1], 0, -5);
		$response->arrive = substr($results_entry[0][2], 0, -5);
		$response->duration = substr($results_entry[0][3], 0, -5);

		echo json_encode($response);
	}
?>
