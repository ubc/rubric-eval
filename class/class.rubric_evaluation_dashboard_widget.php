<?php
/**
 * 
 * @author loongchan
 * @TODO: need to think about capability, so ONLY teacher see things? or maybe students get different view (grades)?
 * @TODO: 
 * 
 */
class CTLT_Rubric_Evaluation_Dashboard_Widget {
	
	public function __construct() {
		add_action( 'wp_dashboard_setup', array($this, 'setup_widget') );
	}
	
	public function setup_widget() {
		wp_add_dashboard_widget('ctlt_rubric_evaluation_widget', __('Rubric Evaluation Widget', 'ctlt_rubric_evaluation'), array( $this, 'output_widget'));
	}
	
	public function output_widget() {
		echo 'Once DB Table setup, and grades saved, we can display appropriate data based on roles (Teacher/TA/Student).';
	}
}

if( is_admin() )
	$ctlt_rubric_evaluation_dashboard_widget = new CTLT_Rubric_Evaluation_Dashboard_Widget();