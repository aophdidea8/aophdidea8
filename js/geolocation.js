(function($) {
	function postcodeSuccess(data) {
		
		$('#postcodeInput').val(data.postcode);
		getEvents();
	}
	
	function generalError(error){
	}
	
	$(document).ready(function(){
		
		if(navigator.geolocation){
			navigator.geolocation.getCurrentPosition(
				function(position){
					var lat,
					lon;
					
					lat = position.coords.latitude;
					lon = position.coords.longitude;
					
					$('#lat').val(lat);
					$('#lon').val(lon);	
					
					
					$.ajax({
						url: 'lat-long-to-postcode.php?lat=' + lat + '&lon=' + lon,
						dataType: 'json',
						success: postcodeSuccess,
						error: generalError
					});
				},
				generalError
			);
		}
	});
	
})(jQuery);