 (function ($){
	$(document).ready(function() {	 
		//--- List posts / pages --- // 
		//thanks to https://codex.wordpress.org/Plugin_API/Action_Reference/quick_edit_custom_box
		// we create a copy of the WP inline edit post function
		 if (typeof inlineEditPost != 'undefined') { 
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
			}
		 }
	});
 })(jQuery);
