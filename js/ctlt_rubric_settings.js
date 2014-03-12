(function ($){
	 $(document).ready(function() {
		//--- rubric settings pages ---//
		//dealing with + Grading Group buton
		$('#add-grading-group-btn').click(function() {
			if ($(this).html().indexOf('+') >= 0) {
				$(this).text($(this).html().replace('+','-'));
				$('.add-grading-group').slideDown();
			} else {
				$(this).text($(this).html().replace('-','+'));
				$('.add-grading-group').slideUp();
			}
		});
		
		//dealing with + Advanced Options button
		$('#add-grading-group-advanced-btn').click(function() {
			if ($(this).html().indexOf('+') >= 0) {
				$(this).text($(this).html().replace('+','-'));
				$('.add-grading-group-advanced').slideDown();
			} else {
				$(this).text($(this).html().replace('-','+'));
				$('.add-grading-group-advanced').slideUp();
			}
		});
		
		//now to deal with datepicker!
		$('#rubric-evaluation-grading-group-field-duedate').datepicker();
		
		//dealing with removing rows
		$('.ctlt-rubric-delete-row').click(function() {
			var row = $(this).attr('data-row');
			if (confirm('Are you sure you want to delete this?')) {
				$(this).parent().parent().remove();
			} 
		});
	}); 
 })(jQuery);
