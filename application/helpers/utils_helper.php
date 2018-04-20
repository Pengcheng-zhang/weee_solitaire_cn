<?php
if (! defined ( 'BASEPATH' ))
	exit ( 'No direct script access allowed' );

/**
 * ****************************************
 * User
 * ****************************************
 */
if (! function_exists ( 'get_session_user' )) {
	function get_session_user() {
		$CI = & get_instance ();
		return $CI->session->userdata ( SESSION_USER );
	}
}

if (! function_exists ( 'set_session_user' )) {
	function set_session_user($user) {
		$CI = & get_instance ();
		// set the site language by user settings
		$lang = $user ['language'];
		if (! $lang) {
			$lang = get_site_language ();
			$CI->user_model->update_weee_user_by_global_id ( [ 
					'language' => $lang 
			], $user ['Global_User_ID'] );
		} else {
			set_site_language ( $lang );
		}
		
		return $CI->session->set_userdata ( SESSION_USER, array (
				'Global_User_ID' => $user ['Global_User_ID'],
				'userId' => $user ['userId'],
				'roleType' => $user ['roleType'],
				'alias' => $user ['alias'],
				'headImgUrl' => $user ['headImgUrl'],
				'wxSnsOpenId' => $user ['wxSnsOpenId'] 
		) );
	}
}
if (! function_exists ( 'get_referral_id_from_cookie' )) {
	/**
	 * 从Cookie中获取referral id
	 *
	 * @return false if not existed.
	 */
	function get_referral_id_from_cookie() {
		$cookie = get_cookie ( COOKIE_REFERRAL_ID );
		if (! $cookie) {
			return false;
		}
		$cookie_array = explode ( ':', $cookie );
		if (count ( $cookie_array ) != 2) {
			return false;
		}
		$expire = 3600 * 2;
		$cookie_settime = $cookie_array [1];
		if ($cookie_settime + $expire < time ()) {
			return false;
		}
		return $cookie_array [0];
	}
}
if (! function_exists ( 'check_url_for_referral_id' )) {
	/**
	 * 获取URL中的referral id，如果是团长访问的，确保URL里面需要包含referral id
	 *
	 * @param array $user
	 * @return boolean false if no referral id in the url, otherwise return referral id
	 */
	function check_url_for_referral_id($user) {
		$CI = & get_instance ();
		$referral_id = $CI->input->get ( 'referral_id' );
		
		if (! $user) {
			return $referral_id;
		}
		$user_id = $user ['Global_User_ID'];
		
		if (! property_exists ( $CI, 'sales_model' )) {
			$CI->load->model ( 'sales_model' );
		}
		// 如果是开通affiliate program的团长，确认URL包含团长USER ID的referral id
		$sales = $CI->sales_model->get_sales_additional_by_sales_person_id ( $user_id );
		if ($sales && $sales ['affiliate'] == 'Y') {
			$current_url = get_request_uri ();
			$url = add_parameter_to_url ( $current_url, 'referral_id', $user_id );
			if ($url != $current_url) {
				redirect ( $url );
			}
		}
		
		return $referral_id;
	}
}
if (! function_exists ( 'is_admin_user' )) {
	function is_admin_user($user) {
		if (! $user) {
			return false;
		}
		if (is_array ( $user )) {
			if (key_exists ( 'roleType', $user )) {
				return ! in_array ( USER_ROLE_NORMAL, str_split ( $user ['roleType'] ) );
			}
			$user_id = $user ['Global_User_ID'];
		} else {
			$user_id = $user;
		}
		$CI = & get_instance ();
		$user = $CI->user_model->get_weee_user_by_global_id ( $user_id );
		if ($user) {
			return ! in_array ( USER_ROLE_NORMAL, str_split ( $user ['roleType'] ) );
		}
		return false;
	}
}
if (! function_exists ( 'is_authorized' )) {
	function is_authorized($user, $privilege_key) {
		if (! $user) {
			return false;
		}
		
		$CI = & get_instance ();
		$user_role = '';
		if (is_array ( $user ) && key_exists ( 'roleType', $user )) {
			$user_role = $user ['roleType'];
		} else {
			$user_id = is_array ( $user ) ? $user ['Global_User_ID'] : $user;
			$user = $CI->user_model->get_weee_user_by_global_id ( $user_id );
			if ($user) {
				$user_role = $user ['roleType'];
			}
		}
		
		if ($user_role) {
			$privilege_roles = $CI->config->item ( 'privilege' );
			if (key_exists ( $privilege_key, $privilege_roles )) {
				return array_intersect ( str_split ( $user_role ), $privilege_roles [$privilege_key] ) ? true : false;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
}
if (! function_exists ( 'create_user_access_token' )) {
	function create_user_access_token($user_id, $expire_time) {
		$CI = & get_instance ();
		$access_toke = gen_uuid ();
		$result = $CI->user_model->insert_user_access_token ( $user_id, $access_toke, $expire_time );
		if ($result) {
			return encode_uuid_base64 ( $access_toke );
		} else {
			return FALSE;
		}
	}
}
if (! function_exists ( 'get_user_by_weee_access_token' )) {
	function get_user_by_weee_access_token($access_token) {
		if (! $access_token) {
			return false;
		}
		$CI = & get_instance ();
		$access_token = decode_uuid_base64 ( $access_token );
		$access_token_row = $CI->user_model->get_user_access_token_row ( $access_token );
		if (! $access_token_row) {
			log_message ( 'error', 'get_user_by_weee_access_token: invalid access token: ' . $access_token );
			return false;
		}
		if ($access_token_row ['expired_time'] < time ()) {
			log_message ( 'error', 'get_user_by_weee_access_token: The access token is expired: ' . $access_token );
			return false;
		}
		return $CI->user_model->get_weee_user_by_global_id ( $access_token_row ['user_id'] );
	}
}

if (! function_exists ( 'get_head_image_url' )) {
	function get_head_image_url($head_img_url, $size = 'small', $full_mode = true) {
		if ($head_img_url) {
			$amazon_s3_url_pattern = "/^https:\/\/(.*?)amazonaws\.com/";
			if (preg_match ( $amazon_s3_url_pattern, $head_img_url )) {
				if ($size === 'small') {
					$head_img_url = str_replace ( '.jpg', '-64.jpg', $head_img_url );
				} else if ($size === 'large') {
					$head_img_url = str_replace ( '.jpg', '-132.jpg', $head_img_url );
				}
			} else {
				if ($size === 'small') {
					$head_img_url = preg_replace ( '/(\/0)$/i', '/64', $head_img_url );
				} else if ($size === 'large') {
					$head_img_url = preg_replace ( '/(\/0)$/i', '/132', $head_img_url );
				}
				if ($full_mode) {
					$head_img_url = preg_replace ( '/(img\/u\/v\/.+)$/i', '\1/f', $head_img_url );
				}
			}
		} else {
			$head_img_url = 'https://www.sayweee.com/css/img/avatar_unknown.png';
		}
		return $head_img_url;
	}
}
if (! function_exists ( 'get_head_image_base_url' )) {
	function get_head_image_base_url() {
		$s3_config = get_config_value ( WEEE_CONFIG_S3, true );
		$aws_s3_bucket = $s3_config ['extra_value_1'];
		$aws_s3_host = $s3_config ['extra_value_5'];
		$head_img_base_url = "https://{$aws_s3_host}/{$aws_s3_bucket}/";
		return $head_img_base_url;
	}
}
if (! function_exists ( 'weee_create_user_head_image_block' )) {
	function weee_create_user_head_image_block($user) {
		$content = '<a href="' . site_url ( 'person/view/u' . $user ['Global_User_ID'] ) . ' " class="thumbnail" target="_blank">';
		$content .= '<img src="' . get_head_image_url ( $user ['headImgUrl'] ) . '">';
		$content .= '<div class="caption"><span>' . $user ['alias'] . '</span></div></a>';
		return $content;
	}
}

if (! function_exists ( 'redirect_to_login' )) {
	function redirect_to_login($redirect_url_after_login = '', $message = 'You don\'t have permission to access. Sign in First.') {
		$CI = & get_instance ();
		if ($message)
			$CI->session->set_flashdata ( 'flashmsg', $message );
		
		if ($redirect_url_after_login) {
			if (! preg_match ( '#^https?://#i', $redirect_url_after_login )) {
				$redirect_url_after_login = site_url ( $redirect_url_after_login );
			}
			$url = site_url ( 'login' ) . '?redirect_url=' . urlencode ( $redirect_url_after_login );
			redirect ( $url, 'refresh' );
		} else {
			redirect ( 'login', 'refresh' );
		}
	}
}
if (! function_exists ( 'get_user_from_weixin_sns_access_info' )) {
	/**
	 * Get the weee user object by weixin userinfo interface result.
	 * If the user record doesn't exist, create it first.
	 *
	 * @param array $access_info
	 *        	the result of weixin userinfo interface
	 * @param string $wx_api_type
	 *        	[wx_api | wx_web_api | wx_app_api]
	 * @return weee.user | NULL
	 */
	function get_user_from_weixin_sns_access_info($access_info, $wx_api_type = 'wx_api') {
		$CI = & get_instance ();
		$CI->load->model ( 'user_model' );
		
		$weixin_open_id = $access_info ['openid'];
		if ('wx_web_api' == $wx_api_type) {
			// WEB
			$field_openid = 'wxWebOpenId';
		} else if ('wx_app_api' == $wx_api_type) {
			// APP
			$field_openid = 'wxOpenId';
		} else {
			// WX GongZhongHao
			$field_openid = 'wxSnsOpenId';
		}
		
		$user = $CI->user_model->get_weee_user ( array (
				$field_openid => $weixin_open_id 
		) );
		if ($user) {
			log_message ( 'debug', 'The user exists in the user table.' );
			return $user;
		}
		
		log_message ( 'debug', "New user[$weixin_open_id], insert into the DB. wx_api_type=$wx_api_type" );
		$CI->user_model->create_weixin_user ( $weixin_open_id );
		
		if (strpos ( $access_info ['scope'], 'snsapi_userinfo' ) !== false || strpos ( $access_info ['scope'], 'snsapi_login' ) !== false) {
			log_message ( 'debug', 'The user basic info is empty. Calling Weixin API...' );
			$access_token = $access_info ['access_token'];
			$weixin_user_info = get_sns_weixin_user_info ( $access_token, $weixin_open_id );
			if ($weixin_user_info) {
				$CI->user_model->update_weixin_user_basic_info ( $weixin_open_id, $weixin_user_info );
				$CI->load->helper ( 'uuid' );
				$CI->user_model->create_weee_user_from_weixin_sns ( gen_uuid (), $weixin_user_info ['nickname'], $weixin_open_id, $weixin_user_info ['unionid'], $weixin_user_info ['headimgurl'], $field_openid );
				$user = $CI->user_model->get_weee_user ( array (
						$field_openid => $weixin_open_id 
				) );
				return $user;
			} else {
				log_message ( 'error', 'Can not get user basic info from Weixin API.' );
			}
		} else {
			log_message ( 'debug', 'The user basic info is empty. Ask the user to authorize it.' );
		}
		
		return NULL;
	}
}
if (! function_exists ( 'log_user_activity' )) {
	/**
	 * Log the user activity into the table
	 *
	 * @param string $user_id
	 * @param string $type
	 * @param string $target_id
	 * @param array $parameters
	 */
	function log_user_activity($user_id, $type, $target_id, $parameters, $parameter = NULL, $hidden = 'N') {
		$CI = & get_instance ();
		if ($user_id) {
			$CI->db->update ( 'user_activity', array (
					'hidden' => 'Y' 
			), array (
					'user_id' => $user_id,
					'type' => $type,
					'target_id' => $target_id 
			) );
		}
		$wx_open_id = $CI->session->userdata ( SESSION_WX_SNS_OPEN_ID );
		if (! $wx_open_id) {
			$wx_open_id = NULL;
		}
		$CI->db->insert ( 'user_activity', array (
				'user_id' => $user_id,
				'wx_open_id' => $wx_open_id,
				'type' => $type,
				'target_id' => $target_id,
				'parameter' => $parameter,
				'parameters' => json_encode ( $parameters, JSON_UNESCAPED_UNICODE ),
				'hidden' => $hidden 
		) );
	}
}
if (! function_exists ( 'get_user_notification_setting' )) {
	function get_user_notification_setting($user, $key) {
		$CI = & get_instance ();
		if (! array_key_exists ( 'settings', $user ) || ! array_key_exists ( 'wxSnsOpenId', $user )) {
			// The user info doesn't include all extra table record, retrieve them
			$user = $CI->user_model->get_weee_user_with_weixin_extra_info ( is_array ( $user ) ? $user ['Global_User_ID'] : $user );
		}
		
		// get the user notifcaiton type from the user's settings
		$notification_type = WEEE_NOTIFICATION_TYPE_WEIXIN;
		$notification_enabled = 1;
		$user_settings = json_decode ( $user ['settings'], true );
		log_message ( 'debug', "user_settings: {$key} - " . print_r ( $user_settings, true ) );
		if ($user_settings && key_exists ( 'notification', $user_settings ) && key_exists ( $key, $user_settings ['notification'] )) {
			if (key_exists ( 'type', $user_settings ['notification'] )) {
				$notification_type = $user_settings ['notification'] ['type'];
			}
			if (key_exists ( $key, $user_settings ['notification'] )) {
				$notification_enabled = $user_settings ['notification'] [$key];
			}
		}
		$notification_type = $notification_enabled ? $notification_type : WEEE_NOTIFICATION_TYPE_NONE;
		if ($key === WEEE_NOTIFICATION_DAILY_REPORT && $notification_type != WEEE_NOTIFICATION_TYPE_NONE) {
			$notification_type = WEEE_NOTIFICATION_TYPE_EMAIL;
		}
		
		// return the email or weixin id based on the notification type
		$notification_target = null;
		if ($notification_type != WEEE_NOTIFICATION_TYPE_NONE) {
			if ($notification_type === WEEE_NOTIFICATION_TYPE_WEIXIN && $user ['wxSnsOpenId'] && $user ['subscribe']) {
				$notification_target = $user ['wxSnsOpenId'];
			} else if ($user ['email'] || $user ['contact_email']) {
				$notification_type = WEEE_NOTIFICATION_TYPE_EMAIL;
				$notification_target = $user ['email'] ? $user ['email'] : $user ['contact_email'];
			} else {
				$notification_type = WEEE_NOTIFICATION_TYPE_NONE;
			}
		}
		$out = [ 
				'type' => $notification_type,
				'target' => $notification_target 
		];
		log_message ( 'debug', '$out: ' . print_r ( $out, true ) );
		return $out;
	}
}
/**
 * ****************************************
 * Image
 * ****************************************
 */
if (! function_exists ( 'get_image_thumbnail_url' )) {
	function get_image_thumbnail_url($image_url) {
		return preg_replace ( '/(static_img\/.+)\.(.+)$/i', '\1_s.\2', $image_url );
	}
}

if (! function_exists ( 'get_image_square_thumbnail_image' )) {
	/**
	 * Get the thumbnail image url.
	 * The url is a http url or local server file path.
	 * If the image is not on our server, return orignal url.
	 *
	 * @param string $orignal_file_path
	 * @return string
	 */
	function get_image_square_thumbnail_image($orignal_file_path) {
		return preg_replace ( '/\/(static_img|weee_img)\/([^\/]+)\/(\S{22}).(gif|jpg)/', '/\1/\2/\3-square-160.\4', $orignal_file_path );
	}
}

if (! function_exists ( 'get_image_scale_url' )) {
	function get_image_scale_url($orignal_file_path, $side_length) {
		return preg_replace ( '/\/(static_img)\/([^\/]+)\/(\S{22}).(gif|jpg)/', '/product/image/\2/\3.\4/' . $side_length, $orignal_file_path );
	}
}

/**
 * ****************************************
 * View
 * ****************************************
 */
if (! function_exists ( 'output_stylesheet_link_tags' )) {
	function output_stylesheet_link_tags($css_file_keys, $return = false) {
		if (is_string ( $css_file_keys )) {
			$css_file_keys = array_map ( 'trim', explode ( ',', $css_file_keys ) );
		}
		$CI = & get_instance ();
		$min_support = $CI->config->config ['weee_min_supprot'];
		$cloud_front_support = $CI->config->config ['weee_cloud_front_support'];
		$base_url = $cloud_front_support ? $CI->config->config ['weee_cloud_front'] : base_url ();
		
		$CI->load->config ( 'weee_files' );
		$weee_css_files = $CI->config->item ( 'weee_css_files' );
		$tags = '';
		foreach ( $css_file_keys as $css_file_key ) {
			if (key_exists ( $css_file_key, $weee_css_files )) {
				$file = $weee_css_files [$css_file_key];
				if ($min_support && key_exists ( 'min', $file )) {
					$file_path = $file ['min'];
				} else {
					$file_path = $file ['file'];
				}
				if (isset ( $_SERVER ['SERVER_NAME'] ) && $_SERVER ['SERVER_NAME'] === 'localhost') {
					$file_path = $file ['file'];
					$file ['v'] = 0;
				}
				$tags .= '<link rel="stylesheet" href="' . $base_url . $file_path . ($file ['v'] ? "?v={$file['v']}" : '') . '">' . "\n";
			} else {
				if (empty ( $css_file_key )) {
					continue;
				}
				log_message ( 'error', "Can not find the $css_file_key in config file." );
			}
		}
		if ($return)
			return $tags;
		else
			echo $tags;
	}
}
if (! function_exists ( 'output_java_script_tags' )) {
	function output_java_script_tags($js_file_keys, $return = false) {
		if (is_string ( $js_file_keys )) {
			$js_file_keys = array_map ( 'trim', explode ( ',', $js_file_keys ) );
		}
		$CI = & get_instance ();
		$min_support = $CI->config->config ['weee_min_supprot'];
		$cloud_front_support = $CI->config->config ['weee_cloud_front_support'];
		$base_url = $cloud_front_support ? $CI->config->config ['weee_cloud_front'] : base_url ();
		
		$CI->load->config ( 'weee_files' );
		$weee_js_files = $CI->config->item ( 'weee_js_files' );
		$tags = '';
		foreach ( $js_file_keys as $js_file_key ) {
			if (key_exists ( $js_file_key, $weee_js_files )) {
				$file = $weee_js_files [$js_file_key];
				if ($min_support && key_exists ( 'min', $file )) {
					$file_path = $file ['min'];
				} else {
					$file_path = $file ['file'];
				}
				if (isset ( $_SERVER ['SERVER_NAME'] ) && $_SERVER ['SERVER_NAME'] === 'localhost') {
					$file_path = $file ['file'];
					$file ['v'] = 0;
				}
				$tags .= '<script src="' . $base_url . $file_path . ($file ['v'] ? "?v={$file['v']}" : '') . '"></script>' . "\n";
			} else {
				if (empty ( $js_file_key )) {
					continue;
				}
				log_message ( 'error', "Can not find the $js_file_key in config file." );
			}
		}
		if ($return)
			return $tags;
		else
			echo $tags;
	}
}

if (! function_exists ( 'get_google_map_url_str' )) {
	function get_google_map_url_str() {
		$CI = & get_instance ();
		$url_address = $CI->config->config ['google_apis'] ['url_address'] ['browser'];
		$key = $CI->config->config ['google_apis'] ['key'] ['browser'];
		return $url_address . '/maps/api/js?key=' . $key;
	}
}

if (! function_exists ( 'output_google_map_js_tag' )) {
	function output_google_map_js_tag($return = false) {
		$CI = & get_instance ();
		$url_address = get_google_map_url_str ();
		$tag = '<script type="text/javascript" src="' . $url_address . '"></script>';
		if ($return)
			return $tag;
		else
			echo $tag;
	}
}

if (! function_exists ( 'output_user_thumbnail' )) {
	function output_user_thumbnail($user) {
		$content = '<a class="user_thumbnail" href="' . site_url ( 'person/view/u' . $user ['Global_User_ID'] ) . ' ">';
		$content .= '<img src="' . get_head_image_url ( $user ['headImgUrl'] ) . '">';
		$content .= $user ['alias'] . '</a>';
		return $content;
	}
}
if (! function_exists ( 'dt_filter_to_where_array' )) {
	function dt_filter_to_where_array($search, $filter_field_names) {
		$where = [ ];
		foreach ( $filter_field_names as $filter_field_name ) {
			$value = isset ( $search ['value'] [$filter_field_name] ) ? $search ['value'] [$filter_field_name] : '';
			$filter_field_name = str_replace ( '__', '.', $filter_field_name );
			if ($value) {
				if (preg_match ( '/^(.+)_ge$/', $filter_field_name, $matches )) {
					$where [$matches [1] . ' >='] = $value;
				} elseif (preg_match ( '/^(.+)_le$/', $filter_field_name, $matches )) {
					$where [$matches [1] . ' <='] = $value;
				} else if (preg_match ( '/^(.+)_like$/', $filter_field_name, $matches )) {
					$CI = & get_instance ();
					$where [$matches [1] . ' LIKE'] = '%' . $CI->db->escape_like_str ( $value ) . '%';
				} else if (preg_match ( '/^(.+)_in$/', $filter_field_name, $matches )) {
					$CI = & get_instance ();
					if (is_array ( $value )) {
						$value = implode ( '\',\'', $value );
					} else {
						$value = implode ( '\',\'', explode ( ',', $value ) );
					}
					$where [$matches [1] . ' IN (\'' . $value . '\')'] = NULL;
				} else {
					$where [$filter_field_name] = $value;
				}
			}
		}
		return $where;
	}
}

/**
 * ****************************************
 * System & Config
 * ****************************************
 */
if (! function_exists ( 'get_meta_codes_array' )) {
	function get_meta_codes_array($type_id, $all_fields = false) {
		$CI = & get_instance ();
		$CI->db->order_by ( 'pos' );
		$query = $CI->db->get_where ( 'meta_code', "type_id = $type_id" );
		$codes = $query->result_array ();
		
		if ($all_fields) {
			return $codes;
		}
		
		$code_array = array ();
		$value_key = "value_" . get_site_language ();
		foreach ( $codes as $code ) {
			$code_array [$code ['key']] = $code [$value_key];
		}
		return $code_array;
	}
}

if (! function_exists ( 'get_meta_code_value_by_key' )) {
	function get_meta_code_value_by_key($type_id, $key, $language = null) {
		$CI = & get_instance ();
		$where ['key'] = $key;
		$where ['type_id'] = $type_id;
		$table = 'meta_code';
		$query = $CI->db->get_where ( $table, $where );
		$code = $query->row_array ();
		$language = $language ? $language : get_site_language ();
		$value_key = "value_" . $language;
		
		return $code ? $code [$value_key] : '';
	}
}

if (! function_exists ( 'get_config_value' )) {
	function get_config_value($key, $need_extra_values = false) {
		$CI = & get_instance ();
		$where = array (
				'key' => $key 
		);
		$query = $CI->db->get_where ( 'config', $where );
		$data = $query->row_array ();
		if ($data) {
			if ($need_extra_values) {
				return $data;
			} else {
				return $data ['value'];
			}
		} else {
			return false;
		}
	}
}

if (! function_exists ( 'set_config_value' )) {
	function set_config_value($key, $value, $extra_values = NULL) {
		$CI = & get_instance ();
		$where = array (
				'key' => $key 
		);
		$values ['value'] = $value;
		if ($extra_values) {
			$values = array_merge ( $values, $extra_values );
		}
		$CI->db->update ( 'config', $values, $where );
	}
}
if (! function_exists ( 'get_cookie_language' )) {
	function get_cookie_language() {
		return get_cookie ( COOKIE_SITE_LANGUAGE );
	}
}
if (! function_exists ( 'set_site_language' )) {
	function set_site_language($site_lang) {
		set_cookie ( COOKIE_SITE_LANGUAGE, $site_lang, COOKIE_EXPIRE_TIME );
	}
}
if (! function_exists ( 'get_language_mapping' )) {
	function get_language_mapping($language = null) {
		$lang_options = array (
				LANGUAGE_ENGLISH => array (
						'key' => LANGUAGE_ENGLISH,
						'label' => 'English',
						'ci_name' => 'english',
						'db_label' => 'label_en' 
				),
				LANGUAGE_CHINESE => array (
						'key' => LANGUAGE_CHINESE,
						'label' => '中文简体',
						'ci_name' => 'chinese',
						'db_label' => 'label' 
				),
				LANGUAGE_CHINESE_T => array (
						'key' => LANGUAGE_CHINESE_T,
						'label' => '中文繁體',
						'ci_name' => 'chinese_Hant',
						'db_label' => 'label_zh_hant' 
				) 
		);
		if ($language && ! array_key_exists ( $language, $lang_options )) {
			$language = DEFAULT_LANGUAGE;
		}
		if ($language) {
			return $lang_options [$language];
		}
		return $lang_options;
	}
}
if (! function_exists ( 'get_site_language' )) {
	function get_site_language() {
		$language_mapping = get_language_mapping ();
		$CI = & get_instance ();
		// robot
		if ($CI->agent->is_robot ()) {
			if ($robot_lang = $CI->input->get ( 'lang' )) {
				if (array_key_exists ( $robot_lang, $language_mapping )) {
					return $CI->input->get ( 'lang' );
				}
			}
			return LANGUAGE_ENGLISH;
		}
		
		// detect default language by HTTP agent for APP API
		$user_agent = $CI->input->user_agent ();
		if (preg_match ( '/WeeeApp.*API/', $user_agent )) {
			$site_lang = LANGUAGE_ENGLISH;
			if ($CI->agent->accept_lang ( 'zh' )) {
				$site_lang = LANGUAGE_CHINESE;
			} else if ($CI->agent->accept_lang ( 'en' )) {
				$site_lang = LANGUAGE_ENGLISH;
			} else if ($CI->agent->accept_lang ( 'zh-Hant' )) {
				$site_lang = LANGUAGE_CHINESE_T;
			} else if (preg_match ( '/WeeeApp.*\(zh\)/', $user_agent )) {
				$site_lang = LANGUAGE_CHINESE;
			} else if (preg_match ( '/WeeeApp.*\(zh-Hant\)/', $user_agent )) {
				$site_lang = LANGUAGE_CHINESE_T;
			} else if (preg_match ( '/WeeeApp.*(en)/', $user_agent )) {
				$site_lang = LANGUAGE_ENGLISH;
			}
			return $site_lang;
		}
		
		// cookie
		$site_lang = get_cookie_language ();
		if ($site_lang && array_key_exists ( $site_lang, $language_mapping )) {
			return $site_lang;
		}
		// user default
		$user = get_session_user ();
		if ($user) {
			$user = $CI->user_model->get_weee_user_by_global_id ( $user ['Global_User_ID'] );
			$site_lang = $user ['language'];
			if ($site_lang) {
				set_site_language ( $site_lang );
				return $site_lang;
			}
		}
		
		$site_lang = LANGUAGE_CHINESE;
		
		// detect default language by HTTP agent for APP
		if (preg_match ( '/WeeeApp.*\(zh\)/', $user_agent )) {
			$site_lang = LANGUAGE_CHINESE;
		} else if (preg_match ( '/WeeeApp.*(en)/', $user_agent )) {
			$site_lang = LANGUAGE_ENGLISH;
		} else if (preg_match ( '/WeeeApp.*\(zh-Hans\)/', $user_agent )) {
			$site_lang = LANGUAGE_CHINESE;
		} else if (preg_match ( '/WeeeApp.*\(zh-Hant\)/', $user_agent )) {
			$site_lang = LANGUAGE_CHINESE_T;
		}
		
		if ($user) {
			$CI->user_model->update_weee_user_by_global_id ( [ 
					'language' => $site_lang 
			], $user ['Global_User_ID'] );
		}
		set_site_language ( $site_lang );
		return $site_lang;
	}
}
if (! function_exists ( 'get_i18n_image_url' )) {
	function get_i18n_image_url($image_name) {
		// $site_lang = get_site_language ();
		$site_lang = 'zh';
		return site_url ( "css/img/{$site_lang}/$image_name" );
	}
}
if (! function_exists ( 'update_gb_i18n_by_table' )) {
	function convert_gb_i18n_from_hans_to_hant($table, $old_row_data, $new_row_data) {
		$CI = & get_instance ();
		$CI->load->model ( 'groupbuy_model' );
		$result = true;
		$column_defs = query_column_defs_by_table ( $table );
		$zh_convert_biz = & load_class ( 'Zh_convert_biz', 'biz/zh_convert', '' );
		$target_id = $new_row_data ['id'];
		foreach ( $column_defs as $column ) {
			$key = $column ['column_name'];
			
			$new_value = empty ( $new_row_data [$key] ) ? '' : $new_row_data [$key];
			$old_value = empty ( $old_row_data [$key] ) ? '' : $old_row_data [$key];
			if ($old_row_data && $new_value == $old_value) {
				continue;
			}
			$i18n_record = $CI->groupbuy_model->get_i18n_by_unique_key ( $column ['id'], $target_id, LANGUAGE_CHINESE_T );
			if ($new_value) {
				$new_value = $zh_convert_biz->gb2312_big5 ( $new_value );
			}
			if ($i18n_record) {
				// -- update by i18n.id
				$result = $CI->groupbuy_model->update_i18n_by_id ( $i18n_record ['id'], [ 
						'content' => $new_value 
				] );
			} else {
				$result = $CI->groupbuy_model->insert_i18n ( [ 
						"column_def_id" => $column ['id'],
						"language" => LANGUAGE_CHINESE_T,
						"target_id" => $target_id,
						'content' => $new_value 
				] );
			}
			
			if (! $result) {
				return false;
			}
		}
		return true;
	}
}
if (! function_exists ( 'get_user_language' )) {
	/**
	 *
	 * @deprecated
	 *
	 */
	function get_user_language($user = null) {
		$CI = & get_instance ();
		if ($CI->agent->is_robot) {
			return LANGUAGE_ENGLISH;
		}
		if (! $user) {
			$user = get_session_user ();
		}
		if ($user && key_exists ( 'language', $user ) && $user ['language']) {
			return $user ['language'];
		}
		$language = get_cookie_language ();
		if ($language) {
			return $language;
		}
		$language = $CI->input->get ( 'lang' );
		if ($language && ($language === LANGUAGE_CHINESE || $language === LANGUAGE_ENGLISH)) {
			return $language;
		}
		if (is_weixin_browser ()) {
			return LANGUAGE_CHINESE;
		}
		if ($CI->agent->accept_lang ( 'zh' ) || $CI->agent->accept_lang ( 'zh-cn' )) {
			return LANGUAGE_CHINESE;
		}
		if ($CI->agent->accept_lang ( 'en' )) {
			return LANGUAGE_ENGLISH;
		}
		return LANGUAGE_ENGLISH;
	}
}
/**
 * ****************************************
 * CURL
 * ****************************************
 */
if (! function_exists ( 'curl_asynchronous_call' )) {
	function curl_asynchronous_call($url, $params = array()) {
		$fields_string = '';
		foreach ( $params as $key => $value ) {
			$value_addslashes = addslashes ( $value );
			$fields_string .= " -F \"{$key}={$value_addslashes}\"";
		}
		$fields_string = $fields_string . ' ' . '"' . $url . '"';
		$command = 'curl -s' . $fields_string;
		if (strpos ( php_uname ( 's' ), 'Windows' ) === false) {
			$command .= ' > /dev/null &';
		}
		log_message ( 'debug', '$command = ' . print_r ( $command, true ) );
		
		$arrOutput = null;
		exec ( $command, $arrOutput );
	}
}
if (! function_exists ( 'curl_synchronous_call' )) {
	function curl_synchronous_call($url, $params = array(), $headers = array(), $curl_options = array()) {
		$fields_string = '';
		if (in_array ( 'Content-type: application/json', $headers )) {
			$fields_string = json_encode ( $params );
		} else {
			foreach ( $params as $key => $value ) {
				$fields_string .= $key . '=' . urlencode ( $value ) . '&';
			}
			rtrim ( $fields_string, '&' );
		}
		
		$ch = curl_init ();
		curl_setopt ( $ch, CURLOPT_URL, $url );
		if ($params) {
			curl_setopt ( $ch, CURLOPT_POST, count ( $params ) );
			curl_setopt ( $ch, CURLOPT_POSTFIELDS, $fields_string );
		}
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, true );
		if ($headers) {
			curl_setopt ( $ch, CURLOPT_HTTPHEADER, $headers );
		}
		foreach ( $curl_options as $option => $value ) {
			curl_setopt ( $ch, $option, $value );
		}
		$result = curl_exec ( $ch );
		
		if ($errno = curl_errno ( $ch )) {
			log_message ( 'error', "Faild to call $url: " . print_r ( $params, TRUE ) );
			log_message ( 'error', "Faild to call $url: " . print_r ( array_merge ( curl_getinfo ( $ch ), [ 
					'errno' => $errno 
			] ), TRUE ) );
			curl_close ( $ch );
			return FALSE;
		}
		curl_close ( $ch ); // close curl handle
		
		return $result;
	}
}
if (! function_exists ( 'curl_get_file_contents' )) {
	function curl_get_file_contents($url) {
		$ch = curl_init ();
		
		curl_setopt ( $ch, CURLOPT_HEADER, 0 );
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt ( $ch, CURLOPT_URL, $url );
		curl_setopt ( $ch, CURLOPT_FOLLOWLOCATION, 1 );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
		
		$data = curl_exec ( $ch );
		if (curl_errno ( $ch )) {
			$data = null;
		}
		curl_close ( $ch );
		
		return $data;
	}
}
if (! function_exists ( 'curl_get_file_size' )) {
	function curl_get_file_size($url) {
		// Assume failure.
		$result = - 1;
		
		$curl = curl_init ( $url );
		
		// Issue a HEAD request and follow any redirects.
		curl_setopt ( $curl, CURLOPT_NOBODY, true );
		curl_setopt ( $curl, CURLOPT_HEADER, true );
		curl_setopt ( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt ( $curl, CURLOPT_FOLLOWLOCATION, true );
		
		$data = curl_exec ( $curl );
		curl_close ( $curl );
		
		if ($data) {
			$content_length = "unknown";
			$status = "unknown";
			
			if (preg_match ( "/^HTTP\/1\.[01] (\d\d\d)/", $data, $matches )) {
				$status = ( int ) $matches [1];
			}
			
			if (preg_match ( "/Content-Length: (\d+)/", $data, $matches )) {
				$content_length = ( int ) $matches [1];
			}
			
			// http://en.wikipedia.org/wiki/List_of_HTTP_status_codes
			if ($status == 200 || ($status > 300 && $status <= 308)) {
				$result = $content_length;
			}
		}
		return $result;
	}
}
/**
 * ****************************************
 * Misc
 * ****************************************
 */
if (! function_exists ( 'is_ios_browser' )) {
	function is_ios_browser() {
		if (isset ( $_SERVER ['HTTP_USER_AGENT'] ) && preg_match ( '/iPad|iPhone|iPod/i', $_SERVER ['HTTP_USER_AGENT'] )) {
			return true;
		}
		return false;
	}
}

if (! function_exists ( 'is_app_browser' )) {
	function is_app_browser() {
		if (isset ( $_SERVER ['HTTP_USER_AGENT'] ) && strpos ( $_SERVER ['HTTP_USER_AGENT'], 'WeeeApp' ) !== false) {
			return true;
		}
		return false;
	}
}

if (! function_exists ( 'obfuscate_email' )) {
	function obfuscate_email($email, $rate = 3) {
		$em = explode ( "@", $email );
		$name = implode ( array_slice ( $em, 0, count ( $em ) - 1 ), '@' );
		$len = ceil ( strlen ( $name ) / $rate );
		
		return substr ( $name, 0, $len ) . str_repeat ( '*', $len ) . "@" . end ( $em );
	}
}

if (! function_exists ( 'obfuscate_address' )) {
	function obfuscate_address($address) {
		return preg_replace ( '/[^\d]*d*([\d]{2}) /', '** ', $address, 1 );
	}
}

if (! function_exists ( 'subtract_addree_to_city' )) {
	function subtract_addree_to_city($formatted_address) {
		$address_compnoents = explode ( ',', $formatted_address );
		if (($len = count ( $address_compnoents )) >= 3) {
			return $address_compnoents [$len - 3] . ', ' . $address_compnoents [$len - 2];
		}
		return '';
	}
}

if (! function_exists ( 'removeEmoji' )) {
	function removeEmoji($text) {
		$clean_text = "";
		
		// Match Emoticons
		$regexEmoticons = '/[\x{1F600}-\x{1F64F}]/u';
		$clean_text = preg_replace ( $regexEmoticons, '', $text );
		
		// Match Miscellaneous Symbols and Pictographs
		$regexSymbols = '/[\x{1F300}-\x{1F5FF}]/u';
		$clean_text = preg_replace ( $regexSymbols, '', $clean_text );
		
		// Match Transport And Map Symbols
		$regexTransport = '/[\x{1F680}-\x{1F6FF}]/u';
		$clean_text = preg_replace ( $regexTransport, '', $clean_text );
		
		// Match Miscellaneous Symbols
		$regexMisc = '/[\x{2600}-\x{26FF}]/u';
		$clean_text = preg_replace ( $regexMisc, '', $clean_text );
		
		// Match Dingbats
		$regexDingbats = '/[\x{2700}-\x{27BF}]/u';
		$clean_text = preg_replace ( $regexDingbats, '', $clean_text );
		
		return $clean_text;
	}
}
if (! function_exists ( 'replace4byte' )) {
	function replace4byte($string) {
		if (! $string)
			return $string;
		
		return preg_replace ( '%(?:
          \xF0[\x90-\xBF][\x80-\xBF]{2}      # planes 1-3
        | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
        | \xF4[\x80-\x8F][\x80-\xBF]{2}      # plane 16
    )%xs', '', $string );
	}
}
if (! function_exists ( 'detect_string_language' )) {
	function detect_string_language($string) {
		if (! $string) {
			return 'en';
		}
		if (preg_match ( "/\p{Han}+/u", $string )) {
			return 'zh';
		}
		return 'en';
	}
}
if (! function_exists ( 'html_2_plain_text' )) {
	function html_2_plain_text($html) {
		if (! $html) {
			return '';
		}
		$doc = new DOMDocument ();
		$libxml_previous_state = libxml_use_internal_errors ( true );
		$doc->loadHTML ( '<?xml version="1.0" encoding="UTF-8"?>' . $html );
		libxml_clear_errors ();
		libxml_use_internal_errors ( $libxml_previous_state );
		return $doc->textContent;
	}
}
if (! function_exists ( 'plain_text_2_html' )) {
	function plain_text_2_html($text) {
		if (! $text) {
			return '';
		}
		$html = html_escape ( $text );
		$html = str_replace ( "\n", '<br/>', $html );
		return $html;
	}
}
if (! function_exists ( 'output_json_result' )) {
	function output_json_result($result, $message = NULL, $object = NULL) {
		$data ['result'] = $result;
		$data ['message'] = $message;
		$data ['object'] = $object;
		$CI = & get_instance ();
		if ($error = error_get_last ()) {
			log_message ( 'error', '================= $error = ' . print_r ( $error, true ) );
		}
		$CI->output->set_content_type ( 'application/json' );
		$CI->output->set_output ( json_encode ( $data ) );
	}
}

if (! function_exists ( 'get_request_uri' )) {
	function get_request_uri() {
		$protocol = (! empty ( $_SERVER ['HTTPS'] ) && $_SERVER ['HTTPS'] !== 'off' || $_SERVER ['SERVER_PORT'] == 443) ? "https://" : "http://";
		$request_uri = $_SERVER ['REQUEST_URI'];
		$http_host = $_SERVER ['HTTP_HOST'];
		// Keep in mind that if the user is using proxy server (like PAC), REQUEST_URI will include the full request URL like http://example.com/path/
		if (strpos ( $request_uri, "{$protocol}{$http_host}" ) === 0) {
			return $request_uri;
		} else {
			if (isset ( $_SERVER ['SERVER_PORT'] ) && $port = $_SERVER ['SERVER_PORT']) {
				if ((($protocol == 'http' && $port != 80) || ($protocol == 'https' && $port != 443)) && ! preg_match ( '/\:\d+$/', $http_host )) {
					$http_host .= ":{$port}";
				}
			}
			return "{$protocol}{$http_host}{$request_uri}";
		}
	}
}
if (! function_exists ( 'generate_rand_string' )) {
	function generate_rand_string($length) {
		$charset = "abcdefghijklmnopqrstuvwxyz0123456789";
		$key = '';
		for($i = 0; $i < $length; $i ++)
			$key .= $charset [(mt_rand ( 0, (strlen ( $charset ) - 1) ))];
		
		return $key;
	}
}

if (! function_exists ( 'gen_uuid' )) {
	function gen_uuid() {
		return sprintf ( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x', 
				// 32 bits for "time_low"
				mt_rand ( 0, 0xffff ), mt_rand ( 0, 0xffff ), 
				
				// 16 bits for "time_mid"
				mt_rand ( 0, 0xffff ), 
				
				// 16 bits for "time_hi_and_version",
				// four most significant bits holds version number 4
				mt_rand ( 0, 0x0fff ) | 0x4000, 
				
				// 16 bits, 8 bits for "clk_seq_hi_res",
				// 8 bits for "clk_seq_low",
				// two most significant bits holds zero and one for variant DCE1.1
				mt_rand ( 0, 0x3fff ) | 0x8000, 
				
				// 48 bits for "node"
				mt_rand ( 0, 0xffff ), mt_rand ( 0, 0xffff ), mt_rand ( 0, 0xffff ) );
	}
}

if (! function_exists ( 'encode_uuid_base64' )) {
	function encode_uuid_base64($uuid) {
		$byteString = "";
		
		// Remove the dashes from the string
		$uuid = str_replace ( "-", "", $uuid );
		
		// Read the UUID string byte by byte
		for($i = 0; $i < strlen ( $uuid ); $i += 2) {
			// Get two hexadecimal characters
			$s = substr ( $uuid, $i, 2 );
			// Convert them to a byte
			$d = hexdec ( $s );
			// Convert it to a single character
			$c = chr ( $d );
			// Append it to the byte string
			$byteString = $byteString . $c;
		}
		
		// Convert the byte string to a base64 string
		$b64uuid = base64_encode ( $byteString );
		// Replace the "/" and "+" since they are reserved characters
		$b64uuid = strtr ( $b64uuid, '+/', '-_' );
		// Remove the trailing "=="
		$b64uuid = substr ( $b64uuid, 0, strlen ( $b64uuid ) - 2 );
		
		return $b64uuid;
	}
}
if (! function_exists ( 'decode_uuid_base64' )) {
	function decode_uuid_base64($b64uuid) {
		$b64uuid .= '==';
		$b64uuid = strtr ( $b64uuid, '-_', '+/' );
		$byteString = base64_decode ( $b64uuid );
		
		$uuid = "";
		for($i = 0; $i < strlen ( $byteString ); $i ++) {
			// Get a single character
			$c = substr ( $byteString, $i, 1 );
			// Convert it to a byte
			$d = ord ( $c );
			// Convert it to hexadecimal characters
			$s = str_pad ( dechex ( $d ), 2, '0', STR_PAD_LEFT );
			// Append it to the uuid string
			$uuid .= $s;
		}
		
		// Add dashes
		$uuid = substr_replace ( $uuid, '-', 8, 0 );
		$uuid = substr_replace ( $uuid, '-', 13, 0 );
		$uuid = substr_replace ( $uuid, '-', 18, 0 );
		$uuid = substr_replace ( $uuid, '-', 23, 0 );
		
		return $uuid;
	}
}

if (! function_exists ( 'download_as_csv' )) {
	function download_as_csv($filename, $data, $header = null) {
		if (is_app_browser ()) {
			redirect ( 'home/platform_error' );
		}
		// disable caching
		$now = gmdate ( "D, d M Y H:i:s" );
		header ( "Expires: Tue, 03 Jul 2001 06:00:00 GMT" );
		header ( "Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate" );
		header ( "Last-Modified: {$now} GMT" );
		
		// force download
		header ( "Content-Type: application/force-download" );
		header ( "Content-Type: application/octet-stream" );
		header ( "Content-Type: application/download" );
		
		// disposition / encoding on response body
		header ( "Content-Disposition: attachment;filename={$filename}" );
		header ( "Content-Transfer-Encoding: binary" );
		
		$fp = fopen ( 'php://output', 'w' );
		$BOM = "\xEF\xBB\xBF";
		fwrite ( $fp, $BOM );
		if ($header) {
			fputcsv ( $fp, $header );
		}
		foreach ( $data as $data_row ) {
			$fields = array ();
			foreach ( $data_row as $header_key => $cell ) {
				if (! $header || array_key_exists ( $header_key, $header )) {
					array_push ( $fields, $cell );
				}
			}
			fputcsv ( $fp, $fields );
		}
		fclose ( $fp );
		ob_flush ();
		die ();
	}
}

if (! function_exists ( 'download_as_xls' )) {
	function download_as_xls($filename, $sheets) {
		if (is_app_browser ()) {
			redirect ( 'home/platform_error' );
		}
		include_once 'Classes/PHPExcel.php';
		include_once 'Classes/PHPExcel/Writer/Excel2007.php';
		
		// Create new PHPExcel object
		$objPHPExcel = new PHPExcel ();
		
		// Set properties
		$objPHPExcel->getProperties ()->setCreator ( "Weee!" );
		$objPHPExcel->getProperties ()->setLastModifiedBy ( "Weee!" );
		$objPHPExcel->getProperties ()->setTitle ( preg_replace ( '/\\.[^.\\s]{3,4}$/', '', $filename ) );
		
		$objPHPExcel->removeSheetByIndex ( 0 );
		foreach ( $sheets as $sheet_num => $sheet ) {
			$objWorkSheet = $objPHPExcel->createSheet ( $sheet_num );
			if (isset ( $sheet ['title'] )) {
				$invalidCharacters = $objWorkSheet->getInvalidCharacters ();
				$sheet ['title'] = str_replace ( $invalidCharacters, '', $sheet ['title'] );
				// Maximum 31 characters allowed in sheet title
				if (mb_strlen ( $sheet ['title'], 'UTF-8' ) > 30) {
					$sheet ['title'] = mb_substr ( $sheet ['title'], 0, 30 );
				}
				$objWorkSheet->setTitle ( $sheet ['title'] );
			}
			
			if (! isset ( $sheet ['tables'] )) {
				log_message ( 'error', "download_as_xls: no tables in the sheet $sheet_num" );
				continue;
			}
			$tables = $sheet ['tables'];
			
			$current_row = 1;
			foreach ( $tables as $talbe_num => $table ) {
				$source = array ();
				
				if (isset ( $table ['title'] )) {
					array_push ( $source, [ 
							$table ['title'] 
					] );
				}
				
				$data = isset ( $table ['data'] ) ? $table ['data'] : array ();
				if (isset ( $table ['header'] )) {
					$header = $table ['header'];
					array_push ( $source, $header );
					foreach ( $data as $data_row ) {
						$fields = array ();
						foreach ( $header as $header_key => $header_label ) {
							$cell = isset ( $data_row [$header_key] ) ? $data_row [$header_key] : NULL;
							array_push ( $fields, $cell );
						}
						array_push ( $source, $fields );
					}
				} else {
					foreach ( $data as $data_row ) {
						$fields = array ();
						foreach ( $data_row as $cell ) {
							array_push ( $fields, $cell );
						}
						array_push ( $source, $fields );
					}
				}
				
				$objWorkSheet->fromArray ( $source, null, "A$current_row", true );
				$current_row += count ( $source ) + 2; // Set two blank lines
			}
		}
		$objPHPExcel->setActiveSheetIndex ( 0 );
		
		header ( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header ( 'Content-Disposition: attachment;filename="' . $filename . '"' );
		header ( 'Cache-Control: max-age=0' );
		// If you're serving to IE 9, then the following may be needed
		header ( 'Cache-Control: max-age=1' );
		
		// If you're serving to IE over SSL, then the following may be needed
		header ( 'Expires: Mon, 26 Jul 1997 05:00:00 GMT' ); // Date in the past
		header ( 'Last-Modified: ' . gmdate ( 'D, d M Y H:i:s' ) . ' GMT' ); // always modified
		header ( 'Cache-Control: cache, must-revalidate' ); // HTTP/1.1
		header ( 'Pragma: public' ); // HTTP/1.0
		
		$objWriter = PHPExcel_IOFactory::createWriter ( $objPHPExcel, 'Excel2007' );
		$objWriter->save ( 'php://output' );
		exit ();
	}
}

if (! function_exists ( 'set_operation_result_into_flashdata' )) {
	/**
	 *
	 * @param string $status
	 *        	'error','info','success','warning'
	 * @param string $message
	 */
	function set_operation_result_into_flashdata($status, $message, $extra_info = null) {
		$CI = & get_instance ();
		$data = array (
				'status' => $status,
				'message' => $message 
		);
		if ($extra_info) {
			$data = array_merge ( $data, $extra_info );
		}
		$operation_result = $CI->session->set_flashdata ( 'operation_result', $data );
	}
}

if (! function_exists ( 'save_operation_result_of_flashdata' )) {
	function save_operation_result_of_flashdata() {
		$CI = & get_instance ();
		$operation_result = $CI->session->flashdata ( 'operation_result' );
		if ($operation_result) {
			$CI->session->set_flashdata ( 'operation_result', $operation_result );
		}
	}
}

if (! function_exists ( 'to_sku_format' )) {
	function to_sku_format($id) {
		return str_pad ( $id, 5, '0', STR_PAD_LEFT );
	}
}

if (! function_exists ( 'redirect_to_input_zipcode' )) {
	function redirect_to_input_zipcode($redirect_url_after_input = '', $message = 'You don\'t have set your position. Input your zipcode or city First.') {
		$CI = & get_instance ();
		if ($message)
			$CI->session->set_flashdata ( 'flashmsg', $message );
		
		if ($redirect_url_after_input) {
			if (! preg_match ( '#^https?://#i', $redirect_url_after_input )) {
				$redirect_url_after_input = site_url ( $redirect_url_after_input );
			}
			$url = site_url ( 'home/zipcode' ) . '?redirect_url=' . urlencode ( $redirect_url_after_input );
			redirect ( $url, 'refresh' );
		} else {
			redirect ( 'home/zipcode', 'refresh' );
		}
	}
}

if (! function_exists ( 'build_level_html_content' )) {
	function build_level_html_content($owner, &$rank = null) {
		$rank_map = [ 
				'rank_0' => [ 
						'min' => 0,
						'max' => 0,
						'class_name' => 'level-icon-heart',
						'count' => 0 
				],
				'rank_1' => [ 
						'min' => 1,
						'max' => 3,
						'class_name' => 'level-icon-heart',
						'count' => 1 
				],
				'rank_2' => [ 
						'min' => 4,
						'max' => 10,
						'class_name' => 'level-icon-heart',
						'count' => 2 
				],
				'rank_3' => [ 
						'min' => 11,
						'max' => 40,
						'class_name' => 'level-icon-heart',
						'count' => 3 
				],
				'rank_4' => [ 
						'min' => 41,
						'max' => 90,
						'class_name' => 'level-icon-heart',
						'count' => 4 
				],
				'rank_5' => [ 
						'min' => 91,
						'max' => 150,
						'class_name' => 'level-icon-heart',
						'count' => 5 
				],
				'rank_6' => [ 
						'min' => 151,
						'max' => 250,
						'class_name' => 'level-icon-diamond-blue',
						'count' => 1 
				],
				'rank_7' => [ 
						'min' => 251,
						'max' => 500,
						'class_name' => 'level-icon-diamond-blue',
						'count' => 2 
				],
				'rank_8' => [ 
						'min' => 501,
						'max' => 1000,
						'class_name' => 'level-icon-diamond-blue',
						'count' => 3 
				],
				'rank_9' => [ 
						'min' => 1001,
						'max' => 2000,
						'class_name' => 'level-icon-diamond-blue',
						'count' => 4 
				],
				'rank_10' => [ 
						'min' => 2001,
						'max' => 5000,
						'class_name' => 'level-icon-diamond-blue',
						'count' => 5 
				],
				'rank_11' => [ 
						'min' => 5001,
						'max' => 10000,
						'class_name' => 'level-icon-crown-blue',
						'count' => 1 
				],
				'rank_12' => [ 
						'min' => 10001,
						'max' => 20000,
						'class_name' => 'level-icon-crown-blue',
						'count' => 2 
				],
				'rank_13' => [ 
						'min' => 20001,
						'max' => 50000,
						'class_name' => 'level-icon-crown-blue',
						'count' => 3 
				],
				'rank_14' => [ 
						'min' => 50001,
						'max' => 100000,
						'class_name' => 'level-icon-crown-blue',
						'count' => 4 
				],
				'rank_15' => [ 
						'min' => 100001,
						'max' => 200000,
						'class_name' => 'level-icon-crown-blue',
						'count' => 5 
				],
				'rank_16' => [ 
						'min' => 200001,
						'max' => 500000,
						'class_name' => 'level-icon-crown-yellow',
						'count' => 1 
				],
				'rank_17' => [ 
						'min' => 500001,
						'max' => 1000000,
						'class_name' => 'level-icon-crown-yellow',
						'count' => 2 
				],
				'rank_18' => [ 
						'min' => 1000001,
						'max' => 2000000,
						'class_name' => 'level-icon-crown-yellow',
						'count' => 3 
				],
				'rank_19' => [ 
						'min' => 2000001,
						'max' => 5000000,
						'class_name' => 'level-icon-crown-yellow',
						'count' => 4 
				],
				'rank_20' => [ 
						'min' => 5000001,
						'max' => 99999999,
						'class_name' => 'level-icon-crown-yellow',
						'count' => 5 
				] 
		];
		$owner_rank = null;
		foreach ( $rank_map as $key => $value ) {
			if (! isset ( $owner ['owner_level'] )) {
				$owner ['owner_level'] = '';
			}
			if ($owner ['owner_level'] >= $value ['min'] && $owner ['owner_level'] <= $value ['max']) {
				$owner_rank = $value;
				$rank = $owner_rank ['max'] - $owner ['owner_level'] + 1;
				break;
			}
		}
		
		$str = '';
		for($i = 0; $i < $owner_rank ['count']; $i ++) {
			$str .= '<i class="level-icon sp-main ' . $owner_rank ['class_name'] . '"></i>';
		}
		return $str;
	}
	if (! function_exists ( 'product_tag_map' )) {
		function product_tag_map() {
			$product_tag_options = get_meta_codes_array ( META_TYPE_PRODUCT_TAG );
			foreach ( $product_tag_options as $key => $label ) {
				$tag_map [$key] = [ 
						'label' => $label 
				];
			}
			return $tag_map;
		}
	}
}

if (! function_exists ( 'add_parameter_to_url' )) {
	function add_parameter_to_url($url, $parameterName, $parameterValue) {
		$replaceDuplicates = true;
		$urlhash = null;
		$cl = 0;
		if (strpos ( $url, '#' )) {
			$cl = strpos ( $url, '#' );
			$urlhash = substr ( $url, $cl, strlen ( $url ) );
		} else {
			$urlhash = '';
			$cl = strlen ( $url );
		}
		$sourceUrl = substr ( $url, 0, $cl );
		$urlParts = explode ( '?', $sourceUrl );
		$newQueryString = '';
		
		if (count ( $urlParts ) > 1) {
			$parameters = explode ( '&', $urlParts [1] );
			for($i = 0; $i < count ( $parameters ); $i ++) {
				$parameterParts = explode ( '=', $parameters [$i] );
				if (! ($replaceDuplicates && $parameterParts [0] == $parameterName)) {
					if ($newQueryString == '') {
						$newQueryString = '?';
					} else {
						$newQueryString .= '&';
					}
					$newQueryString .= $parameterParts [0] . '=' . (isset ( $parameterParts [1] ) ? $parameterParts [1] : '');
				}
			}
		}
		if ($newQueryString == '') {
			$newQueryString = '?';
		}
		if ($newQueryString != '' && $newQueryString != '?') {
			$newQueryString .= '&';
		}
		$newQueryString .= $parameterName . '=' . ($parameterValue ? $parameterValue : '');
		return $urlParts [0] . $newQueryString . $urlhash;
	}
}
if (! function_exists ( 'pass_whitelist' )) {
	function pass_whitelist($target, $type = UTIL_WHITELIST_TYPE_EMAIL) {
		log_message ( 'debug', '$target = ' . print_r ( $target, true ) );
		$CI = & get_instance ();
		if ($CI->config->config ['weee_deployment'] != 'dev') {
			log_message ( 'debug', 'Skip whitelist checking for production' );
			return true;
		}
		
		if (! $target) {
			log_message ( 'error', 'Whitelist check error: no target.' );
			return false;
		}
		
		if ($type == UTIL_WHITELIST_TYPE_WX_SNS) {
			$target = $CI->user_model->get_weee_user ( [ 
					'wxSnsOpenId' => $target 
			] );
			$target = $target ['Global_User_ID'];
		} else if ($type == UTIL_WHITELIST_TYPE_UA_CHANNEL) {
			$target = $CI->user_model->get_weee_user_with_extra_info_where ( [ 
					'ua_channel_id' => $target 
			] );
			$target = $target ['Global_User_ID'];
		}
		
		$file_path = '/etc/weee/notification_whitelist.txt';
		
		if (! file_exists ( $file_path )) {
			log_message ( 'error', 'whitelist config file not exists. path = ' . print_r ( $file_path, true ) );
			return false;
		}
		
		$content = file_get_contents ( $file_path );
		$whitelist = explode ( "\n", $content );
		if ($whitelist) {
			return in_array ( $target, $whitelist );
		}
		return false;
	}
}
if (! function_exists ( 'has_white_black_list' )) {
	function has_white_black_list($target_id, $type) {
		log_message ( 'debug', '$target_id = ' . print_r ( $target_id, true ) );
		log_message ( 'debug', '$type = ' . print_r ( $type, true ) );
		if (! $target_id || ! $type) {
			log_message ( 'error', 'White black list check error: parameter empty.' );
			return false;
		}
		
		switch ($type) {
			case UTIL_WHITELIST_TYPE_GB_DEAL :
				$list_name = 'gb_deal_white_black_list';
				$target_column = 'deal_id';
				break;
			default :
				$list_name = '';
				$target_column = '';
		}
		
		if (! $list_name) {
			log_message ( 'debug', 'no white black list for this type. $type = ' . print_r ( $type, true ) );
			return true;
		}
		
		$where [$target_column] = $target_id;
		$CI = & get_instance ();
		$CI->load->model ( 'white_black_list_model' );
		$result = $CI->white_black_list_model->query_white_black_list ( $where, $list_name );
		if (! $result) {
			return false;
		}
		
		return true;
	}
}
if (! function_exists ( 'pass_white_black_list' )) {
	function pass_white_black_list($user_id, $target_id, $type) {
		log_message ( 'debug', '$user_id = ' . print_r ( $user_id, true ) );
		log_message ( 'debug', '$target_id = ' . print_r ( $target_id, true ) );
		log_message ( 'debug', '$type = ' . print_r ( $type, true ) );
		if (! $user_id || ! $target_id || ! $type) {
			log_message ( 'error', 'White black list check error: parameter empty.' );
			return false;
		}
		
		switch ($type) {
			case UTIL_WHITELIST_TYPE_GB_DEAL :
				$list_name = 'gb_deal_white_black_list';
				$target_column = 'deal_id';
				break;
			default :
				$list_name = '';
				$target_column = '';
		}
		
		if (! $list_name) {
			log_message ( 'debug', 'no white black list for this type. $type' );
			return true;
		}
		
		$where [$target_column] = $target_id;
		$CI = & get_instance ();
		$CI->load->model ( 'white_black_list_model' );
		$white_black_lists = $CI->white_black_list_model->query_white_black_list_by_user_id ( $user_id, $where, $list_name );
		if (! $white_black_lists) {
			return true;
		}
		$in_black_list = false;
		$in_white_list = true;
		foreach ( $white_black_lists as $list ) {
			if ($list ['type'] == 'W' && ! $list ['user_id']) {
				$in_white_list = false;
			} else if ($list ['type'] == 'B' && $list ['user_id']) {
				$in_black_list = true;
			}
		}
		if ($in_black_list || ! $in_white_list) {
			return false;
		}
		
		return true;
	}
}
if (! function_exists ( 'in_blacklist' )) {
	function in_blacklist($user, $target_id) {
		$CI = & get_instance ();
		if (is_numeric ( $user )) {
			$user = $CI->user_model->get_weee_user_with_weixin_extra_info ( $user );
		}
		if (! $user ['black_list_id']) {
			return false;
		}
		$CI->load->model ( 'white_black_list_model' );
		$black_list = $CI->white_black_list_model->query_white_black_list_user_by_id ( $user ['black_list_id'] );
		if (! $black_list) {
			return false;
		}
		$black_list_users = array_column ( $black_list, 'user_id' );
		return in_array ( $target_id, $black_list_users );
	}
}

/**
 * ****************************************
 * MULTI LANGUAGE
 * ****************************************
 */
if (! function_exists ( 'query_column_defs_by_table' )) {
	function query_column_defs_by_table($table_name) {
		$CI = & get_instance ();
		$where ['table_name'] = $table_name;
		$CI->db->order_by ( 'id' );
		$query = $CI->db->get_where ( 'column_def', $where );
		return $query->result_array ();
	}
}
if (! function_exists ( 'get_column_def_by_column' )) {
	function get_column_def_by_column($table_name, $column_name) {
		$CI = & get_instance ();
		$where ['table_name'] = $table_name;
		$where ['column_name'] = $column_name;
		$CI->db->order_by ( 'id' );
		$query = $CI->db->get_where ( 'column_def', $where );
		return $query->row_array ();
	}
}
if (! function_exists ( 'replace_with_i18n_by_id' )) {
	function replace_with_i18n_by_id($table_name, $target_id, &$target, $target_columns = null, $user_language = null) {
		$CI = & get_instance ();
		$where ['table_name'] = $table_name;
		$where ['language'] = $user_language ? $user_language : get_site_language ();
		$where ['target_id'] = $target_id;
		$CI->db->select ( 'gb_i18n.*, column_def.column_name' );
		$CI->db->join ( 'column_def', 'column_def.id = gb_i18n.column_def_id' );
		$query = $CI->db->get_where ( 'gb_i18n', $where );
		$i18ns = $query->result_array ();
		foreach ( $i18ns as $i18n ) {
			if ($target_columns && is_array ( $target_columns ) && key_exists ( $i18n ['column_name'], $target_columns )) {
				$target [$target_columns [$i18n ['column_name']]] = $i18n ['content'];
			} else {
				$target [$i18n ['column_name']] = $i18n ['content'];
			}
		}
	}
}
if (! function_exists ( 'replace_batch_with_i18n' )) {
	function replace_batch_with_i18n($table_name, $target_ids, &$targets, $target_columns = null, $user_language = null) {
		$CI = & get_instance ();
		$where ['table_name'] = $table_name;
		$where ['language'] = $user_language ? $user_language : get_site_language ();
		$where ['target_id in (' . implode ( ',', $target_ids ) . ')'] = NULL;
		if ($target_columns) {
			$where ['column_name in (\'' . implode ( '\',\'', array_keys ( $target_columns ) ) . '\')'] = NULL;
		}
		$CI->db->select ( 'gb_i18n.*, column_def.column_name' );
		$CI->db->join ( 'column_def', 'column_def.id = gb_i18n.column_def_id' );
		$query = $CI->db->get_where ( 'gb_i18n', $where );
		$i18ns = $query->result_array ();
		log_message ( 'debug', '$i18ns: ' . print_r ( $i18ns, true ) );
		foreach ( $i18ns as $i18n ) {
			$target_id = $i18n ['target_id'];
			foreach ( $targets as &$target ) {
				if (key_exists ( 'id', $target ) && $target ['id'] != $target_id) {
					continue;
				}
				if ($target_columns && is_array ( $target_columns ) && key_exists ( $i18n ['column_name'], $target_columns )) {
					$target [$target_columns [$i18n ['column_name']]] = $i18n ['content'];
				} else {
					$target [$i18n ['column_name']] = $i18n ['content'];
				}
			}
		}
	}
}
if (! function_exists ( 'replace_products_with_i18n_by_target_id' )) {
	function replace_products_with_i18n_by_target_id($table_name, $target_id, &$products, $target_columns = null, $user_language = null) {
		$CI = & get_instance ();
		$where ['table_name'] = $table_name;
		$where ['language'] = $user_language ? $user_language : get_site_language ();
		$where ["target_id like '{$target_id}-%'"] = null;
		$CI->db->select ( 'gb_i18n.*, column_def.column_name' );
		$CI->db->join ( 'column_def', 'column_def.id = gb_i18n.column_def_id' );
		$query = $CI->db->get_where ( 'gb_i18n', $where );
		$i18ns = $query->result_array ();
		foreach ( $i18ns as $i18n ) {
			$product_id = substr ( $i18n ['target_id'], strlen ( "{$target_id}-" ) );
			foreach ( $products as &$product ) {
				if (key_exists ( 'product_id', $product ) && $product ['product_id'] != $product_id) {
					continue;
				} else if (! key_exists ( 'product_id', $product ) && $product ['id'] != $product_id) {
					continue;
				}
				if ($target_columns && is_array ( $target_columns ) && key_exists ( $i18n ['column_name'], $target_columns )) {
					$product [$target_columns [$i18n ['column_name']]] = $i18n ['content'];
				} else {
					$product [$i18n ['column_name']] = $i18n ['content'];
				}
			}
		}
	}
}

if (! function_exists ( 'replace_products_with_i18n_by_deal_id' )) {
	function replace_products_with_i18n_by_deal_id($deal_id, &$products, $target_columns = null, $user_language = null) {
		$CI = & get_instance ();
		$where ['gb_deal_product.deal_id'] = $deal_id;
		$where ['language'] = $user_language ? $user_language : get_site_language ();
		if ($target_columns) {
			$column_names = implode ( "','", array_keys ( $target_columns ) );
			$where ["column_def.column_name in ('{$column_names}')"] = null;
		}
		$CI->db->select ( 'gb_i18n.*, column_def.column_name ' );
		$CI->db->join ( 'gb_i18n', ' gb_deal_product.product_id = gb_i18n.target_id ' );
		$CI->db->join ( 'column_def', 'column_def.id = gb_i18n.column_def_id and column_def.table_name = "gb_product"' );
		$query = $CI->db->get_where ( 'gb_deal_product', $where );
		$i18ns = $query->result_array ();
		$product_i18ns = [ ];
		foreach ( $i18ns as $i18n ) {
			$product_i18ns [$i18n ['target_id']] [$target_columns [$i18n ['column_name']]] = $i18n ['content'];
		}
		foreach ( $products as &$product ) {
			if (key_exists ( $product ['product_id'], $product_i18ns )) {
				$exist_product_i18n = $product_i18ns [$product ['product_id']];
				$product = array_merge ( $product, $exist_product_i18n );
			}
		}
	}
}

if (! function_exists ( 'replace_products_with_i18n_by_resource_id' )) {
	function replace_products_with_i18n_by_resource_id($resource_id, &$products, $target_columns = null, $user_language = null) {
		$CI = & get_instance ();
		$where ['gb_deal_resource_product.deal_resource_id'] = $resource_id;
		$where ['language'] = $user_language ? $user_language : get_site_language ();
		if ($target_columns) {
			$column_names = implode ( "','", array_keys ( $target_columns ) );
			$where ["column_def.column_name in ('{$column_names}')"] = null;
		}
		$CI->db->select ( 'gb_i18n.*, column_def.column_name ' );
		$CI->db->join ( 'gb_i18n', ' gb_deal_resource_product.product_id = gb_i18n.target_id ' );
		$CI->db->join ( 'column_def', 'column_def.id = gb_i18n.column_def_id and column_def.table_name = "gb_product"' );
		$query = $CI->db->get_where ( 'gb_deal_resource_product', $where );
		$i18ns = $query->result_array ();
		
		$product_i18ns = [ ];
		foreach ( $i18ns as $i18n ) {
			$product_i18ns [$i18n ['target_id']] [$target_columns [$i18n ['column_name']]] = $i18n ['content'];
		}
		foreach ( $products as &$product ) {
			if (key_exists ( $product ['product_id'], $product_i18ns )) {
				$exist_product_i18n = $product_i18ns [$product ['product_id']];
				$product = array_merge ( $product, $exist_product_i18n );
			}
		}
	}
}

if (! function_exists ( 'get_wk_start_day' )) {
	function get_wk_start_day($time_str = null, $date_format = 'm/d/Y') {
		if ($time_str) {
			$time = strtotime ( $time_str );
		} else {
			$time = time ();
		}
		$wk_day = date ( 'w', $time );
		$wk_day = $wk_day == 0 ? 7 : $wk_day;
		$target_time = $time - ($wk_day - 1) * 60 * 60 * 24;
		$target_date = date ( $date_format, $target_time );
		return $target_date;
	}
}
if (! function_exists ( 'insert_activity_log' )) {
	function insert_activity_log($target_id, $type, $rec_creator_id, $comment = null) {
		$CI = & get_instance ();
		$CI->db->insert ( 'weee.activity_log', [ 
				'target_id' => $target_id,
				'type' => $type,
				'comment' => $comment,
				'rec_creator_id' => $rec_creator_id 
		] );
		if ($CI->db->_error_number ()) {
			log_message ( 'debug', '$this->db->last_query() = ' . print_r ( $CI->db->last_query (), true ) );
			log_message ( 'error', 'member_model.insert_activity_log: ' . $CI->db->_error_number () . ':' . $CI->db->_error_message () );
			return false;
		}
		return true;
	}
}

if (! function_exists ( 'generate_feed' )) {
	function generate_feed($type, $target_id, $last_action = null) {
		/**
		 *
		 * @var $deal_feed_biz Deal_feed_biz
		 */
		$deal_feed_biz = & load_class ( 'Deal_feed_biz', 'biz/deal', '' );
		$feed_generator = $deal_feed_biz->get_feed_generator_interface ( $type );
		$result = $feed_generator->generate_feed ( $target_id, $last_action );
		return $result;
	}
}
if (! function_exists ( 'format_during_time' )) {
	function format_during_time($time, $language = '') {
		if (! $language) {
			$language = get_site_language ();
		}
		
		$hour = intval ( $time / 3600 );
		$minute = intval ( $time / 60 ) % 60;
		
		if ($language == LANGUAGE_ENGLISH) {
			$hour_suffix = $hour > 1 ? 's' : '';
			$minute_suffix = $minute > 1 ? 's' : '';
			if ($hour && $minute) {
				return $hour . ' hour' . $hour_suffix . ' and ' . $minute . ' minute' . $minute_suffix;
			} else if ($hour) {
				return $hour . ' hour' . $hour_suffix;
			} else if ($minute) {
				return $minute . ' minute' . $minute_suffix;
			}
		} else {
			if ($hour && $minute) {
				return $hour . '小时' . $minute . '分';
			} else if ($hour) {
				return $hour . '小时';
			} else if ($minute) {
				return $minute . '分';
			}
		}
	}
}
if (! function_exists ( 'is_purchase_member_deal' )) {
	function is_purchase_member_deal($deal_ID) {
		if ($deal_ID == PURCHASE_MEMBER_DEAL_ID_1 || $deal_ID == PURCHASE_MEMBER_DEAL_ID_2) {
			return true;
		}
		return false;
	}
}

/**
 * ****************************************
 * Captcha
 * ****************************************
 */
define ( 'CAPRACHA_INACTIVE', 'C' );
define ( 'CAPRACHA_ACTIVE', 'A' );

if (! function_exists ( 'create_captcha_img' )) {
	function create_captcha_img($user_id = '', $reused = true) {
		$CI = & get_instance ();
		$CI->load->model ( 'captcha_model' );
		if ($user_id && $reused) {
			$captcha_where ['rec_creator_id'] = $user_id;
			$captcha_where ['rec_create_time >'] = time () - 3600;
			$captcha_where ['status'] = CAPRACHA_ACTIVE;
			$captcha_results = $CI->captcha_model->query_captcha ( $captcha_where, 'id desc', 1 );
			if ($captcha_results) {
				$return ['captcha_id'] = $captcha_results [0] ['id'];
				$return ['captcha_url'] = $captcha_results [0] ['url'];
				return $return;
			}
		}
		
		$CI->load->helper ( 'captcha' );
		$vals = array (
				'word' => rand ( 1000, 10000 ),
				'img_path' => CAPTCHA_BASE_PATH,
				'img_url' => CAPTCHA_URL_BASE,
				'img_width' => '80',
				'img_height' => '34',
				'expiration' => 7200,
				'word_length' => 4,
				'font_size' => 16 
		);
		$cap = create_captcha ( $vals );
		$captcha = array ();
		$captcha ['captcha_key'] = $cap ['word'];
		$captcha ['rec_creator_id'] = $user_id;
		$captcha ['url'] = CAPTCHA_URL_BASE . $cap ['time'] . '.jpg';
		$captcha ['rec_create_time'] = time ();
		$return ['captcha_id'] = $CI->captcha_model->insert_captcha ( $captcha );
		$return ['captcha_url'] = $captcha ['url'];
		
		return $return;
	}
}

if (! function_exists ( 'verfiy_captcha' )) {
	function verfiy_captcha($captcha_id, $key, &$error) {
		$CI = & get_instance ();
		$CI->load->model ( 'captcha_model' );
		$captcha_results = $CI->captcha_model->get_captcha ( $captcha_id );
		
		if (empty ( $captcha_results ) || ($captcha_results ['captcha_key'] != $key)) {
			$error = lang ( 'captcha_error' );
			return false;
		}
		
		if (($captcha_results ['status'] != CAPRACHA_ACTIVE) || ($captcha_results ['rec_create_time'] < time () - 3600)) {
			$error = lang ( 'captcha_timeout_error' );
			return false;
		}
		
		$captcha = array ();
		$captcha ['status'] = CAPRACHA_INACTIVE;
		$return = $CI->captcha_model->update_captcha ( $captcha_id, $captcha );
		return $return;
	}
}

if (! function_exists ( 'popup_notification' )) {
	function popup_notification($user_id, $page, $event_key = null) {
		if (! $user_id || ! $page) {
			return NULL;
		}
		
		$CI = & get_instance ();
		$CI->load->model ( 'notify_event_model' );
		$special_event_id = 0;
		$special_event = $CI->notify_event_model->get_special_event_with_user ( $user_id, $page );
		if ($special_event && empty ( $special_event ['user_id'] ) && strtotime ( $special_event ['end_time'] ) > time ()) {
			// The special event is supported on the page and no user row, insert a new row.
			$notify_event_user = [ 
					'notify_event_id' => $special_event ['id'],
					'user_id' => $user_id,
					'status' => 'A',
					'end_time' => $special_event ['end_time'],
					'next_notification_time' => date ( 'Y-m-d H:i:s' ),
					'parameters' => $special_event ['total'] 
			];
			$special_event_id = $CI->notify_event_model->insert_notify_event_user ( $notify_event_user );
		} else if ($special_event && ! empty ( $special_event ['user_id'] )) {
			$special_event_id = $special_event ['notify_event_user_id'];
		}
		
		$user_popup_notify_event = $CI->notify_event_model->get_user_next_popup_notify_event ( $user_id, $page, $event_key );
		if ($user_popup_notify_event) {
			$update_data ['status'] = $user_popup_notify_event ['status'];
			if (! $user_popup_notify_event ['period_day']) {
				// once notification
				$update_data ['status'] = 'C';
			} else {
				$next_notification_time = time () + 24 * 3600 * $user_popup_notify_event ['period_day'];
				if ($user_popup_notify_event ['end_time'] && strtotime ( $user_popup_notify_event ['end_time'] ) < $next_notification_time) {
					// out of date
					$update_data ['status'] = 'C';
				} else if ($special_event_id == $user_popup_notify_event ['id'] && $user_popup_notify_event ['parameters'] > 1) {
					// show next popup of the special event
					$update_data ['parameters'] = $user_popup_notify_event ['parameters'] - 1;
					$update_data ['next_notification_time'] = date ( 'Y-m-d H:i:s', time () + 5 * 60 );
				} else {
					if ($special_event_id == $user_popup_notify_event ['id']) {
						// reset special event popup count
						$update_data ['parameters'] = $user_popup_notify_event ['total'];
					}
					// set next show time
					$update_data ['next_notification_time'] = date ( 'Y-m-d H:i:s', $next_notification_time );
				}
			}
			$CI->notify_event_model->update_notify_event_user ( $user_popup_notify_event ['id'], $update_data );
		}
		
		return $user_popup_notify_event;
	}
}

if (! function_exists ( 'build_popup_notification_js' )) {
	function build_popup_notification_js($popup_notification) {
		if (! $popup_notification) {
			return '';
		}
		return "window['{$popup_notification['popup_js']}'].apply(this, [{$popup_notification['parameters']}]);";
	}
}

if (! function_exists ( 'build_section_rows' )) {
	function build_section_rows($rows) {
		$content = '';
		foreach ( $rows as $row ) {
			if ($row === 'SEPARATOR') {
				$content .= '<div class="box-shadow-empty"></div>' . "\n";
				continue;
			}
			$content .= '<div class="section-row"';
			if (! empty ( $row ['url'] )) {
				if (! preg_match ( '!^javascript:! i', $row ['url'] )) {
					$row ['url'] = site_url ( $row ['url'] );
				}
				$content .= ' data-url="' . $row ['url'] . '"';
			}
			$content .= '><span class="row-label">' . $row ['title'] . '</span>';
			if (! empty ( $row ['tip'] )) {
				$content .= '<span class="row-indicator-text">' . $row ['tip'] . '</span>';
			}
			if (! empty ( $row ['url'] )) {
				$content .= '<span class="row-indicator"></span>';
			}
			$content .= "</div>\n";
		}
		return $content;
	}
}

if (! function_exists ( 'get_weekday_labels' )) {
	function get_weekday_labels($weekdays_str) {
		$weekdays_str = str_split ( $weekdays_str, 1 );
		$weekday_zh_en = [ 
				lang ( 'common_sunday' ),
				lang ( 'common_monday' ),
				lang ( 'common_tuesday' ),
				lang ( 'common_wednesday' ),
				lang ( 'common_thursday' ),
				lang ( 'common_friday' ),
				lang ( 'common_saturday' ) 
		];
		$weekdays = '';
		foreach ( $weekdays_str as $weekday ) {
			$weekdays [] = $weekday_zh_en [$weekday];
		}
		return $weekdays;
	}
}

if (! function_exists ( 'get_user_credit_card' )) {
	function get_user_credit_card($user_id) {
		$CI = & get_instance ();
		$credit_cards = $CI->user_model->query_user_credit_card ( [ 
				'user_id' => $user_id,
				'status' => 'A' 
		], 'id desc', 1 );
		if (! $credit_cards) {
			return false;
		}
		return $credit_cards [0];
	}
}

if (! function_exists ( 'show_credit_card' )) {
	function show_credit_card($user) {
		if (is_weixin_browser () && ! is_ios_browser ()) {
			return false;
		}
		$config = get_config_value ( WEEE_CONFIG_STRIPE, true );
		$permission = $config ['extra_value_4'];
		if (! $permission) {
			return false;
		}
		$only_for_admin = $config ['extra_value_5'];
		if ($only_for_admin) {
			return is_admin_user ( $user );
		}
		return true;
	}
}

if (! function_exists ( 'check_user_activity_frequence' )) {
	function check_user_activity_frequence($user_id, $page_time) {
		$CI = & get_instance ();
		$CI->db->trans_start ();
		$sql = 'select * from weee.user_active_log where user_id = ? for update';
		$query = $CI->db->query ( $sql, $user_id );
		$user_log = $query->row_array ();
		if ($user_log) {
			// The diff time of two activity should be greater than 5s
			if (strtotime ( $user_log ['last_active_time'] ) >= time () - 5) {
				$CI->db->trans_complete ();
				return false;
			} else {
				$data = [ 
						'last_active_time' => date ( 'Y-m-d H:i:s', time () ),
						'page_time' => $page_time,
						'result' => null 
				];
				$result = $CI->user_model->update_user_active_log ( [ 
						'id' => $user_log ['id'] 
				], $data );
				$CI->db->trans_complete ();
				return $user_log;
			}
		} else {
			$data = [ 
					'user_id' => $user_id,
					'last_active_time' => date ( 'Y-m-d H:i:s', time () ),
					'page_time' => $page_time,
					'result' => null 
			];
			$CI->user_model->insert_user_active_log ( $data );
			$CI->db->trans_complete ();
			return $data;
		}
	}
}

if (! function_exists ( 'is_function_list' )) {
	function is_function_list($function_key) {
		$is_app_ios = $is_app_android = false;
		if (is_app_browser ()) {
			if (is_ios_browser ()) {
				$is_app_ios = true;
			} else {
				$is_app_android = true;
			}
		}
		$version = '';
		if (isset ( $_SERVER ['HTTP_USER_AGENT'] ) && preg_match ( '/WeeeApp ((\d+.){2})/', $_SERVER ['HTTP_USER_AGENT'], $matches )) {
			$version = $matches [1];
		}
		if ($function_key === FUNCTION_KEY_NEW_YEAR_CATEGORY_STYLE) {
			if ($is_app_ios) {
				return $version >= 4 && $version <= 4.1;
			} else if ($is_app_android) {
				return false;
			} else {
				return true;
			}
		}
		if ($function_key === FUNCTION_KEY_ZH_HANT_SUPPORT) {
			if ($is_app_ios) {
				return $version >= 4.3;
			} else if ($is_app_android) {
				return $version >= 4;
			} else {
				return true;
			}
		}
		if ($function_key === FUNCTION_KEY_PREFERENCE_PRODUCT_NUM_GREATER) {
			if ($is_app_ios) {
				return $version >= 4;
			} else {
				return true;
			}
		}
		if ($function_key === FUNCTION_KEY_V2_CATEGORY_STYLE) {
			if ($is_app_ios) {
				return $version >= 4.0;
			} else if ($is_app_android) {
				return $version >= 3.7;
			} else {
				return true;
			}
		}
		if ($function_key === FUNCTION_KEY_FOOTER_POST_SUPPORT) {
			if ($is_app_ios) {
				return $version >= 4.4;
			} else if ($is_app_android) {
				return $version >= 4.0;
			} else {
				return true;
			}
		}
		if ($function_key === FUNCTION_KEY_APP_SHARE_CALLBACK_SUPPOET) {
			if ($is_app_ios) {
				return $version >= 4;
			} else if ($is_app_android) {
				return false;
			} else {
				return true;
			}
		}
		if ($function_key === FUNCTION_KEY_GIFT_PRODUCT_SUPPORT) {
			if ($is_app_ios) {
				return $version > 4.3;
			} else if ($is_app_android) {
				return $version >= 4.0;
			} else {
				return true;
			}
		}
		return false;
	}
}

if (! function_exists ( 'extract_phone_number' )) {
	function extract_phone_number($phone_number) {
		$phone_number = str_replace ( array (
				'+1',
				'+',
				'-' 
		), '', $phone_number );
		if (strlen ( $phone_number ) === 11) {
			$phone_number = substr ( $phone_number, - 10 );
		}
		return $phone_number;
	}
}

if (! function_exists ( 'get_unused_signup_coupon' )) {
	function get_unused_signup_coupon($user) {
		$unused_signup_coupon = null;
		if ($user) {
			$coupon_where ['gb_coupon_code.user_id'] = $user ['Global_User_ID'];
			$coupon_where ['gb_coupon_code.remark'] = COUPON_REMARK_SING_UP;
			$coupon_where ['gb_coupon_code.expiration_time > now()'] = null;
			$coupon_where ['gb_coupon.order_id is null'] = null;
			$CI = & get_instance ();
			$CI->load->model ( 'groupbuy_model' );
			$signup_coupons = $CI->groupbuy_model->query_coupon_code_with_user ( $coupon_where, 'gb_coupon.rec_create_time desc', 1 );
			if ($signup_coupons) {
				$unused_signup_coupon = $signup_coupons [0];
			}
		}
		return $unused_signup_coupon;
	}
}
