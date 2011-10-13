(function($) {
		function initialise () {
			var latlng = new google.maps.LatLng(-34.397, 150.644);
			var myOptions = {
				zoom: 8,
				center: latlng,
				mapTypeId: google.maps.MapTypeId.ROADMAP
			};
			var map = new google.maps.Map(document.getElementById("map_canvas"), myOptions);
		}
		
		function loadMap () {
			var script = document.createElement("script");
			script.type = "text/javascript";
			script.src = "http://maps.googleapis.com/maps/api/js?sensor=false&callback=initialise";
			document.body.appendChild(script);
		}
		
		$('body').bind('mapLoad',loadMap);
})(jQuery);