 (function ($){
	 $(document).ready(function() {
		//--- front end marking section ---//
		//submit form
		$('#rubric-eval-form').submit(function(e) {
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
	});	 
 })(jQuery);
