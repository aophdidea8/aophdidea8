(function($) {
	if(location.geolocation){
		location.geolocation.getCurrentPosition(function(position){
			var lat,
			longit;
			
			lat = position.coords.latitude;
			longit = position.coords.longitude;
		});
	}
})(jQuery);