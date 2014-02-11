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
		//get roles
		$roles = get_option('rubric_evaluation_roles_settings');
		$teacher = $roles['rubric_evaluation_roles_settings']['rubric_evaluation_role_teacher'];
		$student = $roles['rubric_evaluation_roles_settings']['rubric_evaluation_role_student'];
		$ta = $roles['rubric_evaluation_roles_settings']['rubric_evaluation_role_ta'];
		$user = CTLT_Rubric_Evaluation_Util::ctlt_rubric_get_user_role();
		
		$output = 'Once DB Table setup, and grades saved, we can display appropriate data based on roles (Teacher/TA/Student).';
		
		if (isset($student) && ( $user == $student )) {
 			//student
			$output = "(Student) Put student's own work and grades";
		} elseif (isset($ta) && ( $user == $ta )) {
			//ta
			$output = "(TA) Put maybe something like teacher's stuff?";
		} elseif (isset($teacher) && ( $user == $teacher )) {
			//teacher
			$output = "(Teacher) Put in something like mini spreadsheet or who has done / not done etc.";
		} 

		echo $output;
	}
}

if( is_admin() )
	$ctlt_rubric_evaluation_dashboard_widget = new CTLT_Rubric_Evaluation_Dashboard_Widget();