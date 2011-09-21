<?php

class Topspin_CP {
	var $version = '0.1.0';
	
	function Topspin_CP( $switch = TRUE ) {
		global $IN, $DB;
		
		//check that topspin module is installed
		$module_query = $DB->query('SELECT module_id FROM exp_modules WHERE module_name = "Topspin"');
		if(!$module_query->num_rows) {
			return;
		}
		
		//check that config has been entered
		$sql = "SELECT `api_key`, `username`, `artist_id` FROM exp_topspin ORDER BY id ASC LIMIT 1";
		$config_vars = $DB->query($sql);
		if($config_vars->num_rows == 0 && $IN->GBL('P') != 'update_general_config') return $this->general_config();
		if(($config_vars->row['api_key'] == '' || $config_vars->row['username'] == '' || $config_vars->row['artist_id'] == '') && $IN->GBL('P') != 'update_general_config') return $this->general_config();
		
		//check that pages is installed
		$pages_query = $DB->query('SELECT module_id FROM exp_modules WHERE module_name = "Pages"');
		if($pages_query->num_rows == 0) {
			$r .= $DSP->error_message($LANG->line('install_pages')).BR;
		}
		
		if ($switch)
		{
		    switch($IN->GBL('P'))
		    {
		    	case 'clear_cached_offers'		: $this->clear_cached_offers();
		    		break;
		    	case 'store'					: $this->store_config();
		    		break;
				case 'add_new_store'			: $this->store_config();
					break;
				case 'general_config'			: $this->general_config();
					break;
				case 'update_general_config' 	: $this->update_general_config();
					break;
				case 'update_store'				: $this->update_store();
					break;
				case 'delete_store'				: $this->delete_store();
					break;
				case 'update_artist_list'		: $this->update_artist_list();
					$this->general_config();
					break;
				default 						: $this->topspin_home();
					break;
			}
		}
	}
	
	function topspin_home() {
		global $IN, $DSP, $DB, $LANG;
		
		$DSP->title = $LANG->line('topspin_module_name');
		$DSP->crumb = $DSP->anchor(BASE.
		                           AMP.'C=modules'.
		                           AMP.'M=topspin',
		                           $LANG->line('topspin_module_name'));
		
				
		return $this->general_config();
	}
	
	function content_wrapper( $highlight = null ) {
			global $IN, $DB, $DSP, $LANG;
			
			$DSP->title = $LANG->line('topspin_module_name');
			
			$nav_array = array(	'general_config' => array('P' => 'general_config','title'=>$LANG->line('general_config')));
			
			$stores_query = $DB->query('SELECT id, name FROM exp_topspin_stores');
			foreach($stores_query->result as $row) 
			{
				$nav_array['store_'.$row['id']] = array('P' => 'store', 'num' => $row['id'], 'title' => stripslashes($row['name']));
			}
	
			$nav_array['add_new_store']	= array('P' => 'add_new_store', 'title'=>$LANG->line('add_new_store'));
			
	/*		$nav_array['demo'] = array('P' => 'demo', 'title'=>'demo');
				$nav_array['demo2'] = array('P' => 'demo2', 'title'=>'demo2');*/
			
			$nav = $this->nav($nav_array, $highlight);
			
			if ($nav != '')
			{
				$DSP->body .= $nav;
			}
		
		}
		
		
		 /** -----------------------------------
		 /**  Navigation Tabs
		 /** -----------------------------------*/
		
		function nav($nav_array, $highlight = null)
		{
			global $IN, $DSP, $PREFS, $REGX, $FNS, $LANG;
		                
			/** -------------------------------
			/**  Build the menus
			/** -------------------------------*/
			// Equalize the text length.
			// We do this so that the tabs will all be the same length.
				
			$temp = array();
			foreach ($nav_array as $k => $v)
			{
				//$temp[$k] = $LANG->line($k);
				$temp[$k] = $v['title'];
			}
			$temp = $DSP->equalize_text($temp);
		
			//-------------------------------
			$page = ($highlight == null ? $IN->GBL('P') : $highlight);
		    $r = <<<EOT
		        
		        <script type="text/javascript"> 
		        <!--
		
				function styleswitch(link)
				{                 
					if (document.getElementById(link).className == 'altTabs')
					{
						document.getElementById(link).className = 'altTabsHover';
					}
				}
			
				function stylereset(link)
				{                 
					if (document.getElementById(link).className == 'altTabsHover')
					{
						document.getElementById(link).className = 'altTabs';
					}
				}
				
				-->
				</script>
				
				
EOT;
		    
			$r .= $DSP->table_open(array('width' => '100%'));
		
			$nav = array();
			foreach ($nav_array as $key => $val)
			{
				$url = '';
				
				if (is_array($val))
				{
					$url = BASE.AMP.'C=modules'.AMP.'M=topspin';		
					
					foreach ($val as $k => $v)
					{
						if($k != 'title' && $v != null) $url .= AMP.$k.'='.$v;
					}					
					$title = $val['title'];
					
				}
		
				$url = ($url == '') ? $val : $url;
		
				$div = ($page == $key) ? 'altTabSelected' : 'altTabs';
				$linko = '<div class="'.$div.'" id="'.$key.'"  onclick="navjump(\''.$url.'\');" onmouseover="styleswitch(\''.$key.'\');" onmouseout="stylereset(\''.$key.'\');">'.$title.'</div>';
					
				$nav[] = array('text' => $DSP->anchor($url, $linko));
			}
		
			$r .= $DSP->table_row($nav);		
			$r .= $DSP->table_close();
		
			return $r;          
	    }
	    /* END */
	
