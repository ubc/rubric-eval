 (function ($){
	 $(document).ready(function() {
		 
		//dealing with + Grading Group buton
		$('#add_grading_group_btn').click(function() {
			if ($(this).html().indexOf('+') >= 0) {
				$(this).text($(this).html().replace('+','-'));
				$('.add_grading_group').slideDown();
			} else {
				$(this).text($(this).html().replace('-','+'));
				$('.add_grading_group').slideUp();
			}
		});
		
		//dealing with + Advanced Options button
		$('#add_grading_group_advanced_btn').click(function() {
			if ($(this).html().indexOf('+') >= 0) {
				$(this).text($(this).html().replace('+','-'));
				$('.add_grading_group_advanced').slideDown();
			} else {
				$(this).text($(this).html().replace('-','+'));
				$('.add_grading_group_advanced').slideUp();
			}
		});
		
		//dealing with removing rows
		$('.ctlt_rubric_delete_row').click(function() {
			var row = $(this).attr('data-row');
			if (confirm('Are you sure you want to delete this?')) {
				$(this).parent().parent().remove();
			} 
		});
		
		//submit form
		$('#rubric_eval_form').submit(function(e) {
			var inputs = $(this).serialize();
			var data = {
					action: 'rubric_eval_mark',
					data: inputs
			};

			$.post('/wp-admin/admin-ajax.php', data, function(response) {
				alert(response);
			});
			e.preventDefault();
		});

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
		
		//now to deal with datepicker!
		$('#rubric_evaluation_grading_group_field_duedate').datepicker();
	 });
	 
	 //thanks to https://codex.wordpress.org/Plugin_API/Action_Reference/quick_edit_custom_box
	// we create a copy of the WP inline edit post function
	var $wp_inline_edit = inlineEditPost.edit;
	// and then we overwrite the function with our own code
	inlineEditPost.edit = function( id ) {
		// "call" the original WP edit function
		// we don't want to leave WordPress hanging
		$wp_inline_edit.apply( this, arguments );
	
		// now we take care of our business
	
		// get the post ID
		var $post_id = 0;
		if ( typeof( id ) == 'object' )
			$post_id = parseInt( this.getId( id ) );
	
		if ( $post_id > 0 ) {
			// define the edit row
			var $edit_row = $( '#edit-' + $post_id );
			var $post_row = $( '#post-' + $post_id );
			
			//get the data
			var $rubric_mark = $( '.rubric_eval_mark_value', $post_row).val();

			//populate quick edit
			$( '.rubric_evaluation_select:input', $edit_row ).val($rubric_mark);
	
		}
	};
	 
 })(jQuery);
