<?php
class CTLT_Rubric_Evaluation_Lists
{
	private $roles;
    /**
     * Start up
     */
    public function __construct() {
    	//setup roles
    	$this->roles = get_option('rubric_evaluation_roles_settings');
    	
    	//setup metaboxes
    	add_action( 'admin_init', array( $this, 'page_init' ) );
    	
        //filter posts....
        //@TODO: need to make it more flexible to not just posts, but pages, etc
		add_action('restrict_manage_posts', array($this, 'add_rubric_dropdown'));
		add_filter('posts_where', array( $this, 'modify_posts_bulk_action'));
		add_filter('manage_posts_columns', array($this, 'add_rubric_column_head'));
		add_filter('manage_pages_columns', array($this, 'add_rubric_page_column_head'));
		add_action('manage_posts_custom_column', array($this, 'add_rubric_column_value'), 10, 2 );
		add_action('manage_pages_custom_column', array($this, 'add_rubric_page_column_value'), 10, 2);
		add_action('quick_edit_custom_box', array($this, 'rubric_quick_edit'), 10, 2);

        //save posts/page
        add_action( 'save_post', array($this, 'save_rubric_evaluation'), 10, 3);
        
        wp_register_script('CTLT_Rubric_Edit_Css', RUBRIC_EVALUATION_PLUGIN_URL.'css/ctlt_rubric_edit.css');
		wp_register_script('CTLT_Rubric_Evaluation_Lists_Script', RUBRIC_EVALUATION_PLUGIN_URL.'js/ctlt_rubric_lists.js', array('jquery'), false, true);    
		wp_register_script('CTLT_Rubric_Evaluation_Edit_Script', RUBRIC_EVALUATION_PLUGIN_URL.'js/ctlt_rubric_edit.js', array('jquery'), false, true);
    }
    
    /**
     * Register and add settings
     */
    public function page_init() {
    	//add metabox IF there are things to mark!
    	$postterms = CTLT_Rubric_Evaluation_Util::ctlt_rubric_get_terms_for('post');
    	if (!empty($postterms)) {
   			add_meta_box('rubric_evaluation_post', __('Rubric Evaluation', 'ctlt_rubric_evaluation'), array($this, 'setup_metabox'), 'post', 'side', 'high');
    	}
   		$pageterms = CTLT_Rubric_Evaluation_Util::ctlt_rubric_get_terms_for('page');
		if (!empty($pageterms)) {
			add_meta_box('rubric_evaluation_page', __('Rubric Evaluation', 'ctlt_rubric_evaluation'), array($this, 'setup_metabox'), 'page', 'side', 'high');
		}
    }
    
    public function add_rubric_dropdown() {
    	global $post;
    
    	//only add the dropdown if there is any terms involved!
    	$taxterms = CTLT_Rubric_Evaluation_Util::ctlt_rubric_get_terms_for($post->post_type);
    	if (count($taxterms)) {
    		$array_key = array('0');
    		$array_value = array(__('View all rubric eval', 'ctlt_rubric_evaluation'));
    		foreach ($taxterms as $taxterm) {
    			$array_key[] = $taxterm->term_id;
    			$array_value[] = $taxterm->name;
    		}
    			
    		echo '<select name="rubric_eval" id="rubric_eval" class="postform">';
    		echo CTLT_Rubric_Evaluation_Util::ctlt_rubric_create_html_options(array_combine($array_key, $array_value), reset($array_key));
    		echo '</select>';
    	}
    }
    
    /**
     * function to modify list posts query
     *
     * @props http://codex.wordpress.org/Plugin_API/Filter_Reference/posts_where
     * @param unknown $where
     * @return string
     */
    public function modify_posts_bulk_action( $where ) {
    	$rubric_eval_id = intval($_GET['rubric_eval']);
    	//@TODO: need to make it so teachers and tas can see this???
    	if ( is_admin()) {
    		if ( isset( $_GET['rubric_eval'] ) && !empty( $_GET['rubric_eval'] ) && intval( $_GET['rubric_eval'] ) != 0 ) {
    			global $wpdb;
    			$term_id = intval( $_GET['rubric_eval'] );
    			$where .= " AND ID IN (SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id=$term_id )";
    		}
    	}
    	return $where;
    }
    
    
	/**
	 * Adds column header for list all posts pages
	 * 
	 * @param unknown $defaults
	 * @return array
	 * @TODO: need to unhardcode!  @see add_rubric_page_column_head()
	 */
	public function add_rubric_column_head($defaults) {
		$tax = get_taxonomies(array('name' => RUBRIC_EVALUATION_TAXONOMY));
		$taxterms = get_terms($tax, array('hide_empty' => false), 'names', 'and');
		$new_defaults = array();
		if (count($taxterms) && !(isset($_GET['post_type']) && $_GET['post_type'] == 'page')) {
			$valid_keys = array_keys($defaults);
			
			if (in_array('tags', $valid_keys)) {
				foreach ($defaults as $column_key => $column_name) {
					$new_defaults[$column_key] = $column_name;
					if ($column_key == 'tags') {
						$new_defaults[RUBRIC_EVALUATION_COLUMN_KEY] = __('Rubric Evaluation', 'ctlt_rubric_evaluation');
					}
				}
			} else { 
				$defaults[RUBRIC_EVALUATION_COLUMN_KEY] = __('Rubric Evaluation', 'ctlt_rubric_evaluation');
				$new_defaults = $defaults;
			}
		} else {
			$new_defaults = $defaults;
		}	
		return $new_defaults;
	}
	
