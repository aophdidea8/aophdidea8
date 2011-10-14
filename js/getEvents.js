
function getEvents()
{
	lat = $('#lat').val();
	lon = $('#lon').val();
	
	$.ajax({
		url: 'lastfmFeed.php?lat=' + lat + '&long=' + lon,
		dataType: 'json',
		success: eventsSuccess,
		error: generalError
	});
}

function eventsSuccess(result)
{
	console.log(result);
}