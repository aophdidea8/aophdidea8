
function getEvents()
{
	lat = $('#lat').val();
	lon = $('#lon').val();

	$.ajax({
		url: 'lastfmFeed.php?lat=' + lat + '&long=' + lon,
		dataType: 'json',
		success: eventsSuccess,
		error: function (a,b,c) {console.log(a, b, c)}
	});
}

function eventsSuccess(result)
{
	$('body').trigger('mapLoad');
	events = result.events;
	$.each(events, function (a, b)
	{
		title = b.title;
		
		lat = b.venue.location.point.lat;
		lon = b.venue.location.point.long;
		
		idea8.addMarker(lat, lon, title);
		
	});
}
