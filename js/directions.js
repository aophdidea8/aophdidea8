(function($){
	function directions(origin,dest){
		var origin = 'origin=' + origin.lat + ',' + origin.lon,
		destination = 'destination=' + dest.lat + ',' + dest.lon,
		sensor = navigator.geolocation ? 'true' : 'false';
		
		sensor = 'sensor=' + sensor;
		
		parameters = origin + '&' + destination + '&' + sensor;
		
		$.ajax({
			url: 'http://maps.googleapis.com/maps/api/directions/json?' + parameters,
			dataType: 'json',
			success: function(data){
				return data;
			},
			error: function(){
				return false;
			}
		});
	}
	
	if(!window.idea8){
		window.idea8 = {};
	}
	window.idea8.getDirections = directions;
})(jQuery);