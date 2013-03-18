<?php
/*
Plugin Name: Happy Collision Productions Post Type
Description: Creates a "Productions" post type with lots of possibilities for data display.
Author: Don Denton
Version: 1.1
Author URI: http://happycollision.com
*/

require_once 'productions_class.php';
/*
*********************** Activate/Install/Upgrade ***********************
*/
//Establish variables for activation/upgrade
global $hc_productions_db_version;
$hc_productions_db_version = "0.10";
$hc_installed_ver = get_option( "hc_productions_db_version" );

function hc_productions_install() {
	global $wpdb;
	global $hc_productions_db_version;
	global $hc_installed_ver;
	
	if( $hc_installed_ver != $hc_productions_db_version ) {
		$table_name1 = $wpdb->prefix . "hc_productions_data";
		$table_name2 = $wpdb->prefix . "hc_productions_dates";

		$sql1 = "CREATE TABLE $table_name1 (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		production_id bigint(20) UNSIGNED NOT NULL,
		performance_type tinytext NOT NULL,
		time time DEFAULT '00:00:00' NOT NULL,
		start_date date,
		end_date date,
		mon bool DEFAULT '0' NOT NULL,
		tue bool DEFAULT '0' NOT NULL,
		wed bool DEFAULT '0' NOT NULL,
		thu bool DEFAULT '0' NOT NULL,
		fri bool DEFAULT '0' NOT NULL,
		sat bool DEFAULT '0' NOT NULL,
		sun bool DEFAULT '0' NOT NULL,
		dates text,
		description text,
		PRIMARY KEY  id (id)
		);";
		
		$sql2 = "CREATE TABLE $table_name2 (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		production_id bigint(20) UNSIGNED NOT NULL,
		preview_date date,
		opening_date date,
		closing_date date,
		PRIMARY KEY  id (id)
		);";
		
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql1);
		dbDelta($sql2);
		
		update_option("hc_productions_db_version", $hc_productions_db_version);
	}
}

register_activation_hook(__FILE__,'hc_productions_install');

//Checks for version updates and runs any necessary code
function hc_productions_update_db_check() {
    global $hc_productions_db_version;
    if (get_option('hc_productions_db_version') != $hc_productions_db_version) {
        //we have a new version.
        //For now, only the regular install needs to run
        hc_productions_install();
    }
}
add_action('plugins_loaded', 'hc_productions_update_db_check');

/*
*********************** Create Post Type/Taxonimies ***********************
*/

add_action('init', 'production_init');
function production_init() 
{
  $labels = array(
    'name' => _x('Productions', 'post type general name'),
    'singular_name' => _x('Production', 'post type singular name'),
    'add_new' => _x('Add New', 'Production'),
    'add_new_item' => __('Add New Production'),
    'edit_item' => __('Edit Production'),
    'new_item' => __('New Production'),
    'view_item' => __('View Production'),
    'search_items' => __('Search Productions'),
    'not_found' =>  __('No productions found'),
    'not_found_in_trash' => __('No productions found in Trash'), 
    'parent_item_colon' => ''
  );
  $args = array(
    'labels' => $labels,
    'public' => true,
    'publicly_queryable' => true,
    'show_ui' => true, 
    'query_var' => true,
    'rewrite' => true,
    'capability_type' => 'post',
    'hierarchical' => true,
    'menu_position' => null,
    'supports' => array('title','thumbnail','editor'),
    'has_archive' => 'past-productions'
  ); 
  register_post_type('production',$args);
}

