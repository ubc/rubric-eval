<?php
/**
 * 
 * @author loongchan
 *
 */
class CTLT_Rubric_Evaluation_Front
{
	const RUBRIC_EVAL_INFO = 'rubric-eval-mark';
	const RUBRIC_EVAL_MAX_DROPDOWN_MARK = 10;
	
	/**
	 * Start up
	 */
	public function __construct() {
		add_filter( 'the_content', array($this, 'front_grade_box'));
		add_action('wp_ajax_rubric_eval_mark', array($this, 'rubric_eval_mark'));

		//add javascript
		wp_register_script('CTLT_Rubric_Evaluation_Front_Script', RUBRIC_EVALUATION_PLUGIN_URL.'js/ctlt_rubric_front.js', array('jquery'), false, true);
	}
	
	public function rubric_eval_mark() {		
		$post_parameters = array();
		$current_user_id = get_current_user_id();
		parse_str($_POST['data'], $post_parameters);
		$return_result = __('Mark was not saved.  Please refresh the page and try again.', 'ctlt_rubric_evaluation');
		$verify = wp_verify_nonce($post_parameters['_wpnonce'], CTLT_Rubric_Evaluation_Front::RUBRIC_EVAL_INFO.'_'.$post_parameters['post_type'].'_'.$post_parameters['term_id'].'_'.$post_parameters['post_id']);
		if ($verify) {
			$saved = $this->_save_value(get_current_user_id(), $post_parameters['post_type'], $post_parameters['post_id'], $post_parameters['term_id'], $post_parameters[CTLT_Rubric_Evaluation_Front::RUBRIC_EVAL_INFO]);
			if ($saved) {
				$return_result = __('Mark was saved!', 'ctlt_rubric_evaluation');
			}
		}
		echo $return_result;

		die();
	}

	/**
	 * @TODO:
	 * 1. check IF it should show up (is author of post or teacher AND has custom taxonomy on that post!)
	 * 2. display appropriate type of field (lable for author, input for teacher?)
	 * 3. need to think about how to intercept submission to save grade for teacher.....
	 * 
	 * NOTE: 
	 * - need to add nonce to form
	 * - need to make it ajax
	 * - currently restricted to single, not archive views
	 */
	public function front_grade_box($content) {
		global $post;
		$current_user = wp_get_current_user();
		$isLoggedin = is_user_logged_in();
		$terms = wp_get_post_terms($post->ID, array('ctlt_rubric_evaluation'));
		
		$display = '';
		
		//check if we even should display something
		if (!is_wp_error($terms) && !empty($terms) && $isLoggedin && is_singular() && count($terms) == 1) {
			wp_enqueue_script('CTLT_Rubric_Evaluation_Front_Script');
			
			$term = reset($terms);
			$value = CTLT_Rubric_Evaluation_Front::get_rubric_evaluation_mark(get_post_type($post), $post->ID, $term->term_id); //need to pull from DB's table
			$value = (is_null($value) ? 0 : esc_attr($value->mark));
		
			$user = CTLT_Rubric_Evaluation_Util::ctlt_rubric_get_user_role();
			$roles = get_option('rubric-evaluation-roles-settings');
			$teacher = $roles['rubric-evaluation-roles-settings']['rubric-evaluation-roles-settings']['rubric-evaluation-role-teacher'];
			$student = $roles['rubric-evaluation-roles-settings']['rubric-evaluation-roles-settings']['rubric-evaluation-role-student'];
			$ta = $roles['rubric-evaluation-roles-settings']['rubric-evaluation-roles-settings']['rubric-evaluation-role-ta'];
			
			if ((isset($teacher) && ( $user == $teacher )) || (isset($ta) && ( $user == $ta ))) {
				//we need to determine what format the input takes
				$type_option = get_option('rubric-evaluation-roles-settings');
				$type = $type_option['rubric-evaluation-roles-settings']['rubric-evaluation-grading-type']['rubric-evaluation-grading-type'];
				$display .= '<form method="post" id="rubric-eval-form" action=""><label for="'.CTLT_Rubric_Evaluation_Front::RUBRIC_EVAL_INFO.'">'.__('Mark', 'ctlt_rubric_evaluation').'</label>';
				
				if (strcasecmp('text', $type) == 0) {
					$display .= $this->_output_text();
				} else if (strcasecmp('dropdown', $type) == 0) {
					$display .= $this->_output_dropdown();
				} else {
					$display .= __('Please set the Grading Type again', 'ctlt_rubric_evaluation');
				}
				
				$display .= '<input class="btn" id="rubric-eval-mark-submit" type="submit" value="'.__('Submit', 'ctlt_rubric_evaluation').'">';
				$display .= '</form>';
			} elseif (isset($student) && ( $user == $student ) && $current_user->ID == $post->post_author) {				
				$display .= '<div class="rubric-eval-show-mark">';
				$display .= __( 'Mark: ', 'ctlt_rubric_evaluation').$value;
				$display .= '</div>';
			}
		}

		return $content.$display;
	}
	
