(function($) {
	$(document).ready(function(){
		var sections = $('#userActionsOne > div > section');
		sections.each(function(){
			var section = $(this),
			link = $('a', this);
			
			link.each(function(){
				$(this).click(function(event){
					event.preventDefault();
					var article = section.children('article');
					
					if(article.children().length !== 0){
						article.slideToggle();
					}
				});
			});
		});
	});
})(jQuery);