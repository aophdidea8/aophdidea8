$(document).ready( function () 
	{
        $('#locationInput').submit(function (e) 
		{
			e.preventDefault();
			translatePostcode();
			
		});
	}
);

function translatePostcode()
{
	postcode = $('#postcodeInput').val();
	
	$.ajax({
		url: 'postcode-to-lat-long.php?postcode=' + postcode,
		dataType: 'json',
		success: function (r) { $('#lat').val(r.lat); $('#lon').val(r.lon); },
		error: function (a,b,c) { }
	});
}