	function general_config($msg = '') {
		global $IN, $DSP, $DB, $LANG, $PREFS;
		
		$this->content_wrapper('general_config');
		$DSP->crumb .= $DSP->crumb_item($LANG->line('general_config'));    
		
		$r = '';
		if ($msg != '') $r .= $DSP->qdiv('success', $msg).BR;
			
		//get config variables
		$site_id = $PREFS->ini('site_id');
		$sql = "SELECT `id`, `api_key`, `username`, `artist_id`, `artists_data`, `update_offers`, `twitter_username`, `twitter_message`
				FROM exp_topspin WHERE id = ".$site_id." ORDER BY id ASC LIMIT 1";
		$config_vars = $DB->query($sql);
		if($config_vars->num_rows == 0) {
			$api_key = '';
			$username = '';
			$artist_id = '';
			$artists_data = array();
			$update_offers = '';
			$twitter_username ='';
			$twitter_message ='';
		} else {
			$api_key = $config_vars->row['api_key'];
			$username = $config_vars->row['username'];
			$artist_id = $config_vars->row['artist_id'];
			$artists_data = unserialize(stripslashes($config_vars->row['artists_data']));
			$update_offers = $config_vars->row['update_offers'];
			$twitter_username = $config_vars->row['twitter_username'];
			$twitter_message = $config_vars->row['twitter_message'];
		}
		
		// Declare Form
		$r .= $DSP->form('C=modules'.AMP.'M=topspin'.AMP.'P=update_general_config','target');
		//Table Heading
		$r .= $DSP->qdiv('tableHeading', $LANG->line('general_config')); 
		
		//  config variable list
		$r .= $DSP->table('tableBorder', '0', '0', '100%'); 
		//api key
		$r .= $DSP->tr().
		$DSP->table_qcell('tableCellOne', $LANG->line('api_key')).
		$DSP->table_qcell('tableCellTwo', $DSP->input_text('api_key', $api_key,'255','255','input','70%')).
		$DSP->tr_c();
		//username
		$r .= $DSP->tr().
		$DSP->table_qcell('tableCellOne', $LANG->line('username')).
		$DSP->table_qcell('tableCellTwo', $DSP->input_text('username', $username,'255','255','input','70%')).
		$DSP->tr_c();
		
		//display artist options if api_key & username are present
		if(count($artists_data) > 0) {
			//artist_id
			$r .= $DSP->tr().
				$DSP->table_qcell('tableCellOne', $LANG->line('choose_artist'));
				
			$artist_select = $DSP->input_radio('artist_id','0',($artist_id == '0' || $artist_id == '' ? 1 : 0)).
					$DSP->qspan('',$LANG->line('all_artists')).
					$DSP->br();
			foreach($artists_data as $artist) 
			{
				if(preg_match('/\/(\d+)$/', $artist['url'], $matches)) {
					$artist_option_id = $matches[1];
	            	$artist_select .= $DSP->input_radio('artist_id',$artist_option_id, ($artist_id == $artist_option_id ? 1 : 0)).
	            		$DSP->qspan('',$artist['name']).
	            		$DSP->br();
	            }
            }
			$r .= $DSP->table_qcell('tableCellTwo',$artist_select).
					$DSP->tr_c();
		}
		
		$r .= $DSP->tr().
			$DSP->table_qcell('tableCellOne', $LANG->line('update_offers_automatically')).
			$DSP->table_qcell('tableCellTwo', $DSP->input_checkbox('update_offers','1', ($update_offers == 1 ? 1 : 0))).
			$DSP->tr_c().
			$DSP->tr().
			$DSP->table_qcell('tableCellOne', $LANG->line('twitter_username')).
			$DSP->table_qcell('tableCellTwo', $DSP->input_text('twitter_username',$twitter_username,20,60)).
			$DSP->tr_c().
			$DSP->tr().
			$DSP->table_qcell('tableCellOne', $LANG->line('twitter_message')).
			$DSP->table_qcell('tableCellTwo', $DSP->input_textarea('twitter_message',$twitter_message,5,60).
				$DSP->br().
				$DSP->qspan('',$LANG->line('twitter_tokens'))).
			$DSP->tr_c().
			$DSP->table_c();       
			 
		//  Submit Button Text - Add/Update
		$r .= $DSP->qdiv('itemWrapper', BR.$DSP->input_submit($LANG->line('save_config')));    
			        
		//  Close Form
		$r .= $DSP->form_c();
		
		//reload artist list button
		if($api_key != '' && $username != '') {
			$r .= $DSP->form('C=modules'.AMP.'M=topspin'.AMP.'P=update_artist_list','target').
			$DSP->qdiv('itemWrapper', BR.$DSP->input_submit($LANG->line('reload_artist_list'))).
			$DSP->form_c();
		}
		
		$DSP->body .= $r;
		
	}
	
