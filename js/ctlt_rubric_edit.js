(function ($){
	 $(document).ready(function() {
		//--- page / post edit pages ---//
		//toggle radio butons for rubric related stuff.
		$('input.rubric_evaluation_radio').on('mouseenter focusin', function(e) {
			if ($(this).prop('checked')) {
				$(this).data('selected', 1);
			} else {
				$(this).data('selected', 0);
			}
		}).on('mouseleave focusout', function(e) {
			$(this).removeData('selected');	
		});
		$('input.rubric_evaluation_radio').on('click select', function() {
			if ($(this).data('selected')) {
				$(this).data('selected',0).prop('checked', false);
			} else {
				$(this).data('selected',1).prop('checked', true);
			}
		});
	 });
 })(jQuery);