	/**
	 * Adds column header for list all posts pages
	 *
	 * @param unknown $defaults
	 * @return array
	 * @TODO: need to unhardcodd! @see add_rubric_column_head()
	 */
	public function add_rubric_page_column_head($defaults) {
		$tax = get_taxonomies(array('name' => RUBRIC_EVALUATION_TAXONOMY));
		$taxterms = CTLT_Rubric_Evaluation_Util::ctlt_rubric_get_terms_for('page');
		$new_defaults = array();
		if (count($taxterms) && (isset($_REQUEST['post_type']) && $_REQUEST['post_type'] == 'page')) {
			$valid_keys = array_keys($defaults);
			if (in_array('author', $valid_keys)) {
				foreach ($defaults as $column_key => $column_name) {
					$new_defaults[$column_key] = $column_name;
					if ($column_key == 'author') {
						$new_defaults[RUBRIC_EVALUATION_COLUMN_KEY] = __('Rubric Evaluation', 'ctlt_rubric_evaluation');
					}
				}
			} else {
				$defaults[RUBRIC_EVALUATION_COLUMN_KEY] = __('Rubric Evaluation', 'ctlt_rubric_evaluation');
				$new_defaults = $defaults;
			}
		} else {
			$new_defaults = $defaults;
		}
		return $new_defaults;
	}
	
	/**
	 * Adds value of each row for lists posts page
	 * 
	 * @param string $column_name
	 * @param int $post_id
	 */
	public function add_rubric_column_value( $column_name, $post_id ) {
		if ($column_name == RUBRIC_EVALUATION_COLUMN_KEY) {
			$terms = wp_get_post_terms($post_id, RUBRIC_EVALUATION_TAXONOMY);
			$term = array();
			if (!empty($terms)) {
				foreach ($terms as $term_unit) {
					$term[] = $term_unit->name; 
				}
				$term_val = reset($terms);
				$hidden = '<input type="hidden" class="rubric-eval-mark-value" value="'.$term_val->term_id.'">';
				echo $hidden.implode(", ", $term);
			}
		}
	}

	/**
	 * Adds value of each row for lists page page
	 *
	 * @param string $column_name
	 * @param int $post_id
	 */
	public function add_rubric_page_column_value( $column_name, $post_id ) {
		if ($column_name == RUBRIC_EVALUATION_COLUMN_KEY) {
			$terms = wp_get_object_terms($post_id, RUBRIC_EVALUATION_TAXONOMY);			
			$term = array();
			if (!empty($terms)) {
				foreach ($terms as $term_unit) {
					$term[] = $term_unit->name;
				}
				$term_val = reset($terms);
				$hidden = '<input type="hidden" class="rubric-eval-mark-value" value="'.$term_val->term_id.'">';
				echo $hidden.implode(", ", $term);
			}
		}
	}
	