	function update_general_config() 
	{
		global $DB, $IN, $LANG;
		
		$username = $DB->escape_str($IN->clean_input_data($IN->GBL('username')));
		$api_key = $DB->escape_str($IN->clean_input_data($IN->GBL('api_key')));
		$artist_id = $DB->escape_str($IN->clean_input_data($IN->GBL('artist_id')));
		$update_offers = $DB->escape_str($IN->clean_input_data($IN->GBL('update_offers')));
		$update_offers = ($update_offers == '1' ? '1' : '0');
		$twitter_username = $DB->escape_str($IN->clean_input_data($IN->GBL('twitter_username')));
		$twitter_message = $DB->escape_str($IN->clean_input_data($IN->GBL('twitter_message')));
		
		//check for empty post?
		if(count($_POST) == 0) return $this->general_config(); 
		
		$data = array('username'		=>	$username,
					'api_key'			=>	$api_key, 
					'artist_id'			=>	$artist_id, 
					'update_offers'		=>	$update_offers,
					'twitter_username'	=>	$twitter_username,
					'twitter_message'	=>	$twitter_message,
					'offers_timestamp'	=>  0);
		
		$sql = "SELECT id FROM exp_topspin ORDER BY id ASC LIMIT 1";
		$config_vars = $DB->query($sql);
	
		if($config_vars->num_rows == 0) {
			$DB->query($DB->insert_string('exp_topspin', $data));
		} else {
			$DB->query($DB->update_string('exp_topspin', $data, "id = ".$config_vars->row['id']));
		}
	
		$this->update_artist_list();
		
		$DB->query('UPDATE exp_topspin_stores SET store_data_timestamp = 0');
		
		return $this->general_config($LANG->line('config_saved'));	
	
	}
	