	//======================================================================
	//
	// Sanitization callback functions
	//
	//======================================================================
	public static function get_rubric_evaluation_mark($object_type, $object_id, $term_id, $user_id = 0) {
		global $wpdb;
		
		if (empty($object_type) || empty($object_id) || empty($term_id)) {
			return '';
		}
		
// 		error_log('term_id'.print_r($term_id,true));error_log('object_type: '.print_r($object_type,true));error_log('object_id: '.print_r($object_id,true));error_log('user_id:'.print_r($user_id,true));
		
		$table_name = $wpdb->prefix . RUBRIC_EVALUATION_MARK_TABLE_SUFFIX;
		if ($user_id == 0) {
			$query = $wpdb->prepare( "SELECT * FROM $table_name WHERE object_type = %s AND object_id = %d AND term_id = %d",
					$object_type,
					$object_id,
					$term_id
			);
		} else {
			$query = $wpdb->prepare( "SELECT * FROM $table_name WHERE object_type = %s AND object_id = %d AND user_id = %d AND term_id = %d",
					$object_type,
					$object_id,
					$user_id,
					$term_id
			);
		}

		$result = $wpdb->get_row( $query );
		return $result;
	}
	
	//======================================================================
	//
	// Private callback functions
	//
	//======================================================================
	/**
	 * @TODO need to account for if grade was set!
	 */
	private function _output_dropdown() {
		global $post;
		$terms = wp_get_post_terms($post->ID, array('ctlt_rubric_evaluation'));
		$term = reset($terms);
		$default = CTLT_Rubric_Evaluation_Front::get_rubric_evaluation_mark(get_post_type($post), $post->ID, $term->term_id); //need to pull from DB's table
		$default = (is_null($default) ? 0 : esc_attr($default->mark));

		$display = '';
		$display .='<select class="rubric-evaluation-select" id="'.CTLT_Rubric_Evaluation_Front::RUBRIC_EVAL_INFO.'" name="'.CTLT_Rubric_Evaluation_Front::RUBRIC_EVAL_INFO.'">';
		foreach (array_combine(range(0, CTLT_Rubric_Evaluation_Front::RUBRIC_EVAL_MAX_DROPDOWN_MARK), range(0, CTLT_Rubric_Evaluation_Front::RUBRIC_EVAL_MAX_DROPDOWN_MARK)) as $key => $val) {
			if ($default == $key) {
				$display .= "<option value='$key' selected='selected'>$val</option>\n";
			} else {
				$display .= "<option value='$key'>$val</option>\n";
			}
		}
		$display .= '</select>';
		$display .= '<input type="hidden" name="post_id" value="'.$post->ID.'">';
		$display .= '<input type="hidden" name="post_type" value="'.get_post_type($post).'">';
		$display .= '<input type="hidden" name="term_id" value="'.$term->term_id.'">';
		$display .= wp_nonce_field(CTLT_Rubric_Evaluation_Front::RUBRIC_EVAL_INFO.'_'.get_post_type($post).'_'.$term->term_id.'_'.$post->ID);
		return $display;
	}
	
	private function _output_text() {
		global $post;
		$terms = wp_get_post_terms($post->ID, array('ctlt_rubric_evaluation'));
		$term = reset($terms);
		$value = CTLT_Rubric_Evaluation_Front::get_rubric_evaluation_mark(get_post_type($post), $post->ID, $term->term_id); //need to pull from DB's table
		$value = (is_null($value) ? 0 : esc_attr($value->mark));
		
		$display = '';
		$display .= '<input type="text" id="'.CTLT_Rubric_Evaluation_Front::RUBRIC_EVAL_INFO.'" name="'.CTLT_Rubric_Evaluation_Front::RUBRIC_EVAL_INFO.'" value="'.$value.'">';
		$display .= '<input type="hidden" name="post_id" value="'.$post->ID.'">';
		$display .= '<input type="hidden" name="post_type" value="'.get_post_type($post).'">';
		$display .= '<input type="hidden" name="term_id" value="'.$term->term_id.'">';
		$display .= wp_nonce_field(CTLT_Rubric_Evaluation_Front::RUBRIC_EVAL_INFO.'_'.get_post_type($post).'_'.$term->term_id.'_'.$post->ID);
		
		return $display;
	}
	
	private function _save_value($user_id, $object_type, $object_id, $term_id, $mark, $deleted = 0, $created = false, $modified = false) {
		global $wpdb;
		
		if (empty($user_id) || empty($object_type) || empty($object_id) || empty($term_id) || empty($mark)) {
			return false;
		}
		
		$table_name = $wpdb->prefix . RUBRIC_EVALUATION_MARK_TABLE_SUFFIX;
		
		//need to see if update or insert!
		$exists = CTLT_Rubric_Evaluation_Front::get_rubric_evaluation_mark($object_type, $object_id, $term_id, $user_id);
		$result = false;
		if (is_null($exists)) {
			//insert!
			$data = array(
					'user_id'		=> intval($user_id),
					'object_type'	=> $object_type,
					'object_id'		=> intval($object_id),
					'term_id'		=> intval($term_id),
					'mark'			=> $mark,
					'deleted'		=> 0,
					'created'		=> current_time('mysql'),
					'modified'		=> current_time('mysql')
			);
 			$result = $wpdb->insert( $table_name, $data, array( '%d', '%s', '%d', '%d', '%s', '%d', '%s', '%s' ) );
		} else {
			//update!
			$data = get_object_vars($exists);
			$where = array('id' => $data['id']);
			$data['mark'] = $mark;
			$data['modified'] = current_time('mysql');
			$result = $wpdb->update($table_name, $data, $where);
		}
		return $result;
	}
	
}
$ctlt_rubric_evaluation_front = new CTLT_Rubric_Evaluation_Front();