<?php
Class HCProduction{
	protected $table_name; //will become on construct $wpdb->prefix . 'hc_productions_data';
	protected static $table_suffix = 'hc_productions_data';
	protected static $form_prefix = 'hc_production-';
	protected static $db_fields =
		array(
			'production_id'
			,'performance_type'
			,'time'
			,'start_date'
			,'end_date'
			,'mon'
			,'tue'
			,'wed'
			,'thu'
			,'fri'
			,'sat'
			,'sun'
			,'dates'
			,'description'
		);
	protected static $db_formats = 
		array(
			'production_id'		=> '%d'
			,'performance_type'	=> '%s'
			,'time'				=> '%s'
			,'start_date'		=> '%s'
			,'end_date'			=> '%s'
			,'mon'				=> '%d'
			,'tue'				=> '%d'
			,'wed'				=> '%d'
			,'thu'				=> '%d'
			,'fri'				=> '%d'
			,'sat'				=> '%d'
			,'sun'				=> '%d'
			,'dates'			=> '%s'
			,'description'		=> '%s'
		);
	//Both the above arrays do not include the 'id' field.  It isn't necessary below.
	
	function __construct(){
		global $wpdb;
		$this->table_name = $wpdb->prefix . self::$table_suffix;
	}
		
	//CRUD:
	//private function to lookup all data for particular production
	//returns an object array (indexed by id) ordered by date for a particular production
	private function production_data($production_id){
		global $wpdb;
		$sql = 
			"SELECT * FROM ".$this->table_name."
			WHERE production_id = '$production_id'
			ORDER BY
				start_date ASC,
				end_date ASC,
				time ASC
			";
		return $wpdb->get_results($sql,OBJECT_K);
	}
	
	//private function to create an array of values to insert with $wpdb
	/*
	 * Expects an array with key value pairs as column value pairs. ( 'type' => 'evening' )
	 */
	private function col_val_array($record_array){
		$col_vals = array();
		foreach(self::$db_fields as $field){
			$col_vals[$field] = $record_array[$field];
		}
		return $col_vals;
	}
	
	//private function to create record
	/*
	 * Expects an array with key value pairs as column value pairs. ( 'type' => 'evening' )
	 */
	private function create_record($record_array){
		global $wpdb;
		// http://codex.wordpress.org/Class_Reference/wpdb
		//will return false on fail
		return $wpdb->insert(
			$this->table_name,
			$this->col_val_array($record_array),
			self::$db_formats
		);
	}
	
	//private function to update record
	/*
	 * Expects an array with key value pairs as column value pairs. ( 'type' => 'evening' )
	 */
	private function update_record($record_array){
		global $wpdb;
		// http://codex.wordpress.org/Class_Reference/wpdb
		$rows_affected = $wpdb->update( 
			$this->table_name,
			$this->col_val_array($record_array),
			array('id' => $record_array['id']), 
			self::$db_formats,
			array('%d') 
		);
		return $rows_affected; // or false, on failure
	}
	
	//private function to delete record. Returns false on failure. (Can return 0 on success)
	private function delete_record($id){
		global $wpdb;
		return $wpdb->query( 
			$wpdb->prepare( 
				"DELETE FROM ".$this->table_name."
				 WHERE id = %d
				"
			,$id)
		);
	}	
	
	//private function to chop the field names into chunks. ie: 'hc_production' 'field_name' '##'
	private function chop_form_fields($field_name){
		$field_array = explode('-', $field_name);
		return array('field' => $field_array[1], 'form_id' => $field_array[2]);
	}
	
	//private function to change date to database readable
	private function machine_readable($form_value, $date_type){
		if($form_value == '') return $form_value;
		if($date_type == 'date'){
			return date('Y-m-d', strtotime($form_value));
		}elseif($date_type == 'time'){
			return date('H:i:s', strtotime($form_value));
		}elseif($date_type == 'datetime'){
			return date('Y-m-d H:i:s', strtotime($form_value));
		}
	}
	
	//Adds year to override dates if none was given
	private function override_dates_add_year($data){
		if($data == '') return '';
		if(preg_match('/^\d{4}./sm', $data) == 1) {return $data;}
		return date('Y') . "\n" . $data;
	}
	
	//public function to save form data
	public function save_form($production_id){
		//if there isn't any POST data, we are stuck.
		if(!isset($_POST)) return false;
		
		//let's identify the form data we want:
		$pertinent_data = array();
		foreach($_POST as $field => $data){
			$test = strstr($field, self::$form_prefix);
			if($test !== false){
				$pertinent_data[$field] = trim($_POST[$field]);
			}
		}
		
		//best to change all '' values to null
		foreach($pertinent_data as $pkey => $data){
			if($data === '') $pertinent_data[$pkey] = null;
		}

		//let's break the form data into groups
		$grouped_data = array();
		//ddprint($pertinent_data);
		foreach($pertinent_data as $field => $data){
			$form_group = $this->chop_form_fields($field);
			$grouped_data[$form_group['form_id']][$form_group['field']] = $data;
		}
		
		//Right off the bat, if there are form records that have no data, let's toss them
		foreach($grouped_data as $gkey => $drop_candidate){
			$has_data = array();
			foreach($drop_candidate as $field => $data){
				if(!($data === null || $data === '')){
					//the field has data
					$has_data[] = $field;
				}
			}
			if(count($has_data) == 0){
				//buh bye.
				unset($grouped_data[$gkey]);
			}
		}
		
		//change human dates to database dates AND add year to override dates if necessary.
		foreach($grouped_data as $gkey => $form_array){
			foreach($form_array as $field => $data){
				switch ($field){
					case 'start_date':
					case 'end_date':
						$grouped_data[$gkey][$field] = $this->machine_readable($data, 'date');
						break;
					case 'time':
						$grouped_data[$gkey][$field] = $this->machine_readable($data, 'time');
						break;
					case 'dates':
						$grouped_data[$gkey][$field] = $this->override_dates_add_year($data);
						break;
					default: break;
				}
			}
		}
				
		//we need current data to compare to, so we know if we have to delete at the end,
		//and so we know if we really have anything to save at all.
		$in_database = $this->production_data($production_id);
		//ddprint($in_database);
		if( gettype($in_database) == null || count($in_database) < 1) {
			$in_database = array();
		}

		$to_insert = array();
		$to_update = array();
		$to_delete = array();
		
		//anything to be inserted will not have an id
		//anything to be updated will have an id		
		foreach($grouped_data as $form_array){
			//also... a good time to drop in the post_id
			$form_array['production_id'] = $production_id;
			
			if($form_array['id']==null || count($in_database) < 1){
				$to_insert[] = $form_array;
			}else{
				$to_update[] = $form_array;
			}
		}
		
		//Look for current records in the db that aren't in the form
		if(count($in_database) > count($to_update)){
			//We have records to delete!
			$to_update_ids = array();
			foreach($to_update as $record){
				$to_update_ids[] = $record['id'];
			}
			foreach($in_database as $id => $record){
				if(!in_array($id,$to_update_ids)){
					//That record needs to go!
					$to_delete[] = $in_database[$id];
					unset($in_database[$id]);
				}
			}
		}
		
		
		//The update fields might actually need to be deleted after all
		//If the only two fields with data are the id fields, we can delete
		foreach($to_update as $ukey => $delete_candidate){
			$has_data = array();
			foreach($delete_candidate as $field => $data){
				if(!($data === null || $data === '')){
					//the field has data
					$has_data[] = $field;
				}
			}
			if(count($has_data) != 2) continue;
			$strikes = 1;
			if(in_array('id',$has_data)) $strikes++;
			if(in_array('production_id',$has_data)) $strikes++;
			if($strikes == 3){
				//well... you know.
				//we have to put in the current database object, not the form array
				$to_delete[] = $in_database[$to_update[$ukey]['id']];
				unset($to_update[$ukey]);
			}
		}
				
		foreach($to_update as $ukey => $form_array){
			$save = false;
			foreach($in_database[$form_array['id']] as $col => $data){
				if(isset($form_array[$col])){
					//The html form had the field, or the box was checked
					if($form_array[$col] === $data){
						//The form and the database match. No need to save.
					}else{
						//The form and the database do not match.
						$save = true;
					}
				}else{
					//The html form didn't have the field/the box wasn't checked
					//Add the column to the fields to update
					$to_update[$ukey][$col] = '';
					$save = true;
					
					//But if it is from the day checkboxes, we may still be in the clear
					if(in_array($col, array('mon','tue','wed','thu','fri','sat','sun'))){
						$to_update[$ukey][$col] = 0;
						if($to_update[$ukey][$col] == $data){
							//Then we are still good.
							$save = false;
						}
					}
				}
			}
			if($save == false){
				//There was nothing to save.
				unset($to_update[$ukey]);
			}
		}
		
		//Let's finally alter the database!
		$errors = array();
		foreach($to_insert as $insert){
			$succeded = $this->create_record($insert);
			if($succeded !== false) continue;
			$errors[] = "Failed to insert record for {$insert['type']}";
		}
		
		foreach($to_update as $update){
			$succeded = $this->update_record($update);
			if($succeded !== false) continue;
			$errors[] = "Failed to update record for {$update['type']}, id = {$update['id']}";
		}
		
		foreach($to_delete as $delete){
			$succeded = $this->delete_record($delete->id);
			if($succeded !== false) continue;
			$errors[] = "Failed to delete record for {$delete->type}, id = {$delete->id}";
		}
		
		if(count($errors) > 0) return $errors;
		return true;
	}
	
	//Display
	//public function to allow loop on admin form
	public static function get_form_parts_by_production($production_id){
		$production = new self();
		$performances = $production->production_data($production_id);
		//replace binary data with 'checked' or ''
		foreach($performances as $performance){
			foreach($performance as $field => $data){
				$performance->$field = $production->humanize_date_time($data);
			}
			$checked = array('mon','tue','wed','thu','fri','sat','sun');
			foreach($checked as $check){ //ha ha
				if($performance->$check == 1){
					$performance->$check = 'checked';
				}else{
					$performance->$check = '';
				}
			}
		}
		if(count($performances)==0){
			$performances[0] = (object) '';
		}
		return $performances;
	}
	
	public static function get_table_name(){
		global $wpdb;
		return $wpdb->prefix . self::$table_suffix;
	}
	//returns the human-readable version of the data
	public function humanize_date_time($database_value){
		if(preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $database_value) == 1){
			//if YYYY-MM-DD HH:MM:SS, return Month DD, Year at HH:MM pm
			if($database_value == '0000-00-00 00:00:00') return '';
			return date('F j, Y \a\t g:ia',strtotime($database_value));
		}elseif(preg_match('/^\d{4}-\d{2}-\d{2}$/', $database_value) == 1){
			//if YYYY-MM-DD, return Month DD, Year
			if($database_value == '0000-00-00') return '';
			return date('F j, Y',strtotime($database_value));
		}elseif(preg_match('/^\d{2}:\d{2}:\d{2}$/', $database_value) == 1){
			//if HH:MM:SS, return HH:MM pm
			if($database_value == '00:00:00') return '';
			return date('g:ia',strtotime($database_value));
		}else{
			return $database_value;
		}
	}

}
Class HCProductionDates{
	
	protected static $table_suffix = 'hc_productions_dates';
	protected static $key_field = 'production_id';
	private static $db_fields = array(
		'closing_date'
		,'opening_date'
		,'preview_date'
		,'production_id'
	);
	private static $db_formats = array(
		'%s'
		,'%s'
		,'%s'
		,'%d'
	);
	
	public static function key_field(){
		return self::$key_field;
	}
	
	public static function get_dates($production_id){
		global $wpdb;
		$sql = 
			"SELECT * FROM ". $wpdb->prefix . self::$table_suffix ."
			WHERE ".self::$key_field." = '$production_id'
			";
		$dates = $wpdb->get_row($sql);
		if($dates == null) return null;
		foreach($dates as $field => $data){
			$dates->$field = self::humanize_date_time($data);
		}
		return  $dates;
	}
	
	//private function to create an array of values to insert with $wpdb
	/*
	 * Expects an array with key value pairs as column value pairs. ( 'type' => 'evening' )
	 */
	private static function col_val_array($record_array){
		$col_vals = array();
		foreach(self::$db_fields as $field){
			if($field == self::$key_field) {
				$col_vals[$field] = $record_array[$field];				
			}else{
				$col_vals[$field] = self::machine_readable($record_array[$field],'date');
			}
		}
		return $col_vals;
	}

	//private function to change date to database readable
	private static function machine_readable($form_value, $date_type){
		if($form_value == '') return $form_value;
		if($date_type == 'date'){
			return date('Y-m-d', strtotime($form_value));
		}elseif($date_type == 'time'){
			return date('H:i:s', strtotime($form_value));
		}elseif($date_type == 'datetime'){
			return date('Y-m-d H:i:s', strtotime($form_value));
		}
	}
	
	public static function save_dates($record_array){
		global $wpdb;
		foreach($record_array as $field => $record){
			$record_array[$field] = trim($record);
		}
		if(self::get_dates($record_array[self::$key_field])){
			//update
			self::update_dates($record_array);
		}else{
			//insert
			self::insert_dates($record_array);
		}
	}

	private function insert_dates($record_array){
		global $wpdb;
		// http://codex.wordpress.org/Class_Reference/wpdb
		//will return false on fail
		
		return $wpdb->insert(
			$wpdb->prefix . self::$table_suffix,
			self::col_val_array($record_array),
			self::$db_formats
		);
	}
	
	private static function update_dates($record_array){
		global $wpdb;
		$record_array = self::col_val_array($record_array);
		// http://codex.wordpress.org/Class_Reference/wpdb
		$rows_affected = $wpdb->update( 
			$wpdb->prefix . self::$table_suffix,
			self::col_val_array($record_array),
			array(self::$key_field => $record_array[self::$key_field]), 
			self::$db_formats,
			array('%d') 
		);
		return $rows_affected; // or false, on failure
	}
	
	//returns the human-readable version of the data
	private static function humanize_date_time($database_value){
		if(preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $database_value) == 1){
			//if YYYY-MM-DD HH:MM:SS, return Month DD, Year at HH:MM pm
			if($database_value == '0000-00-00 00:00:00') return '';
			return date('F j, Y \a\t g:ia',strtotime($database_value));
		}elseif(preg_match('/^\d{4}-\d{2}-\d{2}$/', $database_value) == 1){
			//if YYYY-MM-DD, return Month DD, Year
			if($database_value == '0000-00-00') return '';
			return date('F j, Y',strtotime($database_value));
		}elseif(preg_match('/^\d{2}:\d{2}:\d{2}$/', $database_value) == 1){
			//if HH:MM:SS, return HH:MM pm
			if($database_value == '00:00:00') return '';
			return date('g:ia',strtotime($database_value));
		}else{
			return $database_value;
		}
	}
	
	public static function setup_upcoming_current(){
		global $wpdb;
		$today = date('Y-m-d');
		
		//see if there are any shows this month
		$sql = "
			SELECT * FROM ". $wpdb->prefix . self::$table_suffix ."
			WHERE opening_date > '$today'
			ORDER BY opening_date
		";
		
		$upcoming = self::get_productions_for_upcoming_current($sql);
		
		$sql = "
			SELECT * FROM ". $wpdb->prefix . self::$table_suffix ."
			WHERE opening_date <= '$today' 
				AND (closing_date >= '$today' OR closing_date = '0000-00-00')
			ORDER BY opening_date
		";
		
		$current = self::get_productions_for_upcoming_current($sql);
		
		if($upcoming==false && $current==false){
			return false;
		}
								
		return array('Current' => $current, 'Upcoming' => $upcoming);
	}

	
	public static function setup_preview_box(){
		global $wpdb;
		$today = date('Y-m-d');
		$future = date('Y-m-d', strtotime('today +2 weeks'));		
		
		//see if there are any shows this month
		$sql = "
			SELECT production_id FROM ". $wpdb->prefix . self::$table_suffix ."
			WHERE opening_date <= '$future' 
				AND (closing_date >= '$today' OR preview_date >= '$today')
		";
		
		$upcoming = self::get_productions_for_display($sql);
		
		if($upcoming==false){
			return false;
		}
		
		//Trim off all dates before today
		foreach($upcoming as $date => $object_array){
			if($date < date('Y-m-d')){
				unset($upcoming[$date]);
			}
		}
		
		return $upcoming;
	}
	
	public static function setup_calendar(){
		global $wpdb;
		$today = date('Y-m-d');
		$this_month = date('Y-m') . '-01';
		
		//see if there are any shows this month or in the future
		$sql = "
			SELECT production_id FROM ". $wpdb->prefix . self::$table_suffix ."
			WHERE closing_date >= '$this_month'
		";
		
		$upcoming = self::get_productions_for_display($sql);
		
		if($upcoming==false){
			return false;
		}
		

		//Trim off all dates before the 1st of this month
		foreach($upcoming as $date => $object_array){
			if($date < $this_month){
				unset($upcoming[$date]);
			}
		}

		//get the Y-m-d for the last date
		end($upcoming);
		$last_day = key($upcoming);
		reset($upcoming);
		$first_day = key($upcoming);
		reset($upcoming);
				
		//Get the timestamp for the beginning of the week when the first month starts
		$first_of_month =  substr($first_day,0,strrpos($first_day,'-')) . '-01';
		$get_first_of_month =  getdate(strtotime($first_of_month));
		$ts_first_sunday = $get_first_of_month[0] - ($get_first_of_month['wday']*24*60*60);
		
		//create an array with all calendar dates, even if there are no shows
		$ts = $ts_first_sunday;
		$ts_last_day = strtotime("$last_day +1 day");
		$array_of_dates = array();
		while($ts <= $ts_last_day){
			if(isset($upcoming[date('Y-m-d', $ts)])){
				$array_of_dates[date('Y-m-d', $ts)] = $upcoming[date('Y-m-d', $ts)];
			}else{
				$array_of_dates[date('Y-m-d', $ts)] = null;
			}
			$ts = $ts + (24*60*60);
		}		
		
		//ddprint($array_of_dates);
		return $array_of_dates;
	}
	
	private static function get_productions_for_upcoming_current($sql){
		global $wpdb;
		
		$results = $wpdb->get_results($sql);
		if($results === null) return false;
		
		$posts = array();
		foreach($results as $row){
			//get the associated production from wp_posts
			$posts[$row->opening_date . '_' . $row->production_id] = get_post($row->production_id);
			
			if($posts[$row->opening_date . '_' . $row->production_id]->post_status != 'publish'){
				unset($posts[$row->opening_date . '_' . $row->production_id]);
				continue;
			}
			
			foreach (array('preview_date','opening_date','closing_date') as $date_type){
				if($row->$date_type !== '0000-00-00'){
					$posts[$row->opening_date . '_' . $row->production_id]->$date_type = strtotime($row->$date_type);
				}
			}
		}
		
		return $posts;
	}
	
	private static function get_productions_for_display($sql){
		global $wpdb;

		$results = $wpdb->get_results($sql);
		if($results === null) return false;
		
		$production_ids = array();
		foreach($results as $row){
			$production_ids[] = $row->production_id;
		}
		//each id needs to be wrapped with ''
		$production_ids = implode('\',\'',$production_ids);
		
		$sql = "
			SELECT * FROM " . HCProduction::get_table_name() . "
			WHERE production_id IN('". $production_ids ."')
		";
		$results = $wpdb->get_results($sql,OBJECT_K);

		//no need to capture to a variable. Objects are passed by reference
		self::array_dates($results);
		
		$upcoming = self::order_dates($results);

		return $upcoming;
	}

	private static function order_dates($obj_array){
		global $wpdb;
		$upcoming = array();
		
		//find all the dates first
		foreach($obj_array as $obj){
		//ddprint($obj);
			if($obj->dates != ''){
				foreach($obj->dates as $date => $id_array){
					if(array_key_exists($date,$upcoming)){
						$upcoming[$date] = array_merge($upcoming[$date],$id_array);
					}else{
						$upcoming[$date] = $id_array;
					}
				}
			}
		}
		
		//sort array by date
		ksort($upcoming);
		
		//populate the array values with objects (overwrite the ids)
		foreach($upcoming as &$id_array){
			foreach($id_array as &$id){
				$id = $obj_array[$id];
				$id->dates = null;
			}
		}
		
		//function to usort() the array with the showtimes in order
		if(!function_exists('hc_production_time_sort')){
			function hc_production_time_sort($a,$b){
				if($a->time==$b->time){
					return 0;
				}
				return ($a->time > $b->time) ? 1 : -1;
			}
		}
		foreach($upcoming as &$object_array){
			usort($object_array,'hc_production_time_sort');		
		}
				
		return $upcoming;
	}
	
	
	private static function array_dates($object_array){
		foreach($object_array as $type_data){
			//ddprint($type_data);
			$year = date('Y'); //May as well set a default. It will cause problems if the dates haven't been specified in production pages.
			$dates_array = array();	
			if($type_data->dates != ''){
				//parse the dates
				$dates_array = explode("\n",trim($type_data->dates));
				//ddprint($dates_array);
								
				$dates_array2 = array();
				foreach($dates_array as $date_line){
					if(preg_match('/^\d{4}.*/sm', $date_line) == 1){
						//This is a line telling us the year.
						$year = trim($date_line);
						continue;
					}
					$first_space_pos = strpos($date_line,' ');					
					$month_name = substr($date_line,0,$first_space_pos);
					$month_dates = substr($date_line,$first_space_pos);
					
					//strip whitespace and extra commas
					$month_dates = preg_replace(array('/\s/','/,,/'),array('',','),trim($month_dates));
					$month_dates = trim($month_dates,',');
					
					$month_dates_exp = explode(',',$month_dates);
					foreach($month_dates_exp as $date){
						if($date == ''){
							//would cause a false addition of today's date to the calendar. So skip it.
							continue;
						}
						$dates_array2[date('Y-m-d', strtotime("$month_name $date, $year"))] = array($type_data->id);
					}
				}
				$type_data->dates = $dates_array2;
				
			}else{
				// TODO use other date fields
				// This section needs a mechanism to prevent endless loop of dates that run into infinity

			}
		}
		return $object_array;
	}
}