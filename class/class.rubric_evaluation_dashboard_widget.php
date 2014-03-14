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
		wp_register_style('CTLT_Rubric_Dashboard_Css', RUBRIC_EVALUATION_PLUGIN_URL.'css/ctlt_rubric_dashboard.css');
	}
	
	/**
	 * setup widgets
	 */
	public function setup_widget() {
		wp_add_dashboard_widget('ctlt_rubric_evaluation_widget', __('Rubric Evaluation Widget', 'ctlt_rubric_evaluation'), array( $this, 'output_widget'));
		wp_enqueue_style('CTLT_Rubric_Dashboard_Css');
	}
	
	/**
	 * output widget based on roles
	 */
	public function output_widget() {
		//get roles
		$roles = get_option('rubric-evaluation-roles-settings');
		$teacher = $roles['rubric-evaluation-roles-settings']['rubric-evaluation-roles-settings']['rubric-evaluation-role-teacher'];
		$student = $roles['rubric-evaluation-roles-settings']['rubric-evaluation-roles-settings']['rubric-evaluation-role-student'];
		$ta = $roles['rubric-evaluation-roles-settings']['rubric-evaluation-roles-settings']['rubric-evaluation-role-ta'];
		$user = CTLT_Rubric_Evaluation_Util::ctlt_rubric_get_user_role();
		
		$output = '';
		
		if (isset($student) && ( $user == $student )) { //student
			$output = $this->_output_student_dashboard($output);
		} elseif (isset($ta) && ( $user == $ta )) { //ta
			$output .= $this->_output_ta_dashboard($output);
		} elseif (isset($teacher) && ( $user == $teacher )) {//teacher
			$output .= $this->_output_teacher_dashboard($output);
		} 

		echo $output;
	}
	
	//======================================================================
	//
	// Private output functions
	//
	//======================================================================
	private function _output_student_dashboard($content) {
		$content = "Here is a list of the things you have done so far...";
		$current_user_id = get_current_user_id();
		$types = array('post', 'page');
		$terms = get_terms(RUBRIC_EVALUATION_TAXONOMY, array('hide_empty' => false));
		$all_posts = CTLT_Rubric_Evaluation_Util::ctlt_rubric_get_posts(array('post_type' => 'post,page', 'author' => $current_user_id));
		
		//ok, got content, now need to display.
		$content .= '<ul class="rubric-evaluation-list">';
		$content .= '<li><span class="rubric-evaluation-head rubric-evaluation-term">'.__('Category', 'ctlt_rubric_evaluation').'</span>';
		$content .= '<span class="rubric-evaluation-head rubric-evaluation-term-due">'.__('Due Date', 'ctlt_rubric_evaluation').'</span>';
		$content .= '<span class="rubric-evaluation-head rubric-evaluation-anchor">'.__('Title', 'ctlt_rubric_evaluation').'</span>';
		foreach ($terms as $term) {
			$term_desc = CTLT_Rubric_Evaluation_Util::ctlt_rubric_get_term_meta($term);
			$date_color = (empty($term_desc['duedate']) || (strtotime($term_desc['duedate']) > time()))? 'rubric-eval-date-valid' : 'rubric-eval-date-invalid';
			$post = $all_posts[$term->slug];
			if (!empty($post)) {
				$content .= '<li><span class="rubric-evaluation-term">'.$post->post_rubric_term->name.'</span>';
				$content .= '<span class="'.$date_color.' rubric-evaluation-term-due">'.(!empty($term_desc['duedate'])?$term_desc['duedate']: __('n/a', 'ctlt_rubric_evaluation')).'</span>';
				$content .= '<a class="rubric-evaluation-anchor" href="'.wp_get_shortlink($post->ID, $post->post_type).'">'.$post->post_title.'</a></li>';
			} else {
				$content .= '<li><span class="rubric-evaluation-term">'.$term->name.'</span>';
				$content .= '<span class="'.$date_color.' rubric-evaluation-term-due">'.(!empty($term_desc['duedate'])?$term_desc['duedate']: __('n/a', 'ctlt_rubric_evaluation')).'</span>';
				$content .= '&nbsp;</li>';
			}	
		}		

		return $content;
	}
	
	private function _output_ta_dashboard($content) {
		$content = "(TA) Put maybe something like teacher's stuff?";
		return $content;
	}
	
	private function _output_teacher_dashboard($content) {
		error_log('hi');
		$roles = get_option('rubric-evaluation-roles-settings');
		error_log('roles;'.print_r($roles,true));
		$terms = get_terms(RUBRIC_EVALUATION_TAXONOMY, array('hide_empty' => false));
		$blog_id = get_current_blog_id();
		$content = 'Detailed Marks can be seen on the Rubric Eval <a target="_blank" href="'.get_admin_url($blog_id, 'admin.php?page=rubric_evaluation_subpage_settings').'">'.__('Spreadsheet', 'ctlt_rubric_evaluation').'</a>.';
		
		$fields = array('ID', 'user_login', 'user_nicename', 'display_name');
		$students = get_users(array('blog_id' => $blog_id, 'role' => $roles['rubric-evaluation-roles-settings']['rubric-evaluation-roles-settings']['rubric-evaluation-role-student'], 'fields' => $fields));
		
		$content .= '<ul class="rubric-evaluation-list">';
		$content .= '<li><span class="rubric-evaluation-head rubric-evaluation-med-term">'.__('Info', 'ctlt_rubric_evaluation').'</span>';
		$content .= '<span class="rubric-evaluation-head rubric-evaluation-term-due">'.__('Stat', 'ctlt_rubric_evaluation').'</span>';
		$content .= '<span class="rubric-evaluation-head rubric-evaluation-anchor">'.__('Notes', 'ctlt_rubric_evaluation').'</span></li>';
		
		$content .= '<li><span class="rubric-evaluation-med-term">'.__('No. Students', 'ctlt_rubric_evaluation').'</span>';
		$content .= '<span class="rubric-evaluation-term-due">'.count($students).'</span>';
		$content .= '<span class="rubric-evaluation-anchor">&nbsp;</span></li>';
		
		foreach ($terms as $term) {
			$term_desc = CTLT_Rubric_Evaluation_Util::ctlt_rubric_get_term_meta($term);
			$date_color = (empty($term_desc['duedate']) || (strtotime($term_desc['duedate']) > time()))? 'rubric-eval-date-valid' : 'rubric-eval-date-invalid';
			$content .='<li><span class="rubric-evaluation-med-term">'.__('Completed: ', 'ctlt_rubric_evaluation').$term->name.'</span>';
			$content .= '<span class="rubric-evaluation-term-due">'.$term->count.'</span>';
			$content .='<span class="'.$date_color.' rubric-evaluation-ancor">'.__('Due: ', 'ctlt_rubric_evaluation').$term_desc['duedate'].'</span></li>';	
		}
		
		$content .= '</ul>';
		
		return $content;
	}
}

if( is_admin() )
	$ctlt_rubric_evaluation_dashboard_widget = new CTLT_Rubric_Evaluation_Dashboard_Widget();