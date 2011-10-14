(function($) {
	$(document).ready(function(){
		var sections = $('#userActionsOne > div > section');
		sections.each(function(i){
			var section = $(this),
			link = $('a', this);
		
			if(i==0) {
				link.addClass('less');
			} else {
				section.children('article').hide();
			}
			
			link.each(function(){
				$(this).click(function(event){
					event.preventDefault();
					var article = section.children('article');

					if(article.children().length !== 0){
						article.slideToggle("slow", function () {
						      	if($(this).is(':visible')) {
									link.addClass('less');
								} else {
									link.removeClass('less');
								}
						});
					}
				});
			});
		});
	});
})(jQuery);