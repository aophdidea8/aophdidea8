$(document).ready( function () 
	{
        $('#submitButton').click(function (e) 
		{
			e.preventDefault();
			
			lon = $('#lon').val();
			lat = $('#lat').val();
			
			if (lat == '' || lon == '')
			{
				translatePostcode();
			}
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