	/**
	 * TODO: for page list, for some reason, after I quicksave, rubric evaluation column value disappears?!?!?!?!?!
	 * @param unknown $column_name
	 * @param unknown $post_type
	 */
	public function rubric_quick_edit($column_name, $post_type) {
		global $post;
		
		if ($column_name != RUBRIC_EVALUATION_COLUMN_KEY) {
			return;
		}

		$user_role = CTLT_Rubric_Evaluation_Util::ctlt_rubric_get_user_role(get_current_user_id());
		if ($user_role == $this->roles['rubric-evaluation-roles-settings']['rubric-evaluation-roles-settings']['rubric-evaluation-role-student']) {
			return;
		}
		
		wp_enqueue_script('CTLT_Rubric_Evaluation_Lists_Script');

		if ($column_name == 'rubric_eval_column') {
			$terms = CTLT_Rubric_Evaluation_Util::ctlt_rubric_get_terms_for($post_type);
			?>
			<fieldset class="inline-edit-col-right">
		      <div class="inline-edit-col column-<?php echo $column_name ?>">
		        <label class="inline-edit-group">
		        	<span class="title"><?php _e('Rubric Evaluation Mark', 'ctlt_rubric_evalution');?></span>
		        	<select class="rubric-evaluation-select" name="rubric-eval-info">
		        		<option value="0">&nbsp;</option>
		        	<?php
	        		foreach ($terms as $term) {
					?>
						<option value="<?php echo $term->term_id;?>"><?php echo $term->name; ?></option>
					<?php 
					} 
		        	?>
		        	</select>
		        </label>
		      </div>
		    </fieldset>
			<?php 
		}
	}
	
	public function setup_metabox() {
		global $post;
		/**
		 * 1 get all terms for post type
		 * 2 find out which ones are for posts
		 * 3 add radio button
		 * 4 make it work with javascript to make it toggleable
		 * 5 need to check if past duedate for that term, if so, then dont' show it
		 */
		$terms = CTLT_Rubric_Evaluation_Util::ctlt_rubric_get_terms_for($post->post_type);
		$tax = get_taxonomies(array('name' => RUBRIC_EVALUATION_TAXONOMY));	
		$post_terms_raw = get_the_terms($post, $tax);
		$post_terms = array();
		
		wp_enqueue_script('CTLT_Rubric_Edit_Css');
		
		if (!empty($post_terms_raw)) {	
			$post_terms = reset(get_the_terms($post, $tax));
		}

		wp_enqueue_script('CTLT_Rubric_Evaluation_Edit_Script');
		
		foreach ($terms as $term) {
			//determined whether we should have what checked....
			$checked = '';
			if (!empty($post_terms) && $term->term_id == $post_terms->term_id) {
				$checked = 'checked="checked"';
			}
			
			//takes into consideration duedate if set
			$term_description = CTLT_Rubric_Evaluation_Util::ctlt_rubric_get_term_meta($term);
			if (isset($term_description['duedate']) && !empty($term_description['duedate'])) {
				if (strtotime($term_description['duedate']) > time()) {
					echo '<label class="rubric-evaluation-radio-label">';
					echo '<input '.$checked.' class="rubric-evaluation-radio" type="radio" name="rubric-eval-info" value="'.$term->term_id.'">';
					echo $term->name;
				} else {
					echo '<label class="rubric-evaluation-radio-label past_due">';					
					echo '<div class="dashicons dashicons-dismiss"></div>';
					echo $term->name . __('(Past Due Date)', 'ctlt_rubric_evaluation');
				}
			} else {
				echo '<label class="rubric-evaluation-radio-label">';
				echo '<input '.$checked.' class="rubric-evaluation-radio" type="radio" name="rubric-eval-info" value="'.$term->term_id.'">';
				echo $term->name;
			}


			echo '</label><br>';
		}
	}

    public function save_rubric_evaluation( $post_id, $post, $update ) {

		// don't save for autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}
		
		// dont save for revisions
		if ( isset( $post->post_type ) && $post->post_type == 'revision' ) {
			return $post_id;
		}

		$taxterms = CTLT_Rubric_Evaluation_Util::ctlt_rubric_get_terms_for($post->post_type);
    	if (isset($_POST['rubric-eval-info']) && !empty($_POST['rubric-eval-info']) ) {
			$term_slug = '';
			foreach ($taxterms as $term) {
				if ($term->term_id == $_POST['rubric-eval-info']) {
					$term_slug = $term->slug;
					break;
				}
			}

			if (!empty($term_slug)) {
				wp_set_object_terms($post_id, $term_slug, RUBRIC_EVALUATION_TAXONOMY);
			}
			
		} else {	//since it is NOT set, we need to remove it (check for date for due date????)
			//need to figure out whether from quicksave or normal edit/create page/post
			$screen = get_current_screen();
			if (!empty($screen) && $screen->base == 'post') {

				$terms = wp_get_object_terms($post_id, RUBRIC_EVALUATION_TAXONOMY);
	
				if (!empty($terms)) {
					$term = reset($terms);
					wp_remove_object_terms($post_id, $term->slug, RUBRIC_EVALUATION_TAXONOMY);
				}
			}
		}
    }
}

//I don't wrap around via is_admin because need to register taxonomy????
if (is_admin()) {
	$ctlt_rubric_evaluation_lists = new CTLT_Rubric_Evaluation_Lists();
}