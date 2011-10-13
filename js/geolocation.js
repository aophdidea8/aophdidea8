(function($) {
	function postcodeSuccess(data) {
		var input = $('#postcode');
		input.each(function(){
			$(this).val(data.postcode);
		});
	}
	
	function generalError(error){
		debugger;
	}
	
	if(navigator.geolocation){
		navigator.geolocation.getCurrentPosition(
			function(position){
				var lat,
				lon;
				
				lat = position.coords.latitude;
				lon = position.coords.longitude;
				
				$.ajax({
					url: 'lat-long-to-postcode.php?lat=' + lat + '&amp;lon=' + lon,
					dataType: 'json',
					success: postcodeSuccess,
					error: generalError
				});
			},
			generalError
		);
	}
})(jQuery);