(function($) {
	if(navigator.geolocation){
		navigator.geolocation.getCurrentPosition(function(position){
			var lat,
			longit;
			
			lat = position.coords.latitude;
			longit = position.coords.longitude;
		});
	}
})(jQuery);