//add filter to insure the text Production, or production, is displayed when user updates a production 
add_filter('post_updated_messages', 'production_updated_messages');
function production_updated_messages( $messages ) {

  $messages['production'] = array(
    0 => '', // Unused. Messages start at index 1.
    1 => sprintf( __('Production updated. <a href="%s">View Production</a>'), esc_url( get_permalink($post_ID) ) ),
    2 => __('Custom field updated.'),
    3 => __('Custom field deleted.'),
    4 => __('Production updated.'),
    /* translators: %s: date and time of the revision */
    5 => isset($_GET['revision']) ? sprintf( __('production restored to revision from %s'), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
    6 => sprintf( __('Production published. <a href="%s">View Production</a>'), esc_url( get_permalink($post_ID) ) ),
    7 => __('Production saved.'),
    8 => sprintf( __('Production submitted. <a target="_blank" href="%s">Preview production</a>'), esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
    9 => sprintf( __('Production scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview Production</a>'),
      // translators: Publish box date format, see http://php.net/date
      date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ), esc_url( get_permalink($post_ID) ) ),
    10 => sprintf( __('Production draft updated. <a target="_blank" href="%s">Preview Production</a>'), esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
  );

  return $messages;
}

//hook into the init action and call create_production_taxonomies when it fires
add_action( 'init', 'create_production_taxonomies', 0 );

//create taxonomies for the post type "production"
function create_production_taxonomies() 
{
  // Season Year: NOT hierarchical (like tags)
  $labels = array(
    'name' => _x( 'Season Years', 'taxonomy general name' ),
    'singular_name' => _x( 'Season Year', 'taxonomy singular name' ),
    'search_items' =>  __( 'Search Season Years' ),
    'popular_items' => __( 'Popular Season Years' ),
    'all_items' => __( 'All Season Years' ),
    'parent_item' => null,
    'parent_item_colon' => null,
    'edit_item' => __( 'Edit Season Year' ), 
    'update_item' => __( 'Update Season Year' ),
    'add_new_item' => __( 'Add New Season Year' ),
    'new_item_name' => __( 'New Season Year' ),
    'separate_items_with_commas' => __( 'Separate season years with commas' ),
    'add_or_remove_items' => __( 'Add or remove season years' ),
    'choose_from_most_used' => __( 'Choose from the most used season years' )
  ); 

    if(!taxonomy_exists('season_year')){
		register_taxonomy('season_year','production',array(
			'hierarchical' => false,
			'labels' => $labels,
			'show_ui' => true,
			'query_var' => true,
			'rewrite' => array( 'slug' => 'season_year' ),
		));
	}

  // Lyricist: NOT hierarchical (like tags)
  $labels = array(
    'name' => _x( 'Lyricists', 'taxonomy general name' ),
    'singular_name' => _x( 'Lyricist', 'taxonomy singular name' ),
    'search_items' =>  __( 'Search Lyricists' ),
    'popular_items' => __( 'Popular Lyricists' ),
    'all_items' => __( 'All Lyricists' ),
    'parent_item' => null,
    'parent_item_colon' => null,
    'edit_item' => __( 'Edit Lyricist' ), 
    'update_item' => __( 'Update Lyricist' ),
    'add_new_item' => __( 'Add New Lyricist' ),
    'new_item_name' => __( 'New Lyricist' ),
    'separate_items_with_commas' => __( 'Separate lyricists with commas' ),
    'add_or_remove_items' => __( 'Add or remove lyricist' ),
    'choose_from_most_used' => __( 'Choose from the most used lyricists' )
  ); 

    if(!taxonomy_exists('lyricist')){
		register_taxonomy('lyricist','production',array(
			'hierarchical' => false,
			'labels' => $labels,
			'show_ui' => true,
			'query_var' => true,
			'rewrite' => array( 'slug' => 'lyricist' ),
		));
	}

  // Author: NOT hierarchical (like tags)
  $labels = array(
    'name' => _x( 'Authors', 'taxonomy general name' ),
    'singular_name' => _x( 'Author', 'taxonomy singular name' ),
    'search_items' =>  __( 'Search Authors' ),
    'popular_items' => __( 'Popular Authors' ),
    'all_items' => __( 'All Authors' ),
    'parent_item' => null,
    'parent_item_colon' => null,
    'edit_item' => __( 'Edit Author' ), 
    'update_item' => __( 'Update Author' ),
    'add_new_item' => __( 'Add New Author' ),
    'new_item_name' => __( 'New Author' ),
    'separate_items_with_commas' => __( 'Separate authors with commas' ),
    'add_or_remove_items' => __( 'Add or remove author' ),
    'choose_from_most_used' => __( 'Choose from the most used authors' )
  ); 

    if(!taxonomy_exists('author')){
		register_taxonomy('author','production',array(
			'hierarchical' => false,
			'labels' => $labels,
			'show_ui' => true,
			'query_var' => true,
			'rewrite' => array( 'slug' => 'author' ),
		));
	}

  // Composer: NOT hierarchical (like tags)
  $labels = array(
    'name' => _x( 'Composers', 'taxonomy general name' ),
    'singular_name' => _x( 'Composer', 'taxonomy singular name' ),
    'search_items' =>  __( 'Search Composers' ),
    'popular_items' => __( 'Popular Composers' ),
    'all_items' => __( 'All Composers' ),
    'parent_item' => null,
    'parent_item_colon' => null,
    'edit_item' => __( 'Edit Composer' ), 
    'update_item' => __( 'Update Composer' ),
    'add_new_item' => __( 'Add New Composer' ),
    'new_item_name' => __( 'New Composer' ),
    'separate_items_with_commas' => __( 'Separate composers with commas' ),
    'add_or_remove_items' => __( 'Add or remove composer' ),
    'choose_from_most_used' => __( 'Choose from the most used composers' )
  ); 

    if(!taxonomy_exists('composer')){
		register_taxonomy('composer','production',array(
			'hierarchical' => false,
			'labels' => $labels,
			'show_ui' => true,
			'query_var' => true,
			'rewrite' => array( 'slug' => 'composer' ),
		));
	}

}


/*
****************************** Meta Boxes *********************
 */

add_action("admin_init", "production_meta_init");
 
function production_meta_init(){
	//format: add_meta_box( $id, $title, $callback, $page, $context, $priority );
	add_meta_box('running_dates_meta', 'Running Dates Information', 'running_dates_meta', 'production', 'normal', 'low');
	add_meta_box('theatre_info_meta', 'Theatre Information', 'theatre_info_meta', 'production', 'normal', 'low');
	add_meta_box('exact_running_dates_meta', 'Performance Types and Exact Dates', 'exact_running_dates_meta', 'production', 'normal', 'low');
}
 
function theatre_info_meta() {
	global $post;
	?>
	<div>These are optional fields, and would provide users with links to see the theatre's site and/or buy tickets.</div>
	<p><label>Theatre Name:</label><br />
	<input type="text" value="<?php echo get_post_meta($post->ID,'hc_theatre_name', true); ?>" size="50" name="hc_theatre_name">
	</p>

	<p><label>Theatre Location:</label><br />
	<input type="text" value="<?php echo get_post_meta($post->ID,'hc_theatre_location', true); ?>" size="50" name="hc_theatre_location">
	</p>

	<p><label>Theatre Web Address:</label> <span class="hint">Format: <code>postplayhouse.com</code></span><br />
	<input class="left" type="text" value="<?php echo get_post_meta($post->ID,'hc_theatre_url', true); ?>" size="50" name="hc_theatre_url">
	</p>

	<p><label>Web Address for Ticket Purchase:</label> <span class="hint">Format: <code>theatretickets.com</code></span><br />
	<input type="text" value="<?php echo get_post_meta($post->ID,'hc_tickets_url', true); ?>" size="50" name="hc_tickets_url">
	</p>
	
	<?php
}

function running_dates_meta() {
	global $post;
	$dates = HCProductionDates::get_dates($post->ID);
	?>
	<style>
		.left{
			float:left;
		}
		.hint{
			color:#999;
		}
		p{
			width:100%;
		}
		p.left, .half{
			width:48%;
			padding:1%;
			margin:0;
			overflow:hidden;
			display:block;
			float:left;
		}
		p.left.one_third{
			width:32%;
		}
		p.left.two_thirds{
			width:64%;
		}
		input[type="text"]{
			width:100%;
			margin:0;
		}
		.anchor{
			height:0;
			clear:all;
			display:block;
			width:100%;
		}
		.textarea{
			display:block;
			height:100px
		}
		.performances{
			margin-top:8px;
		}
		.performances .no_break{
			white-space:nowrap;
		}
		.performances input[type="checkbox"]{
			margin-right:5px;
		}
		.no-js .performances .hidden{
			display:inherit;
		}
		.hide_show{
			cursor:default;
		}
	</style>
	<p class="left"><label>Opening Date:</label> <span class="hint">Format: <code>May&nbsp;28,&nbsp;2010</code></span><br />
	<input type="text" value="<?php echo $dates->opening_date; ?>" size="50" name="hc_opening_date">
	</p>
	
	<p class="left"><label>Closing Date:</label> <span class="hint">Format: <code>August&nbsp;14,&nbsp;2010</code></span><br />
	<input type="text" value="<?php echo $dates->closing_date; ?>" size="50" name="hc_closing_date">
	</p>
	<br clear="all" />

	<p><label>Optional Preview Start Date:</label> <span class="hint">Enter the date of the first preview.  Format: <code>May&nbsp;25,&nbsp;2010</code></span><br />
	<input type="text" value="<?php echo $dates->preview_date; ?>" size="50" name="hc_preview_date">
	</p>
	
	<input type="hidden" value="NotAuto" name="hc_production_autosave_check" />
	<?php
}

function exact_running_dates_meta() {
	global $post;
	$custom = get_post_custom($post->ID);
	
	?>
	<div>Here is where we get fancy.  Once you have filled in the Opening and/or Closing dates above, you can fill out the information below, and it can be used in the display of many data-centric areas of your url depending on your current theme.</div>
	
	<hr />
	
	<?php $performances = HCProduction::get_form_parts_by_production($post->ID);
		$i = 0;
		foreach($performances as $performance):
		$i++;
	?>
	<div class="performances">
		<input type="hidden" value="<?php echo $performance->id; ?>" name="hc_production-id-<?php echo $i; ?>" />
		<input type="button" class="button hidden" style="color:#954" value="Remove This Performance Type" role="remove" /><br class="anchor"/>
		<p class="left one_third"><label>Name/Type:</label><br />
		<input type="text" value="<?php echo $performance->performance_type; ?>" size="50" name="hc_production-performance_type-<?php echo $i; ?>"/>
		<span class="hint">ie: Matinee, Evening, At the Roadhouse</span><br class="anchor"/></p>
		
		<p class="left two_thirds"><label>Description:</label><br />
		<input type="text" value="<?php echo $performance->description; ?>" size="50" name="hc_production-description-<?php echo $i; ?>">
		<span class="hint">If the the type of performance needs more explaining.  ie: "Come see our special two night performance at The Roadhouse at 147 Oak Street!"</span><br class="anchor"/></p>
		
		<br clear="all" />
		<a class="hide_show" style="display:block;">Show Details:</a>
		
		<div class="hidden">
		<p class="left one_third"><label>Curtain Time:</label><br />
		<input type="text" value="<?php echo $performance->time; ?>" size="50" name="hc_production-time-<?php echo $i; ?>">
		<span class="hint">Format: <code>7:30pm</code></span><br class="anchor"/></p>

		<p class="left two_thirds"><label>Days of the Week</label><br />
		<label class="no_break">Mon<input type="checkbox" name="hc_production-mon-<?php echo $i; ?>" value="1" <?php echo $performance->mon; ?> /></label>
		<label class="no_break">Tue<input type="checkbox" name="hc_production-tue-<?php echo $i; ?>" value="1" <?php echo $performance->tue; ?> /></label>
		<label class="no_break">Wed<input type="checkbox" name="hc_production-wed-<?php echo $i; ?>" value="1" <?php echo $performance->wed; ?> /></label>
		<label class="no_break">Thu<input type="checkbox" name="hc_production-thu-<?php echo $i; ?>" value="1" <?php echo $performance->thu; ?> /></label>
		<label class="no_break">Fri<input type="checkbox" name="hc_production-fri-<?php echo $i; ?>" value="1" <?php echo $performance->fri; ?> /></label>
		<label class="no_break">Sat<input type="checkbox" name="hc_production-sat-<?php echo $i; ?>" value="1" <?php echo $performance->sat; ?> /></label>
		<label class="no_break">Sun<input type="checkbox" name="hc_production-sun-<?php echo $i; ?>" value="1" <?php echo $performance->sun; ?> /></label><br />
		<span class="hint">Choose all days on which the performance above occurs.</span>
		<br class="anchor"/></p>
		
		<br clear="all" />

		<p class="left"><label>Start Date:</label><br />
		<input class="left" type="text" value="<?php echo $performance->start_date; ?>" size="50" name="hc_production-start_date-<?php echo $i; ?>">
		<span class="hint">The date the performances described above will begin. Format: <code>January&nbsp;12,&nbsp;2013</code></span><br class="anchor"/></p>
	
		<p class="left"><label>End Date:</label><br />
		<input class="left" type="text" value="<?php echo $performance->end_date; ?>" size="50" name="hc_production-end_date-<?php echo $i; ?>">
		<span class="hint">Format: <code>February&nbsp;21,&nbsp;2013</code></span><br class="anchor"/></p>
		<br clear="all" />
		
		<p><label>Override Dates:</label><br />
		<textarea class="textarea half" name="hc_production-dates-<?php echo $i; ?>"><?php echo $performance->dates; ?></textarea>
		<span class="hint half">If this field has a value, it will override the Weekdays, Start Date, and End Date.  Use this area for performances without regular repeating schedules.<br />
		Format:<br />
		
		<code>2012<br>
		March 5, 6, 7, 23, 24, 25<br>
		April 3, 7<br>
		May 8, 9, 10</code><br />
		Be sure to put each month and year on a different line</span></p>
		<br clear="all" />

		</div><!--hidden-->
		<hr />

	</div><!--performances-->
	<?php endforeach; ?>

	<input type="button" style="color:#496" class="button hidden" value="Add another Performance Type" role="add" />
	
<?php
}

//Now to make sure the ALL the data gets saved:
add_action('save_post', 'hc_production_save_details');
function hc_production_save_details(){
	global $post;

	if($_POST['hc_production_autosave_check']){
		//Save the basic information
		HCProductionDates::save_dates(array(
			'opening_date' => $_POST['hc_opening_date']
			,'closing_date' => $_POST['hc_closing_date']
			,'preview_date' => $_POST['hc_preview_date']
			,'production_id' => $post->ID
			));
		
		//Save the external information fields
		update_post_meta($post->ID, 'hc_theatre_url', $_POST['hc_theatre_url']);
		update_post_meta($post->ID, 'hc_theatre_name', $_POST['hc_theatre_name']);
		update_post_meta($post->ID, 'hc_theatre_location', $_POST['hc_theatre_location']);
		update_post_meta($post->ID, 'hc_tickets_url', $_POST['hc_tickets_url']);
	
		//Save the performance data to the special table.
		$production = new HCProduction();
		$errors = $production->save_form($post->ID);
		
		//This error reporting doesn't work right now... I think.
		if(is_array($errors)){
			$output = implode(' ',$errors);
			$display_errors = new WP_Error();
			$display_errors->add('hc_productions',$output);
			echo $output;
		}
	}
	
}

function hc_productions_enqueue($hook) {
    if( 'post.php' != $hook && 'post-new.php' != $hook) return;
    
    global $post;
    if($post->post_type == 'production'){
    	wp_enqueue_script( 'hc_productions_admin', plugins_url('/hc_productions_admin.js', __FILE__), 'jquery', false, true);
	}
}
add_action( 'admin_enqueue_scripts', 'hc_productions_enqueue' );

//display errors
add_action('admin_notices','hc_productions_errors');
function hc_productions_errors(){
	$errors = new WP_Error();
	echo $errors->get_error_message('hc_productions');
}