	function store_config($msg = '', $error = '', $store_id = 0) 
	{
		global $DSP, $DB, $IN, $LANG, $PREFS, $FNS;
		
		if($store_id == 0) {
			$store_id = $DB->escape_str($IN->clean_input_data($IN->GBL('num')));
			$store_id = (is_numeric($store_id) ? $store_id : '0');
		}
		
		$store_config = $DB->query("SELECT name, uri, store_data, `template`, `rows_pp`, `offer_types`, `tags`, `sort_direction`, `detail_pages` 
				FROM exp_topspin_stores WHERE id = ".$store_id);
		
		$DSP->extra_css = PATH.'modules/topspin/topspin_cp.css';		
		
		if($store_config->num_rows ==0) {
			
			$this->content_wrapper();
			
			$DSP->title = $LANG->line('topspin_module_name');
			$DSP->crumb = $DSP->anchor(BASE.
			                           AMP.'C=modules'.
			                           AMP.'M=topspin',
			                           $LANG->line('topspin_module_name'));
			$DSP->crumb .= $DSP->crumb_item($LANG->line('add_new_store')); 
			
			$store_name = '';
			$template = '';
			$rows_pp = 1;
			$offer_types = array();
			$tags = array();
			$sort = 'asc';
			$detail_pages = '0';
			
		} else {
		
			$this->content_wrapper('store_'.$store_id);
			
			$DSP->title = $LANG->line('topspin_module_name');
			$DSP->crumb = $DSP->anchor(BASE.
			                           AMP.'C=modules'.
			                           AMP.'M=topspin',
			                           $LANG->line('topspin_module_name'));
			$DSP->crumb .= $DSP->crumb_item($store_config->row['name']);
		
			$store_name = $store_config->row['name'];
			$store_uri = $store_config->row['uri'];
			$template = $store_config->row['template'];
			$rows_pp = $store_config->row['rows_pp'];
			$offer_types = (!unserialize($store_config->row['offer_types']) ? array() : unserialize($store_config->row['offer_types']));
			$tags = (!unserialize($store_config->row['tags']) ? array() : unserialize($store_config->row['tags']));
			$sort = $store_config->row['sort_direction'];
			$detail_pages = $store_config->row['detail_pages'];
				
		}
		
		
		$offers = $this->get_offers();
		$tag_list = $this->get_tags($offers);
		//$offer_type_options = array('buy_button','email_for_media','bundle_widget','single_track_player_widget','fb_for_media');
		$offer_type_options = array('buy_button','email_for_media','single_track_player_widget','fb_for_media');
		
		$r = '';
		if ($msg != '') $r .= $DSP->qdiv('success', $msg).BR;
		if ($error != '') $r .= $DSP->qdiv('errormessage', $error).BR;
		
		// Declare Form
		$r .= $DSP->form('C=modules'.AMP.'M=topspin'.AMP.'P=update_store'.($store_id > 0 ? AMP.'num='.$store_id : ''),'target');
		//Table Heading
		if($store_id > 0) {
			$table_heading = $store_config->row['name'].$DSP->qdiv('float-right',$DSP->anchor($store_config->row['uri'],$LANG->line('visit_store'),'',true));
		} else {
			$table_heading = $LANG->line('add_new_store');
		}
		$r .= $DSP->qdiv('tableHeading', $table_heading ); 
		
		//  config variable list
		$r .= $DSP->table('tableBorder', '0', '0', '100%'); 
		//api key
		$r .= $DSP->tr().
		$DSP->table_qcell('tableCellOne', $LANG->line('store_name')).
		$DSP->table_qcell('tableCellTwo', $DSP->input_text('store_name', $store_name,'255','255','input','70%')).
		$DSP->tr_c();
		//username
		$r .= $DSP->tr().
		$DSP->table_qcell('tableCellOne', $LANG->line('store_uri')).
		$DSP->table_qcell('tableCellTwo', $DSP->input_text('store_uri', $store_uri,'255','255','input','70%')).
		$DSP->tr_c();
		//template choice
		$r .= $DSP->tr().
		$DSP->table_qcell('tableCellOne', $LANG->line('choose_template')).
		$DSP->table_qcell('tableCellTwo', $DSP->input_select_header('template').
			$DSP->input_select_option('light','Light',($template == 'light' ? 1 : 0)).
			$DSP->input_select_option('dark','Dark',($template == 'dark' ? 1 : 0)).
			$DSP->input_select_option('custom','Custom',($template == 'custom' ? 1 : 0)).
			$DSP->input_select_footer()
		).
		$DSP->tr_c();
		//Rows per page
		$r .= $DSP->tr().
		$DSP->table_qcell('tableCellOne', $LANG->line('rows_per_page'));
		$row_options = '';
		for($i = 1; $i<=15; $i++) {
			$row_options .= $DSP->input_select_option($i,$i,($rows_pp == $i ? 1 : 0));
		}
		$r .= $DSP->table_qcell('tableCellTwo', $DSP->input_select_header('rows_pp').
			$row_options.
			$DSP->input_select_footer()
		).
		$DSP->tr_c();
		//Offer type checklist
		$offer_type_checkboxes ='';
		foreach($offer_type_options as $ot) {
			$offer_type_checkboxes .= $DSP->input_checkbox('offer_types[]',$ot,(array_search($ot,$offer_types) !== false ? 1 : 0)).$DSP->qspan('',$LANG->line($ot)).$DSP->br();
		}
		$r .= $DSP->tr().
			$DSP->table_qcell('tableCellOne', $LANG->line('offer_types')).
			$DSP->table_qcell('tableCellTwo', $offer_type_checkboxes).
			$DSP->tr_c();
		//tag checklist
		$tag_checkboxes = '';
		foreach($tag_list as $t) {
			$tag_checkboxes .= $DSP->input_checkbox('tags[]',$t,(array_search($t,$tags) !== false ? 1 : 0)).$DSP->qspan('',ucwords(str_replace('_',' ',$t))).$DSP->br();
		}
		$r .= $DSP->tr().
			$DSP->table_qcell('tableCellOne', $LANG->line('tags')).
			$DSP->table_qcell('tableCellTwo', $tag_checkboxes.$DSP->br().$DSP->qspan('',$LANG->line('tags_note'))).
			$DSP->tr_c();
		//	sort direction
		$r .= $DSP->tr().
			$DSP->table_qcell('tableCellOne', $LANG->line('sort_direction')).
			$DSP->table_qcell('tableCellTwo', $DSP->input_select_header('sort').
				$DSP->input_select_option('asc','Lowest',($sort == 'asc' ? 1 : 0)).
				$DSP->input_select_option('desc','Highest',($sort == 'desc' ? 1 : 0)).
				$DSP->input_select_footer()
			).
			$DSP->tr_c();	
		// detail pages
		$r .= $DSP->tr().
			$DSP->table_qcell('tableCellOne', $LANG->line('create_detail_pages')).
			$DSP->table_qcell('tableCellTwo', $DSP->input_checkbox('detail_pages','1',($detail_pages == '1' ? 1 : 0))).
			$DSP->tr_c();
		
		$r .= $DSP->table_c();       
			
		//  Submit Button Text - Add/Update
		$r .= $DSP->qdiv('itemWrapper', BR.$DSP->input_submit($LANG->line(($store_id > 0 ? 'save_store' : 'create_new_store'))));    
		//  Close Form
		$r .= $DSP->form_c();
		
		// detele Store button
		if($store_id > 0) {
			$r .= $DSP->form('C=modules'.AMP.'M=topspin'.AMP.'P=delete_store'.AMP.'num='.$store_id,'target');
			$r .= $DSP->qdiv('itemWrapper', BR.$DSP->input_submit($LANG->line('delete_store'),'delete_store','id="delete_store_btn"'));
			$r .= $DSP->form_c();
			$r .= <<<EOT
			    <script type="text/javascript"> 
			    <!--
			    $(function() {
			    	$('#delete_store_btn').click(function(e) {
			    		e.preventDefault();
			    		if(confirm("Delete {$store_config->row['name']}?")) {
			    			$(this).closest('form').submit();
			    		}
			    	});
			    });
				-->
				</script>
EOT;
		}
		
		$DSP->body .= $r;	
	}
	
	function update_store() 
	{
		global $DB, $IN, $PREFS, $LOC, $SESS, $FNS, $REGX, $LANG;
		
		$store_id = $DB->escape_str($IN->clean_input_data($IN->GBL('num')));
		$store_id = (is_numeric($store_id) ? $store_id : '0');
		$store_name = $DB->escape_str($IN->clean_input_data($IN->GBL('store_name')));
		$store_uri = $DB->escape_str($IN->clean_input_data($IN->GBL('store_uri')));
		if(substr($store_uri,-1) != '/') $store_uri .= '/';
		$template = $DB->escape_str($IN->clean_input_data($IN->GBL('template')));
		$rows_pp  = $DB->escape_str($IN->clean_input_data($IN->GBL('rows_pp')));
		$offer_types  = $DB->escape_str($IN->clean_input_data($IN->GBL('offer_types')));
		$tags  = $DB->escape_str($IN->clean_input_data($IN->GBL('tags')));
		$sort_direction  = $DB->escape_str($IN->clean_input_data($IN->GBL('sort')));
		$detail_pages  = $DB->escape_str($IN->clean_input_data($IN->GBL('detail_pages')));
		$detail_pages = ($detail_pages == '1' ? '1' : '0');
		
		if($store_name == '' || $store_uri == '') {
			return $this->store_config('','Name and URI are required');
		} 
		
		$site_id = $PREFS->ini('site_id');
		
		$pages_var = $this->get_pages_vars();
		
		$store_data = array('name' => $store_name, 
							'uri' => $store_uri, 
							'weblog_id' => $pages_var['weblog_id'], 
							'template_id' => ($template == 'dark' ? $pages_var['dark_template_id'] : $pages_var['light_template_id']), 
							'template'=>$template, 
							'rows_pp'=>$rows_pp, 
							'offer_types'=>serialize($offer_types), 
							'tags'=>serialize($tags),
							'sort_direction'=>$sort_direction,
							'detail_pages' => $detail_pages,
							'store_data_timestamp' => '0'
							);
		
		
		$store_config = $DB->query("SELECT name, uri, store_data, `weblog_id`, `template_id`, `entry_id`, `template`, `rows_pp`, `offer_types`, `tags`, `sort_direction`, `detail_pages` 
				FROM exp_topspin_stores WHERE id = ".$store_id);
				
		if($store_config->num_rows ==0) {
			//create pages entries
			$weblog_data = array('site_id'=>$site_id, 'weblog_id'=>$pages_var['weblog_id'], 'author_id' => $SESS->userdata['member_id'], 'ip_address' => $IN->IP, 'title'=> $store_name, 'url_title' => $REGX->create_url_title($store_name), 'status' => 'open', 'entry_date' => $LOC->now, 'year' => gmdate('Y', $LOC->now), 'month' => gmdate('m', $LOC->now), 'day' => gmdate('d', $LOC->now), 'edit_date' => gmdate('YmdHis', $LOC->now));
			$DB->query($DB->insert_string('exp_weblog_titles',$weblog_data));
			$entry_id = $DB->insert_id;
			
			$weblog_data = array('entry_id' => $entry_id, 'site_id' => $site_id, 'weblog_id'=> $pages_var['weblog_id']);
			$DB->query($DB->insert_string('exp_weblog_data',$weblog_data));
			
			$store_data['entry_id'] = $entry_id;
			$DB->query($DB->insert_string('exp_topspin_stores',$store_data));
			$store_id = $DB->insert_id;
			
		} else {
			$entry_id = $store_config->row['entry_id'];
			$weblog_data = array('weblog_id'=>$pages_var['weblog_id'],
						'title' => $store_name,
						'url_title'=>$REGX->create_url_title($store_name),
						'edit_date' => gmdate('YmdHis', $LOC->now)
						);
			$DB->query($DB->update_string('exp_weblog_titles',$weblog_data,'entry_id = '.$entry_id));
			
			$store_data['entry_id'] = $entry_id;
			$DB->query($DB->update_string('exp_topspin_stores',$store_data,'id = '.$store_id));
		}
				
		$this->save_pages_config($store_uri,$store_data['template_id'],$entry_id);
		
		return $this->store_config($LANG->line('config_saved'),'',$store_id);
	}
	
	function delete_store() {
		global $DB, $IN, $PREFS;
		
		$store_id = $DB->escape_str($IN->clean_input_data($IN->GBL('num')));
		$store_id = (is_numeric($store_id) ? $store_id : '0');
		
		$store_config = $DB->query("SELECT name, uri, store_data, `weblog_id`, `template_id`, `entry_id`, `template`, `rows_pp`, `offer_types`, `tags`, `sort_direction`, `detail_pages` FROM exp_topspin_stores WHERE id = ".$DB->escape_str($store_id));
				
		if($store_config->num_rows) {
		 	if($store_config->row['entry_id'] > 0) {
				$DB->query('DELETE FROM exp_weblog_titles WHERE entry_id = '.$DB->escape_str($store_config->row['entry_id']));
				$DB->query('DELETE FROM exp_weblog_data WHERE entry_id = '.$DB->escape_str($store_config->row['entry_id']));
				$DB->query('DELETE FROM exp_topspin_stores WHERE entry_id = '.$DB->escape_str($store_config->row['entry_id']));
				
				$site_id = $PREFS->ini('site_id');
				
				//update pages config
				$pages_query = $DB->query('SELECT site_pages FROM exp_sites WHERE site_id = '.$DB->escape_str($site_id));
				if($pages_query->num_rows == 0) {
					//error == bad
				} else {
					$site_pages_raw = $pages_query->row['site_pages'];
					if($site_pages_raw == '' || (unserialize($site_pages_raw) == null)) {
						//no pages???
					} else {
						//update pages object
						$site_pages = unserialize($site_pages_raw);
						if(isset($site_pages[$site_id])) {
							//update existisg pages object
							$pages_config = $site_pages[$site_id];
							unset($pages_config['uris'][$store_config->row['entry_id']]);
							unset($pages_config['templates'][$store_config->row['entry_id']]);
							$site_pages[$site_id] = $pages_config;
							$DB->query($DB->update_string('exp_sites',array('site_pages'=>serialize($site_pages)),'site_id = '.$site_id));
						}
					}
					
				}
				
			}
		}
		return $this->general_config('Store deleted');
	}
	
	function get_offers() {
		global $DB, $PREFS;
		$site_id = $PREFS->ini('site_id');
		$topspin_config = $DB->query("SELECT `id`, `api_key`, `username`, `artist_id`, `offers_data`, `offers_timestamp`, `artists_data` 
				FROM exp_topspin WHERE id = ".$DB->escape_str($site_id)." ORDER BY id ASC LIMIT 1"); 
		
		//get topspin data from db or topspin
		if($topspin_config->row['offers_data'] == '' || (unserialize($topspin_config->row['offers_data']) == false) || $topspin_config->row['offers_timestamp'] < date('U')-80000) {
			require_once 'Topspin.php';
			
			$artist_ids = array();
			if($topspin_config->row['artist_id'] > 0) {
				$artist_ids[] = $topspin_config->row['artist_id'];
			} else {
				$artists_data = unserialize(stripslashes($topspin_config->row['artists_data']));
				if(count($artists_data) > 0) {
					foreach($artists_data as $artist) {
						if(preg_match('/\/(\d+)$/', $artist['url'], $matches)) {
							$artist_ids[] = $matches[1];
						}
					}
				}
			}
			$offers_final = array();
			
			foreach($artist_ids as $artist_id) {
				$t = new Topspin_curl($topspin_config->row['api_key'], $topspin_config->row['username'], $artist_id);
			
				$offers_obj = $t->getOffers(1);
				$offers = $offers_obj->offers;
				while($offers_obj->current_page < $offers_obj->total_pages) {
					$offers_obj = $t->getOffers($offers_obj->current_page+1);
					$offers = array_merge($offers,$offers_obj->offers);
				}
			
				foreach($offers as $o) {
					if($o->status == "active") {
						$offers_final[] = $o;	
					}
				}			
			}
			$DB->query($DB->update_string('exp_topspin',array('offers_data'=>serialize($offers_final), 'offers_timestamp'=>date('U')),'id = '.$topspin_config->row['id']));
			$offers = $offers_final;
		} else {
			$offers = unserialize($topspin_config->row['offers_data']);
		}
		return $offers;
	}
	
	function get_tags($offers) {
		$tags = array();
		foreach($offers as $o) {
			if(isset($o->tags)) {
				foreach($o->tags as $t) {
					if(array_search($t, $tags) === false) {
						$tags[] = $t;
					}
				}
			}
		}
		return $tags;
	}
	
	function clear_cached_offers() {
		global $DB, $IN, $LANG;
		$store_id = $DB->escape_str($IN->clean_input_data($IN->GBL('num')));
		
		if($store_id != '') {
			$topspin_config = $DB->query("SELECT `id`, `api_key`, `username`, `artist_id`,`offers_data` FROM exp_topspin ORDER BY id ASC LIMIT 1");
			$DB->query($DB->update_string('exp_topspin',array('offers_data'=>''),'id = '.$topspin_config->row['id']));
		}
		return $this->store_config($LANG->line('config_saved'));
	}
		
	function get_pages_vars() {
		global $DB, $PREFS, $LOC, $FNS;
		
		$site_id = $PREFS->ini('site_id');
		$site_url = $FNS->fetch_site_index();
		$pages_vars = array();
		//check that template group exists
		$template_group_query = $DB->query('SELECT group_id FROM exp_template_groups WHERE group_name = "_topspin_stores" AND site_id = '.$site_id);
		//file_put_contents('/tmp/template_group_query', mixed data, int flags, [resource context])
		if($template_group_query->num_rows == 0) {
			$max_group_order_query = $DB->query('SELECT max(group_order) as max_group_order FROM exp_template_groups WHERE site_id = '.$site_id);
			if($max_group_order_query->num_rows == 0) {
				$max_group_order = 0;
			} else {
				$max_group_order = $max_group_order_query->row['max_group_order'];
			}
			$data = array('site_id' => $site_id, 'group_name'=>'_topspin_stores','group_order'=>($max_group_order+1), 'is_site_default'=>'n', 'is_user_blog'=>'n');
			$DB->query($DB->insert_string('exp_template_groups',$data));
			$template_group_id = $DB->insert_id;
		} else {
			$template_group_id = $template_group_query->row['group_id'];
		}
		$pages_vars['template_group_id'] = $template_group_id;
		
		//make default templates if they don't exist
		//index
		$template_query = $DB->query('SELECT template_id FROM exp_templates WHERE site_id = '.$site_id.' AND template_name = "index" AND group_id = '.$template_group_id);
		if($template_query->num_rows == 0) {
			$data = array('site_id'=>$site_id, 'group_id'=>$template_group_id, 'template_name'=>'index', 'save_template_file'=>'n', 'template_type'=>'webpage', 'template_data'=>'This page left intentionally blank. =)', 'edit_date'=>$LOC->now);
			$DB->query($DB->insert_string('exp_templates',$data));
			$template_id = $DB->insert_id;
		} else {
			$template_id = $template_query->row['template_id'];
		}
		//light
		$template_query = $DB->query('SELECT template_id FROM exp_templates WHERE site_id = '.$site_id.' AND template_name = "light" AND group_id = '.$template_group_id);
		if($template_query->num_rows == 0) {
			$data = array('site_id'=>$site_id, 'group_id'=>$template_group_id, 'template_name'=>'light', 'save_template_file'=>'n', 'template_type'=>'webpage', 'template_data'=>file_get_contents($PREFS->ini('theme_folder_url').'third_party/topspin/default_light.html'), 'edit_date'=>$LOC->now);
			$DB->query($DB->insert_string('exp_templates',$data));
			$light_template_id = $DB->insert_id;
		} else {
			$light_template_id = $template_query->row['template_id'];
		}
		$pages_vars['light_template_id'] = $light_template_id;
		//dark
		$template_query = $DB->query('SELECT template_id FROM exp_templates WHERE site_id = '.$site_id.' AND template_name = "dark" AND group_id = '.$template_group_id);
		if($template_query->num_rows == 0) {
			$data = array('site_id'=>$site_id, 'group_id'=>$template_group_id, 'template_name'=>'dark', 'save_template_file'=>'n', 'template_type'=>'webpage', 'template_data'=>file_get_contents($PREFS->ini('theme_folder_url').'third_party/topspin/default_dark.html'), 'edit_date'=>$LOC->now);
			$DB->query($DB->insert_string('exp_templates',$data));
			$dark_template_id = $DB->insert_id;
		} else {
			$dark_template_id = $template_query->row['template_id'];
		}
		$pages_vars['dark_template_id'] = $dark_template_id;
		
		$weblog_query = $DB->query('SELECT weblog_id FROM exp_weblogs WHERE site_id = '.$site_id.' AND blog_name like "topspin_stores"');
		if($weblog_query->num_rows == 0) {
			$data = array('site_id' => $site_id, 'blog_name' => 'topspin_stores', 'blog_title' => 'Topspin Stores - Do not edit', 'blog_url' => $site_url, 'blog_lang' => 'en', 'blog_encoding' => 'utf-8');
			$DB->query($DB->insert_string('exp_weblogs', $data)); 
			$weblog_id = $DB->insert_id;
		} else {
			$weblog_id = $weblog_query->row['weblog_id'];
		}
		$pages_vars['weblog_id'] = $weblog_id;
		
		return $pages_vars;
	}
	
	function save_pages_config($store_uri,$template_id,$entry_id) {
		global $DB, $PREFS, $FNS;
		
		$site_id = $PREFS->ini('site_id');
		$site_url = $FNS->fetch_site_index();
		
		//update pages config
		$pages_query = $DB->query('SELECT site_pages FROM exp_sites WHERE site_id = '.$site_id);
		if($pages_query->num_rows == 0) {
			//error == bad
		} else {
			$site_pages_raw = $pages_query->row['site_pages'];
			if($site_pages_raw == '' || (unserialize($site_pages_raw) == null)) {
				//build new pages object
				$pages_config = array('uris' => array($entry_id=>$store_uri),
										'templates' => array($entry_id=>(string)$template_id),
										'url' => $site_url);
				$site_pages = array($site_id => $pages_config);
			} else {
				//update pages object
				$site_pages = unserialize($site_pages_raw);
				if(isset($site_pages[$site_id])) {
					//update existisg pages object
					$pages_config = $site_pages[$site_id];
					$pages_config['uris'][$entry_id] = $store_uri;
					$pages_config['templates'][$entry_id] = (string)$template_id;
					$site_pages[$site_id] = $pages_config;
				} else {
					//add new site to existing pages object
					$pages_config = array('uris' => array($entry_id=>$store_uri),
											'templates' => array($entry_id=>(string)$template_id),
											'url' => $site_url);
					$site_pages[$site_id] = $pages_config;
				}
			}
			$DB->query($DB->update_string('exp_sites',array('site_pages'=>serialize($site_pages)),'site_id = '.$site_id));
		}
	}
	
	function update_artist_list() 
	{
		global $DB, $PREFS;
		
		$site_id = $PREFS->ini('site_id');
		$sql = "SELECT `id`, `api_key`, `username`, `artist_id` FROM exp_topspin WHERE id = ".$DB->escape_str($site_id)." ORDER BY id ASC LIMIT 1";
		$config_vars = $DB->query($sql);
		if($config_vars->num_rows == 0) return array();
		
		$artists = array();
		
		$ch = curl_init();
		$currentPage = 0;
		$totalPages = 1;
		do {
			curl_setopt($ch, CURLOPT_URL,'http://app.topspin.net/api/v1/artist?page='.($currentPage+1));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_USERPWD, $config_vars->row['username'] . ':' . $config_vars->row['api_key']);
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
			$response = curl_exec($ch);
			$responseInfo = curl_getinfo($ch);
			$responseError = curl_error($ch);
			
			if(!$responseError) {
				$artist_data = json_decode($response, true);
				$currentPage = $artist_data['current_page'];
				$totalPages = $artist_data['total_pages'];
				foreach($artist_data['artists'] as $artist) {
					$artists[] = $artist;
				}
			} else {
				//needs more intelligent error handling
				return array();
			}
		} while ($currentPage < $totalPages);
		curl_close($ch);
		
		$data = array('artists_data' => addslashes(serialize(array_reverse($artists))), 'offers_timestamp'=>0);
		
		$DB->query($DB->update_string('exp_topspin', $data, "id = ".$config_vars->row['id']));
		
		//return $artists;
	
	}
	

    // --------------------------------
    //  Module installer
    // --------------------------------

    function topspin_module_install()
    {
        global $DB;        
        
        $sql[] = "CREATE TABLE `exp_topspin` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `api_key` varchar(255) DEFAULT NULL,
          `username` varchar(255) DEFAULT NULL,
          `artist_id` varchar(255) DEFAULT NULL,
          `artists_data` text,
          `offers_data` mediumtext,
          `offers_timestamp` int(11) NOT NULL DEFAULT '0',
          `update_offers` tinyint(1) NOT NULL DEFAULT '0',
          `twitter_username` varchar(255) DEFAULT NULL,
          `twitter_message` mediumtext,
          PRIMARY KEY (`id`)
        );";
								       
		$sql[] = "CREATE TABLE `exp_topspin_stores` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `name` varchar(255) DEFAULT NULL,
		  `uri` varchar(255) DEFAULT NULL,
		  `weblog_id` int(11) NOT NULL DEFAULT '0',
		  `template_id` int(11) NOT NULL DEFAULT '0',
		  `entry_id` int(11) NOT NULL DEFAULT '0',
		  `store_data` longtext,
		  `store_data_timestamp` int(11) NOT NULL DEFAULT '0',
		  `template` varchar(50) DEFAULT NULL,
		  `rows_pp` int(3) NOT NULL DEFAULT '1',
		  `offer_types` varchar(255) DEFAULT NULL,
		  `tags` varchar(255) DEFAULT NULL,
		  `sort_direction` varchar(25) DEFAULT NULL,
		  `detail_pages` tinyint(1) NOT NULL DEFAULT '0',
		  PRIMARY KEY (`id`)
		);";
        
        $sql[] = "INSERT INTO exp_modules (module_id, 
                                           module_name, 
                                           module_version, 
                                           has_cp_backend) 
                                           VALUES 
                                           ('', 
                                           'Topspin', 
                                           '$this->version', 
                                           'y')";
                                           
