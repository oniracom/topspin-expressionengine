<?php

class Topspin {

    var $return_data	= ''; 

    // -------------------------------------
    //  Constructor
    // -------------------------------------

    function Topspin()
    {
    }
    
    function template_title() {
    	global $IN,$DB;
    	
    	$pages_uri =  $DB->escape_str($IN->URI);
    	
    	$store_config = $DB->query("SELECT name FROM exp_topspin_stores WHERE uri = '".$pages_uri."'");
    	if($store_config->num_rows) {
    		return $store_config->row['name'];
    	}
    }
    
    function template_css() {
    	global $PREFS;
    	
    	return $PREFS->ini('theme_folder_url').'third_party/topspin/default_template.css';
    }
    
    function template_store_grid() {
    	global $IN, $DB, $PREFS;
    	
    	$detail_page = $IN->GBL('i');
    	if(is_numeric($detail_page) && $detail_page > 0) return '';
    	$tag = $IN->GBL('tag');
    	$page = (is_numeric($IN->GBL('p')) ? $IN->GBL('p') : '0');
    	
    	$topspin_config = $DB->query("SELECT `id`, `api_key`, `username`, `artist_id` 
    		FROM exp_topspin 
    		WHERE id = ".$DB->escape_str($PREFS->ini('site_id'))."
    		ORDER BY id ASC LIMIT 1"); 
    	if($topspin_config->num_rows == 0) {
    		return;
    	}

    	$pages_uri =  $DB->escape_str($IN->URI);
    	$store_config = $DB->query("SELECT rows_pp, detail_pages FROM exp_topspin_stores WHERE uri = '".$pages_uri."'");
    	if($store_config->num_rows == 0) {
    		return '';
    	} else {
    		$rows_pp = $store_config->row['rows_pp'];
    		$detail_pages = ($store_config->row['detail_pages'] == '1' ? '1' : '0'); 
    	}
   		$start = $rows_pp*2*$page;
   		$end = $rows_pp*2*($page+1)-1;
   		$store_offers = $this->get_store_offers();
    	if($end == 0) $end = count($store_offers)-1;
    	
    	
    	if($tag != '') {
    		$temp_offers = array();
    		foreach($store_offers as $o) {
    			if(isset($o->tags)) {
    				if(array_search($tag,$o->tags) !== false) {
    					$temp_offers[] = $o;
    				}
    			}
    		}
    		$store_offers = $temp_offers;
    	}
    	$total_pages = ceil(count($store_offers)/(2*$rows_pp));
    	$final_offers = array();
    	for($i=$start;$i<=min($end,count($store_offers)-1); $i++) {
    		if(isset($store_offers[$i])) $final_offers[] = $store_offers[$i];
    	}
    	
    	return $this->parse_store_grid($final_offers, $page, $total_pages, $detail_pages);
    }
    
    function parse_store_grid($store_offers, $page, $total_pages, $detail_pages) {
    	global $TMPL, $IN, $FNS;
    	$tagdata = $TMPL->tagdata;
    	
   
    	$tagdata = preg_split('/{row_begin}(.*){'.SLASH.'row_begin}/ims', $tagdata, -1, PREG_SPLIT_DELIM_CAPTURE);
    	$header = '';
    	$ouput = '';
    	$footer = '';
    	if(count($tagdata) > 2) {
    		$header = $tagdata[0];
    		$row_begin = $tagdata[1];
    		$tagdata = preg_split('/{row_end}(.*){'.SLASH.'row_end}/ims', $tagdata[2], -1, PREG_SPLIT_DELIM_CAPTURE);
    		if(count($tagdata) > 2) {
    			$footer = $tagdata[2];
    			$row_end = $tagdata[1];
    			$tagdata = preg_split('/({col[^}]*}.*){'.SLASH.'col}/imsU', $tagdata[0], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    			$cols_raw = array();
    			foreach($tagdata as $c) {
    				if(strlen(trim($c)) > 0) {
    					$cols_raw[] = trim($c);
    				}
    			}
    			$cols = array();
    			foreach($cols_raw as $col) {
    				if(preg_match('/{col\s+\w+="([^"]+)"}(.*)/ims', $col, $matches)) {
    					$col_titles = preg_split('/\|/ism', $matches[1],-1, PREG_SPLIT_NO_EMPTY);
    					foreach($col_titles as $title) {
    						$cols[$title] = $matches[2];
    					}
    				} else if (preg_match('/{col\s*}(.*)/ims', $col, $matches)) {
    					$cols['default'] = $matches[1];
     				}
    			}

    			if(count($cols) > 0) {
					//$col_text = $tagdata[1];
					$even = 0;
					$i = 0;
					foreach($store_offers as $o) {
						$cond = array();
						if(isset($cols[$o->offer_type])) {
							$col = $cols[$o->offer_type];
						} else {
							$col = (isset($cols['default']) ? $cols['default'] : '');
						}
						if(preg_match('/{switch="([^"]+)"}/ims', $col, $matches)) {
							$switch_options = preg_split('/\|/', $matches[1]);
							if($even == 0) $switch = (count($switch_options)>0 ? $switch_options[0] : '');
							if($even == 1) $switch = (count($switch_options)>1 ? $switch_options[1] : '');
							$temp = preg_replace('/{switch="[^"]+"}/ims', $switch, $col);	
							
						} else {
							$temp = col;
						}
	    				$temp = $TMPL->swap_var_single('item_price',($o->price > 0 ? '&#36;'.$o->price : ''), $temp);
	    				$temp = $TMPL->swap_var_single('item_name', $o->name, $temp);
	    				//$temp = $TMPL->swap_var_single('item_image', (isset($o->campaign->product->images) ? $o->campaign->product->images[0]->medium_url : $image = $o->poster_image), $temp);
	    				$temp = $TMPL->swap_var_single('item_image',  $o->poster_image, $temp);
	  
	    				if(isset($o->campaign->product->images) || $o->poster_image) {
	    					$cond['item_image'] = 'TRUE';
	    				} else {
	    					$cond['item_image'] = 'FALSE';
	    				}
	    				$temp = $TMPL->swap_var_single('item_description', $o->description, $temp);
	    				$temp = $TMPL->swap_var_single('item_embed', $o->embed_code, $temp);
	    				$detail_split = preg_split('/{('.SLASH.')?item_detail}/', $temp);
	    				$temp = $detail_split[0];
	    				for($i = 1; $i<count($detail_split); $i++) {
	    					if($i % 2) {
	    						$temp .= $TMPL->swap_var_single('link', $IN->URI.'?i='.$o->id, $detail_split[$i]);
	    					} else {
	    						$temp .= $detail_split[$i];
	    					}
	    				}
	    				if(preg_match('/{item_detail}(.*){'.SLASH.'item_detail}/ism',$temp,$matches)) {
	    					if($detail_pages == '1') {
		    					$item_detail = $TMPL->swap_var_single('link', $IN->URI.'?i='.$o->id, $matches[1]);
		    				} else {
		    					$item_detail ='';
		    				}
		    				$temp = preg_replace('/{item_detail}.*{'.SLASH.'item_detail}/ism', $item_detail, $temp);
	    				}
	    				$temp = $FNS->prep_conditionals($temp, $cond);	
	    				$ouput .= $temp;
	    				if($even == 0) $output .= $row_begin.$temp;
	    				if($even == 1) $output .= $temp.$row_end;
	    				$even = abs($even-1);
	    				//if($i > 10) break;
	    				$i++;
	    			}
	    			//empty "cell"
	    			if($even == 1) {
	    				$cond = array();
	    				if(isset($cols[$o->offer_type])) {
    						$col = $cols[$o->offer_type];
    					} else {
    						$col = (isset($cols['default']) ? $cols['default'] : '');
    					}
    					if(preg_match('/{switch="([^"]+)"}/ims', $col, $matches)) {
    						$switch_options = preg_split('/\|/', $matches[1]);
    						if($even == 0) $switch = (count($switch_options)>0 ? $switch_options[0] : '');
    						if($even == 1) $switch = (count($switch_options)>1 ? $switch_options[1] : '');
    						$temp = preg_replace('/{switch="[^"]+"}/ims', $switch, $col);	
    						
    					} else {
    						$temp = col;
    					}
    					$cond['item_image'] = 'FALSE';
	    				$temp = $TMPL->swap_var_single('item_price','', $temp);
	    				$temp = $TMPL->swap_var_single('item_name', '', $temp);
	    				$temp = $TMPL->swap_var_single('item_image', '', $temp);
	    				$temp = $TMPL->swap_var_single('item_description', '', $temp);
	    				$temp = $TMPL->swap_var_single('item_embed', '', $temp);
	    				//$temp = preg_replace('/{item_detail}.*{'.SLASH.'item_detail}/ism', '', $temp);
	    				$detail_split = preg_split('/{('.SLASH.')?item_detail}/', $temp);
	    				$temp = $detail_split[0];
	    				for($i = 1; $i<count($detail_split); $i++) {
	    					if($i % 2) {
	    						$temp .= '';
	    					} else {
	    						$temp .= $detail_split[$i];
	    					}
	    				}
	    				$temp = $FNS->prep_conditionals($temp, $cond);	
	    				
	    				$output .= $temp.$row_end;
	    			}
    			}
    		}
    	}
    	
    	if(preg_match('/{paginate}.*{'.SLASH.'paginate}/ism', $header, $matches)) {
    		$header = $this->parse_pagination($header,$page,$total_pages);
    	}
    	
    	if(preg_match('/{paginate}.*{'.SLASH.'paginate}/ism', $footer, $matches)) {
    		$footer = $this->parse_pagination($footer,$page,$total_pages);
    	}
    	return $header.$output.$footer;
    	
    }
    
    function parse_pagination($text,$page,$total_pages) {
    	global $IN, $TMPL;

    	$pages_uri =  $IN->URI;
    	$tag = $IN->GBL('tag');
    	if(preg_match('/{paginate}(.*){'.SLASH.'paginate}/ism', $text, $matches)) {
	    	if($total_pages > 1) {
		    	$output = $matches[1];
		    } else {
		    	$output = '';
		    }
	    	if(preg_match('/{paginate_left}(.*){'.SLASH.'paginate_left}/ism',$text,$matches)) {
	    		
	    		if($page > 0) {
		    		$link = $pages_uri.'?p='.($page-1);
		    		if($tag != '') {
		    			$link .= '&tag='.urlencode($tag);
		    		}
		    		$left = $TMPL->swap_var_single('link', $link, $matches[1]);	
		    		$output = preg_replace('/{paginate_left}.*{'.SLASH.'paginate_left}/ism', $left, $output);
		    	} else {
	    			$output = preg_replace('/{paginate_left}.*{'.SLASH.'paginate_left}/ism', '', $output);
	    		}
	    	}
	    	if(preg_match('/{paginate_right}(.*){'.SLASH.'paginate_right}/ism',$text,$matches)) {
	    		if($page < $total_pages-1) {
	    			$link = $pages_uri.'?p='.($page+1);
	    			if($tag != '') {
	    				$link .= '&tag='.urlencode($tag);
	    			}
	    			$right = $TMPL->swap_var_single('link', $link, $matches[1]);	
	    			$output = preg_replace('/{paginate_right}.*{'.SLASH.'paginate_right}/ism', $right, $output);
	    		} else {
	    			$output = preg_replace('/{paginate_right}.*{'.SLASH.'paginate_right}/ism', '', $output);
	    		}	
	    	}
	    	$output = preg_replace('/{paginate}.*{'.SLASH.'paginate}/ism', $output, $text);
    		return $output;
    	} else {
    		return $text;
    	}
    }
    
    function template_store_detail() {
    	global $IN;
    	$offer_id = $IN->GBL('i');
    	$store_offers = $this->get_store_offers();
    	foreach($store_offers as $o) {
    		if($o->id == $offer_id) {
    			return $this->parse_store_detail($o);
    		}
    	}
    }
	
	function parse_store_detail($o) {
		global $TMPL, $IN, $PREFS, $FNS;
		
		$temp = $TMPL->tagdata;
		$temp = $TMPL->swap_var_single('item_price',($o->price > 0 ? '&#36;'.$o->price : ''), $temp);
		$temp = $TMPL->swap_var_single('item_name', $o->name, $temp);
		//$temp = $TMPL->swap_var_single('item_image', (isset($o->campaign->product->images) ? $o->campaign->product->images[0]->medium_url : $image = $o->poster_image), $temp);
		$temp = $TMPL->swap_var_single('item_image',  $o->poster_image, $temp);
		$temp = $TMPL->swap_var_single('item_description', $o->description, $temp);
		$temp = $TMPL->swap_var_single('item_embed', $o->embed_code, $temp);
		$fb_uri = $FNS->remove_double_slashes($PREFS->ini('site_url').$IN->URI.'?i='.$o->id);
		$temp = $TMPL->swap_var_single('item_fb_like', '<iframe scrolling="no" frameborder="0" src="http://www.facebook.com/plugins/like.php?href='.urlencode($fb_uri).'&amp;layout=button_count&amp;show_faces=false&amp;width=90&amp;action=like&amp;colorscheme=light&amp;height=21" style="border: medium none; overflow: hidden; width: 90px; height: 21px;" allowtransparency="true"></iframe>', $temp);
		
		if(preg_match('/{item_extra_images}(.*){'.SLASH.'item_extra_images}/ism',$temp,$matches)) {
			$images = array();
			foreach($o->campaign->product->images as $image) {
				$images_temp = $TMPL->swap_var_single('small_photo', $image->small_url, $matches[1]);
				$images[] = $TMPL->swap_var_single('large_photo', $image->large_url, $images_temp);
			}
			
			$temp = preg_replace('/{item_extra_images}.*{'.SLASH.'item_extra_images}/ism', join('',$images), $temp);
		}
		
		$tweet_info = $this->get_twitter($o);
		if(count($tweet_info) > 0) {
			$twitter = '<a href="http://twitter.com/share" class="twitter-share-button" data-url="'.$fb_uri.'" data-text="'.$tweet_info['message'].'" data-count="horizontal" data-via="'.$tweet_info['username'].'">Tweet</a><script type="text/javascript" src="http://platform.twitter.com/widgets.js"></script>';
		} else {
			$twitter = '';
		}
		$temp = $TMPL->swap_var_single('item_tweet', $twitter, $temp);
		return $temp;
	} 
	
	    
    function template_tags() {
    	global $IN, $DB, $TMPL;

    	$detail_page = $IN->GBL('i');
    	$current_tag = $IN->GBL('tag');
    	if(is_numeric($detail_page) && $detail_page > 0) return '';	
    	$pages_uri =  $DB->escape_str($IN->URI);
    	
    	$store_offers = $this->get_store_offers();
    	$store_config = $DB->query("SELECT store_data, store_data_timestamp, offer_types, id, sort_direction, tags FROM exp_topspin_stores WHERE uri = '".$pages_uri."'");
    	if($store_config->num_rows) {
	    	$tags = $this->get_tags($store_offers,$store_config->row['tags']);
	    	if(count($tags) < 2) {
	    		return '';
	    	} else {
	    		$tagdata = $TMPL->tagdata;
	    		if(preg_match('/{tag}(.*){'.SLASH.'tag}/ism',$tagdata,$matches)) {
	    			$tag_template = $matches[1];
	    			$temp = $TMPL->swap_var_single('link', $pages_uri, $tag_template);
	    			$temp = $TMPL->swap_var_single('text', 'All', $temp);
	    			if($current_tag != '') {
	    				$temp = preg_replace('/{current}(.*){'.SLASH.'current}/ism', '', $temp);
	    			} else {
	    				$temp = preg_replace('/{current}(.*){'.SLASH.'current}/ism', '$1', $temp);
	    			}
	    			$output = $temp;
	    			foreach($tags as $t) {
	    				$temp = $TMPL->swap_var_single('link', $pages_uri.'?tag='.urlencode($t), $tag_template);
	    				$temp = $TMPL->swap_var_single('text', $t, $temp);
	    				if($t != $current_tag) {
	    					$temp = preg_replace('/{current}(.*){'.SLASH.'current}/ism', '', $temp);
	    				} else {
	    					$temp = preg_replace('/{current}(.*){'.SLASH.'current}/ism', '$1', $temp);
	    				}
	    				$output .= $temp;
	    			}
	    			
	    			return preg_replace('/({tag}.*{'.SLASH.'tag})/ism', $output, $tagdata);
	    		} else {
	    			return $tagdata;
	    		}
	    	}
    	}
    }
    
    function get_twitter($offer) {
    	global $DB, $PREFS;
    	
    	$site_id = $PREFS->ini('site_id');
    	$site_name = $PREFS->ini('site_name');
    	$site_url = $PREFS->ini('site_url');
    	$sql = "SELECT `twitter_username`, `twitter_message`
    			FROM exp_topspin WHERE id = ".$site_id." ORDER BY id ASC LIMIT 1";
    	$config_vars = $DB->query($sql);
    	$twitter = array();
    	if($config_vars->num_rows > 0) {
    		if($config_vars->row['twitter_username'] != '' && $config_vars->row['twitter_message'] != '') {
	    		$twitter['username'] = $config_vars->row['twitter_username'];
	    		$twitter['message'] = $config_vars->row['twitter_message'];
	    		$twitter['message'] = preg_replace('/\[title\]/ism', $offer->name, $twitter['message']);
	    		$twitter['message'] = preg_replace('/\[site-name\]/ism', $site_name, $twitter['message']);
	    		$parsed_url = parse_url($site_url);
	    		$twitter['message'] = preg_replace('/\[domain-name\]/ism', $parsed_url['host'], $twitter['message']);
	    		// [title], [site-name], [domain-name]
	    		
	    	}
    	}
    	return $twitter;
    }
    
    function get_offers() {
    	global $DB, $PREFS;
    	
    	$topspin_config = $DB->query("SELECT `id`, `api_key`, `username`, `artist_id`, `offers_data`, `offers_timestamp`, `artists_data`
    			FROM exp_topspin WHERE id = ".$DB->escape_str($PREFS->ini('site_id'))." ORDER BY id ASC LIMIT 1"); 
    	
    	//get topspin data from db or topspin
    	if($topspin_config->row['offers_data'] == '' || (unserialize($topspin_config->row['offers_data']) == false) || $topspin_config->row['offers_timestamp'] < date('U')-80000) {
    	
    		require_once PATH.'modules/topspin/Topspin.php';
    	
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
	    				$offers_final[count($offers_final)-1]->artist_id = $topspin_config->row['artist_id'];
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
    
    function get_store_offers() {
    	global $DB, $IN, $PREFS;
    	$pages_uri =  $DB->escape_str($IN->URI);
    	$store_config = $DB->query("SELECT store_data, store_data_timestamp, offer_types, id, sort_direction, tags FROM exp_topspin_stores WHERE uri = '".$pages_uri."'");
    	
    	$store_offers = array();
    	if($store_config->num_rows) {
    		$store_data = unserialize($store_config->row['store_data']);
    		if($store_data != '' && $store_data != false && $store_config->row['store_data_timestamp']+80000 > date('U')) {
    			//cached data okay
    			$store_offers = $store_data;
    		} else {
    			//cache not okay
    			$offers = $this->get_offers();
    			$offer_types = (!unserialize($store_config->row['offer_types']) ? array() : unserialize($store_config->row['offer_types']));
    			$tags = (!unserialize($store_config->row['tags']) ? array() : unserialize($store_config->row['tags']));
    			foreach($offers as $o) {
    				if($o->artist_id ==  $topspin_config->row['artist_id'] || $topspin_config->row['artist_id'] == 0) {
    					if(array_search($o->offer_type, $offer_types) !== false) {
    						if(count($tags) == 0) {
    							$store_offers[] = $o;
    						} else if (isset($o->tags)) {
    							foreach($o->tags as $t) {
    								if(array_search($t, $tags) !== false) {
    									$store_offers[] = $o;
    									break;
    								}
    							}
    						}
    					}		
    				}
    			}
    			uasort($store_offers, array('self', 'offer_cmp'));
    			if($store_config->row['sort_direction'] == 'desc') {
    				$store_offers = array_reverse($store_offers);	
    			}
    			$store_data = array('store_data' => serialize($store_offers), 'store_data_timestamp' => date('U'));
    			$offers_timestamp_query = $DB->query('SELECT offers_timestamp FROM exp_topspin WHERE id = '.$PREFS->ini('site_id'));
    			if($offers_timestamp_query->num_rows) {
    				$store_data['store_data_timestamp'] = $offers_timestamp_query->row['offers_timestamp'];
    			}
    			$DB->query($DB->update_string('exp_topspin_stores', $store_data, 'id = '.$store_config->row['id']));
    			
    		}
    	}
    	
    	return $store_offers;
    
    }
    
    function offer_cmp($a, $b) {
    	if($a->price == $b->price) {
    		return 0;
    	}
    	return ($a->price < $b->price) ? -1 : 1;
    }
    
    function get_tags($offers, $store_tags = '') {
    	$tags = array();
    	if(unserialize($store_tags) == false) {
	    	foreach($offers as $o) {
	    		foreach($o->tags as $t) {
	    			if(array_search($t, $tags) === false) {
	    				$tags[] = $t;
	    			}
	    		}
	    	}
	   } else {
	   		$store_tag_array = unserialize($store_tags);
		   	foreach($offers as $o) {
		   		foreach($o->tags as $t) {
		   			if(array_search($t, $tags) === false && array_search($t, $store_tag_array) !== false) {
		   				$tags[] = $t;
		   			}
		   		}
		   	}
	   }
    	return $tags;
    }
    
    function jquery_lightbox_js() {
    	global $PREFS;
    	return $PREFS->ini('theme_folder_url').'third_party/topspin/slimbox2.js';
    }    
    
    function css_path() {
    	global $PREFS;
    	return $PREFS->ini('theme_folder_url').'third_party/topspin/images/';
    }
}


?>