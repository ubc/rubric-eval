<?php
/**
 * started first with: http://codex.wordpress.org/Creating_Options_Pages
 * help with multi-section on one page with: http://www.mendoweb.be/blog/wordpress-settings-api-multiple-sections-on-same-page/
 * create tabs with: http://wp.tutsplus.com/tutorials/theme-development/the-complete-guide-to-the-wordpress-settings-api-part-5-tabbed-navigation-for-your-settings-page/
 * @author loongchan
 * 
 * TODO:
 * - link to spreadsheet via anchor for type column names
 * - need to display grades
 * - need a way to enter grades
 * - need a way to edit taxonomy on edit/create post page into radio button
 * - think about forcing usage of evaluate plugin
 * - deal with mu stuff.....
 * - deal with language stuff.....
 * - add datepicker for due date / extended date
 * - hide student weight for now?
 * - need to customize dashboard for various roles.... including spreadsheet! 
 * - need to uninstall DB in uninstall.php
 * - detach taxonomy with everything else and add custom metabox to page/post/etc manually
 *
 * PARTIALLY DONE:
 * - do uninstall or disable plugin functions - partially done, need to deal with page
 * - need to add filter for posts/page list pages - done, but just for posts 
 * 
 * DONE:
 * - check that taxonomy is removed as per removal of rows! - done!
 * - start fleshing out spreadsheet - just the look, need to add data
 * - need to setup DB - done
 *
 */
class CTLT_Rubric_Evaluation_Admin
{
    /**
     * Holds the values to be used in the fields callbacks
     */
	const DB_VERSION = "1.0";
	const MAX_ROWS = 20;
	const MAX_GRADES_DROPPED = 5;
    private $options;
    private $active_tab;
    private $rubric_headers = array();
    		

