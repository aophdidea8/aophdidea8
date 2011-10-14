$(document).ready(function() {
		var map,
		mapOptions,
		markersArray = [];
	
		function initialise () {
			var latlng = new google.maps.LatLng($('#lat').val(), $('#lon').val());
			mapOptions = {
				zoom: 13,
				center: latlng,
				mapTypeId: google.maps.MapTypeId.ROADMAP
			};
			map = new google.maps.Map(document.getElementById("map_canvas"), mapOptions);
			$('body').trigger('mapInitialised');
		}
		
		function changeMapCenter (lat, lon) {
			var latlng = new google.maps.LatLng(lat, lon);
			map.setCenter(latlng);
		}

		function changeDiv(divId){
			var div = map.getDiv(),
			divNode = document.getElementById(divId),
			map = new google.maps.Map(divNode,mapOptions);
			id = $(div).attr('id');
			$(div).replaceWith('<div id="' + id + '"></div>');
		}
		
		function addMarker(lat, lon){
			var options = {
				map: map,
				position: new google.maps.LatLng(lat,lon)
			};
			if(arguments[2] === true){
				options.icon = new google.maps.MarkerImage('/img/marker_music.png');
			}
			
			var marker = new google.maps.Marker(options);
			if(arguments[3]){
				addInfoPanel(marker,arguments[3]);
			}
			markersArray.push(marker);
		}
		
		function addInfoPanel(marker,info){
			var infowindow = new google.maps.InfoWindow({
				content: info
			});
			google.maps.event.addListener(marker, 'click', function() {
				infowindow.open(map,marker);
			});
		}
		
		function removeAllMarkers(){
			var i = 0,
			arrayLength = markersArray.length;
			for (;i<arrayLength;i += 1) {
				markersArray[i].setMap(null);
			}
		}

		function loadMap () {
			if(!map){
				var script = document.createElement("script");
				script.type = "text/javascript";
				script.src = "http://maps.googleapis.com/maps/api/js?sensor=false&callback=idea8.initialise";
				document.body.appendChild(script);
			}
			
		}

		if(!window.idea8){
			window.idea8 = {};
		}
		
		window.idea8.initialise = initialise;
		window.idea8.changeMapCenter = changeMapCenter;
		window.idea8.addMarker = addMarker;
		window.idea8.changeDiv = changeDiv;
		window.idea8.removeAllMarkers = removeAllMarkers;
		
		loadMap();
});