       /* $sql[] = "INSERT INTO exp_actions (action_id, 
                                           class, 
                                           method) 
                                           VALUES 
                                           ('', 
                                           'Sfd_couchtube', 
                                           'add_uuid')";
         */                                  
        foreach ($sql as $query)
        {
            $DB->query($query);
        }
        
        return true;
    }
    // END
    
    // ----------------------------------------
    //  Module de-installer
    // ----------------------------------------

    function topspin_module_deinstall()
    {
        global $DB;    

        $query = $DB->query("SELECT module_id
                             FROM exp_modules 
                             WHERE module_name = 'Topspin'"); 
                
        $sql[] = "DELETE FROM exp_module_member_groups 
                  WHERE module_id = '".$query->row['module_id']."'";      
                  
        $sql[] = "DELETE FROM exp_modules 
                  WHERE module_name = 'Topspin'";
                  
        $sql[] = "DELETE FROM exp_actions 
                  WHERE class = 'Topspin'";
                  
        $sql[] = "DELETE FROM exp_actions 
                  WHERE class = 'Topspin_CP'";
        
        $sql[] = "DROP TABLE IF EXISTS `exp_topspin`";
        
        $sql[] = "DROP TABLE IF EXISTS `exp_topspin_stores`";
         
                  
       // $sql[] = "DROP TABLE IF EXISTS exp_fortunes";

        foreach ($sql as $query)
        {
            $DB->query($query);
        }

        return true;
    }
    // END
}

?>