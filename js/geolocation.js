(function($) {
	function postcodeSuccess(data) {
		
		$('#postcodeInput').val(data);
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
					
					postcodeSuccess('SE1 7UT');
					return;
					//If we've got these add a pair of hidden fields to the form.
					
					
					
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