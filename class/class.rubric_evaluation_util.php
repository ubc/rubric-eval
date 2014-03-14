<?php 
class CTLT_Rubric_Evaluation_Util {
	
	/**
	 * Returns the user's role based on user_id
	 * 
	 * @param string $user_id
	 * @return string 
	 */
	public static function ctlt_rubric_get_user_role($user_id = null) {
		if (is_null($user_id)) {
			$user_id = get_current_user_id();
			if (empty($user_id)) {
				return false;
			}
		}

		$user = new WP_User(get_current_user_id($user_id));
		$user_role = $user->roles;

		if (is_super_admin()) {
			return 'administrator';
		}
		
		return reset($user_role);
	}
	
	/**
	 * Gets the mark
	 * 
	 * @param unknown $object_type
	 * @param unknown $object_id
	 * @param unknown $term_id
	 * @param number $user_id
	 * @return string|Ambigous <mixed, NULL, multitype:>
	 */
	public static function ctlt_rubric_get_mark($object_type, $object_id, $term_id, $user_id = 0) {
		global $wpdb;
	
		if (empty($object_type) || empty($object_id) || empty($term_id)) {
			return '';
		}
	
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
	
	/**
	 * Converts role to capabilities for this plugin
	 * 
	 * @param string $role
	 * @return if true, string.  if an error is produced, returns false
	 * @NOTE: need to manually modify for custom roles and permissions...
	 */
	public static function ctlt_rubric_get_capability_for_role($role = 'author') {
		$valid_roles = array('subscriber', 'contributer', 'author', 'editor', 'section_editor', 'administrator');
		$return_value = false;
		if (!empty($role) && in_array($role, $valid_roles)) {
			switch ($role) {
				case 'administrator':
					$return_value = 'activate_plugin';
					break;
				case 'section_editor':
				case 'editor':
					$return_value = 'moderate_comments';
					break;
				case 'author':
					$return_value = 'edit_published_posts';
					break;
				case 'contributer':
					$return_value = 'edit_posts';
					break;
				case 'subscriber':
					$return_value = 'read';
					break;
				default:
					$return_value = 'read';
			}
		}

		return $return_value;
	}
	
	/**
	 * Retrieves the terms for a speciric post type
	 * 
	 * @param string $post_type
	 * @return multitype:Ambigous <multitype:, WP_Error, mixed, string, NULL>
	 */
	public static function ctlt_rubric_get_terms_for($post_type = 'post') {
		$terms = get_terms(array(RUBRIC_EVALUATION_TAXONOMY), array('hide_empty' => false));
		$return_value = array();
		foreach ($terms as $term) {
			$deserialized_info = unserialize(base64_decode($term->description));
			if ($deserialized_info['posttype'] == $post_type) {
				$return_value[] = $term;
			}
		}
		return $return_value;
	}
	
	/**
	 * converts description encoded term description into useful array
	 * 
	 * @param unknown Object|string
	 * @return array
	 */
	public static function ctlt_rubric_get_term_meta($term) {
		if (is_string($term)) {
			$deserialize_info = unserialize(base64_decode($term));
			return $deserialize_info;
		} else if (is_object($term)) {
			$deserialize_info = unserialize(base64_decode($term->description));
			return $deserialize_info;
		}
	}
	
	/**
	 * Setup rubric eval DB Table
	 * 
	 * @return void
	 */
	public static function ctlt_rubric_activate() {
		global $wpdb;
		require_once( ABSPATH.'wp-admin/includes/upgrade.php' );
		 
		$table_name = $wpdb->prefix . RUBRIC_EVALUATION_MARK_TABLE_SUFFIX;
		$sql = "CREATE TABLE $table_name (
		id bigint(11) NOT NULL AUTO_INCREMENT,
		user_id bigint(11) NOT NULL,
		object_type varchar(40) NOT NULL,
		object_id bigint(11) NOT NULL,
		term_id bigint(11) NOT NULL,
		mark varchar(40) NOT NULL,
		deleted tinyint(1) NOT NULL DEFAULT '0',
		created datetime,
		modified datetime,
		PRIMARY KEY  (id) );";
		 
		dbDelta( $sql );
		add_option( 'rubric_evaluation_db_version', CTLT_Rubric_Evaluation_Admin::DB_VERSION );
	}	
	
	/**
	 * Gets the posts based on parameters passed in
	 * 
	 * @param string $post_type
	 * @param string $author
	 * @param boolean $hide_empty
	 * @return array of post objects or empty array
	 */
	public static function ctlt_rubric_get_posts($post_type = 'post', $author = null, $hide_empty = false) {
		if (is_array($post_type)) {
			extract(array_merge(array(
				'post_type' => 'post',
				'author' => '',
				'hide_empty' => false), $post_type));
		}
			
		$all_posts = array();
		$terms = get_terms(RUBRIC_EVALUATION_TAXONOMY, array('hide_empty' => $hide_empty));
		foreach ($terms as $term) {
			$args = array(
					'tax_query' => array(
							array(
	    					'taxonomy' => RUBRIC_EVALUATION_TAXONOMY,
	    					'field' => 'slug',
	    					'terms' => array($term->slug)
	    				)
    				)
			);
			
			//add author attribute if appropriate
			if (!empty($author)) {
				$args['author'] = $author;
			}
			if (!empty($post_type)) {
				$post_type_array = explode(',', $post_type);
				$args['post_type'] = $post_type_array;
			}

			$posts = get_posts($args);
			foreach ($posts as $post) {
				$post->post_rubric_term = $term;	//added term to post object
				$all_posts[$term->slug] = $post;	//split posts by term->slug
			}
				
			//accounts for terms without associated objects
			if (!isset($all_posts[$term->slug])) {
				$all_posts[$term->slug] = array();
			}
		}
		return $all_posts;
	}
	
	/**
	 * Create HTML Options (no select tag though)
	 * @param unknown $arr
	 * @param string $default
	 * @return string
	 */
	public static function ctlt_rubric_create_html_options($arr, $default = '') {
		if (!is_array($arr)) {
			$arr = array($arr);
		}
		
		$return_value = '';
		foreach ($arr as $key => $val) {
			if ($default == $key) {
				$return_value .= "<option value='$key' selected='selected'>$val</option>\n";
			} else {
				$return_value .= "<option value='$key'>$val</option>\n";
			}
		}
		return $return_value;
	}
}