    /**
     * Start up
     */
    public function __construct() {
    	//setup rubric_headers
    	$this->rubric_headers = array(
    		__('Student Weight', 'ctlt_rubric_evaluation'),
    		__('Instructor Weight', 'ctlt_rubric_evaluation'),
    		__('Total', 'ctlt_rubric_evaluation')
    	);
        // Set class property
        $this->setup_options();
        $this->active_tab = (isset($_GET['tab']) && $_GET['tab'] === 'display_advanced'? $_GET['tab'] : 'display_rubric');
        //setup action hooks
        add_action( 'admin_menu', array( $this, 'rubric_evaluation_menu' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
        add_action( 'init', array( $this, 'create_taxonomy' ) );
		add_filter('posts_where', array( $this, 'modify_posts_bulk_action'));

        //filter posts....
        add_action('restrict_manage_posts', array($this, 'add_rubric_dropdown'));
        
        //register scripts
        wp_register_script('CTLT_Rubric_Evaluation_Script', RUBRIC_EVALUATION_PLUGIN_URL.'js/ctlt_rubric_evaluation.js', array('jquery'));
        wp_register_style('CTLT_Rubric_Evaluation_Css', RUBRIC_EVALUATION_PLUGIN_URL.'css/ctlt_rubric_evaluation.css');
        wp_enqueue_style('CTLT_Rubric_Evaluation_Css');
    }
    
    /**
     * Add rubric_evaluation settings page
     */
    public function rubric_evaluation_menu() {
    	$user = CTLT_Rubric_Evaluation_Util::ctlt_rubric_get_user_role();
    	$teacher = $this->options['rubric_evaluation_roles_settings']['rubric_evaluation_role_teacher'];
    	$student = $this->options['rubric_evaluation_roles_settings']['rubric_evaluation_role_student'];
    	$ta = $this->options['rubric_evaluation_roles_settings']['rubric_evaluation_role_ta'];
    	
    	if ((isset($teacher) && ( $user == $teacher )) || (isset($ta) && ( $user == $ta ))) {
    		//add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position );
    		add_menu_page(
	    		_x('Rubric Evaluation', 'page title', 'ctlt_rubric_evaluation'), //page title
	    		_x('Rubric Eval', 'menu title', 'ctlt_rubric_evaluation'), //menu title
	    		CTLT_Rubric_Evaluation_Util::ctlt_rubric_get_capability_for_role($user), //capability
	    		'rubric_evaluation_settings', //slug
	    		array( $this, 'create_rubric_evaluation_settings_page'), //output callback
	    		'dashicons-book' //icon
    		);
    	} elseif (isset($student) && ( $user == $student )) {
			// do nothing???
			//we don't want to show anything becuase students cannot do things!
    	}
    }

    public function setup_options() {
    	$rubric_evaluation_rubric_name = get_option('rubric_evaluation_rubric_name');
    	if ($rubric_evaluation_rubric_name !== false) {
    		$this->options[reset(array_keys($rubric_evaluation_rubric_name))] = $rubric_evaluation_rubric_name[reset(array_keys($rubric_evaluation_rubric_name))];
    	} else {
    		$this->options['rubric_evaluation_rubric_name'] = array();
    	}
    	
    	$rubric_evaluation_roles_settings = get_option('rubric_evaluation_roles_settings');
    	if ($rubric_evaluation_roles_settings !== false && !empty($rubric_evaluation_roles_settings)) {
			$this->options[reset(array_keys($rubric_evaluation_roles_settings))] = $rubric_evaluation_roles_settings[reset(array_keys($rubric_evaluation_roles_settings))];
    	} else {
    		//set to default!  Should only be done when first installed
    		$this->options['rubric_evaluation_roles_settings'] = array('rubric_evaluation_role_settings' => array(
    			'rubric_evaluation_role_ta' => 'editor',
    			'rubric_evaluation_role_student' => 'author',
    			'rubric_evaluation_role_teacher' => 'administrator',
    		));
    		add_option('rubric_evaluation_roles_settings', $this->options['rubric_evaluation_roles_settings']);
    	}
    }

    /**
     * Register and add settings
     */
    public function page_init() {
		$this->setup_rubric_section();
		$this->setup_roles_section();
// 		$tax = get_taxonomies(array('name' => CTLT_Rubric_Evaluation_Admin::TAXONOMY_NAME));
// 		$taxterms = get_terms($tax, array('hide_empty' => false), 'names', 'and');
// 		error_log('tax: '.print_r($tax,true));
// 		error_log('taxterms: '.print_r($taxterms,true));
    }
    
    public function setup_rubric_section() {
    	//===============
    	//Rubric section
    	//===============
    	add_settings_section(
	    	'rubric_evaluation_section_rubric', // ID
	    	__('Rubric Settings', 'ctlt_rubric_evaluation'), // Title
	    	array( $this, 'print_section_info' ), // Callback
	    	'rubric_evaluation_settings_rubric' // Page
    	);
    	
    	add_settings_field(
	    	'rubric_evaluation_rubric_values', // ID
	    	__('Rubric', 'ctlt_rubric_evaluation'), // Title
	    	array( $this, 'output_rubric' ), // Callback
	    	'rubric_evaluation_settings_rubric', // Page
	    	'rubric_evaluation_section_rubric' // Section
    	);

    	//======================
    	//Grading Group section
    	//======================
    	add_settings_section(
    		'rubric_evaluation_grading_group', 
    		__('Grading Group', 'ctlt_rubric_evaluation'), 
    		array($this, 'print_section_info'), 
    		'rubric_evaluation_settings_rubric'
    	);
    	
    	add_settings_field(
    		'rubric_evaluation_grading_group_field', 
    		__('Add Grading Group', 'ctlt_rubric_evaluation'), 
    		array($this, 'output_grading_group'), 
    		'rubric_evaluation_settings_rubric', 
    		'rubric_evaluation_section_rubric'
    	);

    	register_setting(
	    	'rubric_evaluation_rubric', // Option group
	    	'rubric_evaluation_rubric_name', // Option name
	    	array( $this, 'sanitize_rubric' ) // Sanitize
    	);
    }
    
    public function setup_roles_section() {
		//get initial roles for various roles
    	$ta = (isset($this->options['rubric_evaluation_roles_settings']['rubric_evaluation_roles_settings']['rubric_evaluation_role_ta']) ? 
	    	$this->options['rubric_evaluation_roles_settings']['rubric_evaluation_roles_settings']['rubric_evaluation_role_ta'] :
	    	'editor');
    	$student = (isset($this->options['rubric_evaluation_roles_settings']['rubric_evaluation_roles_settings']['rubric_evaluation_role_student']) ? 
	    	$this->options['rubric_evaluation_roles_settings']['rubric_evaluation_roles_settings']['rubric_evaluation_role_student'] :
	    	'author');
    	$teacher = (isset($this->options['rubric_evaluation_roles_settings']['rubric_evaluation_roles_settings']['rubric_evaluation_role_teacher']) ? 
	    	$this->options['rubric_evaluation_roles_settings']['rubric_evaluation_roles_settings']['rubric_evaluation_role_teacher'] :
	    	'administrator');
    	
    	//settings API display fields
    	add_settings_section(
	    	'rubric_evaluation_section_roles',
	    	__('Role Assignment', 'ctlt_rubric_evaluation'),
	    	array( $this, 'print_section_info'),
	    	'rubric_evaluation_settings_role'
    	);
    	
    	add_settings_field(
	    	'rubric_evaluation_role_teacher', // ID
	    	__("Teacher's Role", 'ctlt_rubric_evaluation'), // Title
	    	array( $this, 'output_roles'),
	    	'rubric_evaluation_settings_role', // Page
	    	'rubric_evaluation_section_roles', // Section
	    	array('rubric_evaluation_role_teacher', $this->_get_role('rubric_evaluation_role_teacher', $teacher), 'disabled')
    	);
    	
    	add_settings_field(
	    	'rubric_evaluation_role_ta', // ID
	    	__("TA's Role", 'ctlt_rubric_evaluation'), // Title
	    	array( $this, 'output_roles'),
	    	'rubric_evaluation_settings_role', // Page
	    	'rubric_evaluation_section_roles', // Section
	    	array('rubric_evaluation_role_ta', $this->_get_role('rubric_evaluation_role_ta', $ta))
    	);
    	
    	add_settings_field(
	    	'rubric_evaluation_role_student', // ID
	    	__("Student's Role", 'ctlt_rubric_evaluation'), // Title
	    	array( $this, 'output_roles'),
	    	'rubric_evaluation_settings_role', // Page
	    	'rubric_evaluation_section_roles', // Section
	    	array('rubric_evaluation_role_student', $this->_get_role('rubric_evaluation_role_student', $student))
    	);
    	 
    	register_setting(
	    	'rubric_evaluation_roles', // Option group
	    	'rubric_evaluation_roles_settings', // Option name
	    	array( $this, 'sanitize_roles' ) // Sanitize
    	);
    }
    
    public function create_taxonomy() {
    	//register custom taxonomy if not set
    	if (!taxonomy_exists(RUBRIC_EVALUATION_TAXONOMY)) {
    		// Add new taxonomy, NOT hierarchical (like tags)
    		$labels = array(
    				'name'                       => _x( 'CTLT rubric_evaluation', 'Taxonomy General Name', 'ctlt_rubric_evaluation' ),
    				'singular_name'              => _x( 'CTLT rubric_evaluation', 'Taxonomy Singular Name', 'ctlt_rubric_evaluation' ),
    				'menu_name'                  => __( 'rubric_evaluation', 'ctlt_rubric_evaluation' ),
    				'all_items'                  => __( 'All Items', 'ctlt_rubric_evaluation' ),
    				'parent_item'                => __( 'Parent Item', 'ctlt_rubric_evaluation' ),
    				'parent_item_colon'          => __( 'Parent Item:', 'ctlt_rubric_evaluation' ),
   					'new_item_name'              => __( 'New Item Name', 'ctlt_rubric_evaluation' ),
   					'add_new_item'               => __( 'Add New Item', 'ctlt_rubric_evaluation' ),
   					'edit_item'                  => __( 'Edit Item', 'ctlt_rubric_evaluation' ),
    				'update_item'                => __( 'Update Item', 'ctlt_rubric_evaluation' ),
    				'separate_items_with_commas' => __( 'Separate items with commas', 'ctlt_rubric_evaluation' ),
    				'search_items'               => __( 'Search Items', 'ctlt_rubric_evaluation' ),
    				'add_or_remove_items'        => __( 'Add or remove items', 'ctlt_rubric_evaluation' ),
    				'choose_from_most_used'      => __( 'Choose from the most used items', 'ctlt_rubric_evaluation' ),
   					'not_found'                  => __( 'Not Found', 'ctlt_rubric_evaluation' ),
    		);
    		$args = array(
    				'labels'                     => $labels,
    				'hierarchical'               => true,
    				'public'                     => true,
    				'show_ui'                    => true,
    				'show_admin_column'          => false,
   					'show_in_nav_menus'          => false,
   					'show_tagcloud'              => true,
			);
			
    		//NOTE: need to change in uninstall.php as well, as it's currently hardcoded for posts only.
			register_taxonomy( RUBRIC_EVALUATION_TAXONOMY, 'post', $args );
			register_taxonomy_for_object_type(RUBRIC_EVALUATION_TAXONOMY, 'post');
			return;
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
    
    //======================================================================
    //
    // Output functions
    //
    //======================================================================
    /**
     * Options page callback
     */
	public function create_rubric_evaluation_settings_page() {
		if ($this->active_tab === 'display_rubric') {
    		wp_enqueue_script('CTLT_Rubric_Evaluation_Script');
		}

		//add active class to tabs based on get parameter tab
		$active_rubric_class = $this->active_tab === 'display_rubric'? 'nav-tab-active' : '';
		$active_advanced_class = $this->active_tab === 'display_advanced'? 'nav-tab-active' : '';

    	?>
            <div class="wrap">
                <h2><?php _e('Rubric Evaluation Settings');?></h2>
                <?php 
                	if ($this->active_tab === 'display_advanced') {
						settings_errors('rubric_evaluation_roles'); 
					} else {
						settings_errors('rubric_evaluation_rubric');
					}
				?>    
                
                <h2 class="nav-tab-wrapper">

                	<a href="?page=rubric_evaluation_settings&tab=display_rubric" class="nav-tab <?php echo $active_rubric_class; ?>"><?php echo _x('Rubric', 'rubric tab title', 'ctlt_rubric_evaluation');?></a>
                	<a href="?page=rubric_evaluation_settings&tab=display_advanced" class="nav-tab <?php echo $active_advanced_class; ?>"><?php echo _x('Advanced', 'advanced tab title', 'ctlt_rubric_evaluation');?></a>
                </h2> 
                <form method="post" action="options.php">
                <?php
    				if ($this->active_tab === 'display_advanced') {
    					settings_fields( 'rubric_evaluation_roles' );
    					do_settings_sections( 'rubric_evaluation_settings_role' );
    				} else {
    					settings_fields('rubric_evaluation_rubric');
    					do_settings_sections( 'rubric_evaluation_settings_rubric' );
                    }
                    submit_button(); 
                ?>
                </form>
            </div>
            <?php
	}
        
    public function output_grading_group() {
    	?>
    	<a href="#add" class="button action-button" id="add_grading_group_btn">+ <?php _e('Grading Group', 'ctlt_rubric_evaluation');?></a>
    	<div class="add_grading_group" style="display:none;">
	    	<div class="grading_group_label">
	    		<label class="rubric_evaluation_grading_group_field"><?php _e('Label (eg.Assignment)', 'ctlt_rubric_evaluation');?></label>
	    		<br>
	    		<input id="rubric_evaluation_grading_group_field_label" name="rubric_evaluation_rubric_name[rubric_evaluation_grading_group_field_label]" value="">
	    	</div>
			<div class="grading_group_note">
				<label class="rubric_evaluation_grading_group_field"><?php _e('Note', 'ctlt_rubric_evaluation');?></label>
				<br>
				<input id="rubric_evaluation_grading_group_field_note" name="rubric_evaluation_rubric_name[rubric_evaluation_grading_group_field_note]" value="">
			</div>
			<!-- Hide this one for now as I don't think it quite makes sense -->
			<div class="grading_group_total" style="display:none;">
				<label class="rubric_evaluation_grading_group_field"><?php _e('% of Total', 'ctlt_rubric_evaluation');?></label>
				<br>
				<input id="rubric_evaluation_grading_group_field_total" name="rubric_evaluation_rubric_name[rubric_evaluation_grading_group_field_total]" value="">
			</div>
			<br>
			<a href="#add_advanced" class="button action-button" id="add_grading_group_advanced_btn">+ <?php _e('Advanced Options', 'ctlt_rubric_evaluation');?></a>
			<div class="add_grading_group_advanced" style="display:none;">
				<div class="grading_group_advanced_droptop">
					<label class="rubric_evaluation_grading_group_advanced_field"><?php _e('Drop Top #', 'ctlt_rubric_evaluation');?></label>
					<br>
					<select id="rubric_evaluation_grading_group_advanced_field_droptop" class="rubric_evaluation_select" name="rubric_evaluation_rubric_name[rubric_evaluation_grading_group_advanced_field_droptop]">
					<?php  
						$key_value = range(0,CTLT_Rubric_Evaluation_Admin::MAX_GRADES_DROPPED);
						//TODO: need to set the default value correctly!
						echo $this->_create_html_options(array_combine($key_value, $key_value), '0');
					?>
					</select>
				</div>
				<div class="grading_group_advanced_dropbottom">
					<label class="rubric_evaluation_grading_group_advanced_field"><?php _e('Drop Bottom #', 'ctlt_rubric_evaluation');?></label>
					<br>
					<select id="rubric_evaluation_grading_group_advanced_field_dropbottom" class="rubric_evaluation_select" name="rubric_evaluation_rubric_name[rubric_evaluation_grading_group_advanced_field_dropbottom]">
					<?php  
						$key_value = range(0,CTLT_Rubric_Evaluation_Admin::MAX_GRADES_DROPPED);
						//TODO: need to set the default value correctly!
						echo $this->_create_html_options(array_combine($key_value, $key_value), '0');
					?>
					</select>
				</div>
				<div class="grading_group_advanced_posttype" style="display:none;">
					<label class="rubric_evaluation_grading_group_advanced_field"><?php _e('Post Type', 'ctlt_rubric_evaluation');?></label>
					<br>
					<input id="rubric_evaluation_grading_group_advanced_field_posttype" name="rubric_evaluation_rubric_name[rubric_evaluation_grading_group_advanced_field_posttype]" value="">
				</div>
			</div>
		</div>
		<?php 
    }
    /**
     * Print the Section text
     */
    public function print_section_info() {
    	//         print 'Enter your settings below:';
    }

    public function output_roles($options) {
		$select_extras = (isset($options[2])? $options[2] : '');
		$value = isset($this->options['rubric_evaluation_roles_settings'][$options[0]])? $this->options['rubric_evaluation_roles_settings'][$options[0]] : $options[1];
    	echo "<select $select_extras id='".$options[0]."' name='rubric_evaluation_roles_settings[".$options[0]."]'>";
    	echo wp_dropdown_roles($value);
    	echo "</select>";
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function output_rubric() {
		$vertical_titles = !empty($this->options['rubric_evaluation_rubric_name'])? array_keys($this->options['rubric_evaluation_rubric_name']) : array();

    	echo "<table class='rubric'>\n<tr><th>Actions</th><th>".'Type'."</th>";
    	//table horizontal headers
    	foreach ($this->rubric_headers as $h_title) {
			echo '<th class="Type '.$this->_sanitize_class_name($h_title).'">'.$h_title.'</th>';
		}
		echo "</tr>\n";
		
		//table rows
		$row = 1; //row count
		foreach ($vertical_titles as $v_title) {
			echo '<tr>';
			echo '<td><a href="#delete" class="ctlt_rubric_delete_row" data-row="'.$row.'">x</a></td>';
			echo '<td class="heading '.$this->_sanitize_class_name($v_title).'">'.
					'<label id="rubric_evaluation_rubric_label_'.$row.'" value="'.$v_title.'" />'.$v_title.'</label>'.
					'<input type="hidden" id="rubric_evaluation_rubric_values_'.$row.'" name="rubric_evaluation_rubric_name[rubric_evaluation_rubric_values_'.$row.']" value="'.$v_title.'"/>'.
				'</td>';
			for ($column = 1; $column < (sizeof($this->rubric_headers) + 1); $column++) {
				$value = isset($this->options['rubric_evaluation_rubric_name'][$v_title][$this->rubric_headers[($column - 1)]]) ? $this->options['rubric_evaluation_rubric_name'][$v_title][$this->rubric_headers[($column - 1)]] : '';
 				echo '<td class="'.$this->_sanitize_class_name($v_title).' '.$this->_sanitize_class_name($this->rubric_headers[($column - 1)]).'">';
				echo '<input type="text" id="rubric_evaluation_rubric_values_'.$row.'_'.$column.'" name="rubric_evaluation_rubric_name[rubric_evaluation_rubric_values_'.$row.'_'.$column.']" value="'.$value.'" />';
				echo '</td>';
        	}
        	echo '</tr>';
        	$row++;
        }
        echo '</table>';
    }
    
    public function add_rubric_dropdown() {
		//only add the dropdown if there is any terms involved!
		//@TODO: think about whether unregister_taxonomy_for_object_type affects this?  Maybe need to check posts categories and see if custom taxonomy still there???
		$tax = get_taxonomies(array('name' => RUBRIC_EVALUATION_TAXONOMY));
		$taxterms = get_terms($tax, array('hide_empty' => false), 'names', 'and');
		if (count($taxterms) && !(isset($_GET['post_type']) && $_GET['post_type'] == 'page')) {
			$dropdown_options = array(
				'show_option_all' => __('View all rubric eval'),
				'hide_empty' => 0,
				'hierarchical' => 1,
				'show_count' => 0,
				'orderby' => 'name',
				'name' => 'rubric_eval',
				'taxonomy' => RUBRIC_EVALUATION_TAXONOMY
			);

			wp_dropdown_categories($dropdown_options);
		}
	}

    //======================================================================
    //
    // Sanitization callback functions
    //
    //======================================================================
    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     * @
     */
    public function sanitize_rubric( $input ) {
    	global $wp_taxonomies;
    	$new_input = array();

    	//format the rubric data
    	foreach (range(1, CTLT_rubric_evaluation_Admin::MAX_ROWS) as $row) {
    		if (isset($input['rubric_evaluation_rubric_values_'.$row])) {
    			$new_input[$input['rubric_evaluation_rubric_values_'.$row]] = '';
    
    			foreach (range(1, sizeof($this->rubric_headers)) as $column) {
    				if (isset($input['rubric_evaluation_rubric_values_'.$row.'_'.$column])) {
    					$new_input[$input['rubric_evaluation_rubric_values_'.$row]][$this->rubric_headers[($column - 1)]] = $input['rubric_evaluation_rubric_values_'.$row.'_'.$column];
    				}
    			}
    		}
    	}
    	
    	/** steps to add new labels
    	 * 0. need to check for deleted stuff!
    	 * 1. check required and validate fields for new field (currently only label???)
    	 * 2. add to taxonomy term
    	 * 3. add to rubric data so that it saves properly with new line if appropriate
    	 */

    	//step 0
    	$submitted_table_types = array_keys($new_input);
		$tax = get_taxonomies(array('name' => RUBRIC_EVALUATION_TAXONOMY));
		$taxterms = get_terms($tax, array('hide_empty' => false), 'names', 'and');
    	$saved_table_types = array();
    	foreach( $taxterms as $key => $term) {
			$saved_table_types[] = $term->name;
		}
		$to_be_removed_terms = array_diff($saved_table_types, $submitted_table_types);

		foreach ($to_be_removed_terms as $index => $name) {
			$term_id = $taxterms[$index]->term_id;
			$worked = wp_delete_term($term_id, RUBRIC_EVALUATION_TAXONOMY);
			if (is_wp_error($worked)) {
				add_settings_error('rubric_evaluation_rubric', 'term_delete_failed', __('Failed to remove row(s).', 'ctlt_rubric_evaluation'));
			}
		}

  		//step 1
    	if (isset($input['rubric_evaluation_grading_group_field_label']) && !empty($input['rubric_evaluation_grading_group_field_label'])) {
			
			$term_description = array(
				'label' => $input['rubric_evaluation_grading_group_field_label'],
				'note' => $input['rubric_evaluation_grading_group_field_note'],
				'total' => $input['rubric_evaluation_grading_group_field_total'],
				'droptop' => $input['rubric_evaluation_grading_group_advanced_field_droptop'],
				'dropbottom' => $input['rubric_evaluation_grading_group_advanced_field_droptop'],
				'posttype' => $input['rubric_evaluation_grading_group_advanced_field_posttype']
			);
			$term_description = serialize($term_description);
			
			//step 2
			$added_term = wp_insert_term($input['rubric_evaluation_grading_group_field_label'], RUBRIC_EVALUATION_TAXONOMY, array('description' => $term_description) );

			//now add to mucked array
			if ( !is_wp_error($added_term) ) {
				//step 3
				foreach (range(1, sizeof($this->rubric_headers)) as $column) {
	    			$new_input[$input['rubric_evaluation_grading_group_field_label']][$this->rubric_headers[($column - 1)]] = 0;
				}
				add_settings_error('rubric_evaluation_rubric', 'term_updated', __('Successfully added new row', 'ctlt_rubric_evaluation'), 'updated');
			} else {
				add_settings_error('rubric_evaluation_rubric', 'add_term_error', __('Problem adding new row', 'ctlt_rubric_evaluation'));
			}
		}

    	//format the add new row stuff
    	return array('rubric_evaluation_rubric_name' => $new_input);
    }
    
    public function sanitize_roles( $input ) {
    	$new_input = array();
    	foreach($input as $role => $selected_role) {
    		$new_input[$role] = $selected_role;
    	}
    	
    	//hardcode teacher to admin for now.....
    	$new_input['rubric_evaluation_role_teacher'] = 'administrator';
    	
//     	error_log('sanitize role input: '.print_r($input, true));
//     	error_log('sanitize role new_input: '.print_r($new_input, true));
    	return array('rubric_evaluation_roles_settings' => $new_input);
    }

    //======================================================================
    //
    // Private utility functions for the class
    //
    //======================================================================
    private function _get_role($id, $default_role) {
		return isset($this->option['role'][$id])? $this->option['role'][$id] : $default_role;
	}
	
	private function _sanitize_class_name($to_sanitize) {
		return preg_replace('/[^-_a-zA-Z0-9]+/','_', $to_sanitize);
	}
	
	private function _create_html_options($arr, $default = '') {
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

//I don't wrap around via is_admin because need to register taxonomy????
$ctlt_rubric_evaluation_settings = new CTLT_Rubric_Evaluation_Admin();