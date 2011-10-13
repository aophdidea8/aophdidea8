(function($) {
	function postcodeSuccess(data) {
		var input = $('#postcodeInput');
		input.each(function(){
			$(this).val(data.postcode);
		});
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
					
					//If we've got these add a pair of hidden fields to the form.
					//
					
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
	});
	
})(jQuery);