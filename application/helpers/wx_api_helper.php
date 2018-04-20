<?php
if (! defined ( 'BASEPATH' ))
	exit ( 'No direct script access allowed' );

if (! function_exists ( 'is_weixin_browser' )) {
	function is_weixin_browser() {
		if (isset ( $_SERVER ['HTTP_USER_AGENT'] ) && (strpos ( $_SERVER ['HTTP_USER_AGENT'], 'MicroMessenger' ) !== false || strpos ( $_SERVER ['HTTP_USER_AGENT'], 'Windows Phone' ) !== false)) {
			return true;
		}
		return false;
	}
}

if (! function_exists ( 'call_wx_api' )) {
	function call_wx_api($url) {
		log_message ( 'debug', "call_wx_api: $url" );
		$ch = curl_init ( $url );
		curl_setopt ( $ch, CURLOPT_URL, $url );
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
		// curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
		$result_json = curl_exec ( $ch );
		if (curl_errno ( $ch )) {
			log_message ( 'error', 'Faild to call wx api: ' . curl_error ( $ch ) );
			log_message ( 'error', 'curl_getinfo: ' . print_r ( curl_getinfo ( $ch ), true ) );
			curl_close ( $ch );
			return FALSE;
		}
		curl_close ( $ch ); // close curl handle
		
		$result = json_decode ( $result_json, true );
		if (! $result) {
			log_message ( 'error', 'Faild to parse the wx api response: ' . print_r ( $result_json, TRUE ) );
			return FALSE;
		}
		
		if (array_key_exists ( 'errcode', $result ) && $result ['errcode']) {
			log_message ( 'error', "Failed to call WX API: url = {$url}, result = {$result_json}" );
			return FALSE;
		}
		
		return $result;
	}
}

if (! function_exists ( 'call_wx_post_api' )) {
	function call_wx_post_api($url, $body, &$result_msg = null) {
		log_message ( 'debug', "call_wx_post_api: $url body = $body" );
		
		$ch = curl_init ( $url );
		curl_setopt ( $ch, CURLOPT_CUSTOMREQUEST, "POST" );
		curl_setopt ( $ch, CURLOPT_POSTFIELDS, $body );
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt ( $ch, CURLOPT_HTTPHEADER, array (
				'Content-Type: application/json' 
		) );
		// curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
		
		if ($result_msg)
			$result_msg ['start_time'] = date ( "Y-m-d H:i:s", time () );
		$result_json = curl_exec ( $ch );
		if ($result_msg)
			$result_msg ['end_time'] = date ( "Y-m-d H:i:s", time () );
		if (curl_errno ( $ch )) {
			if ($result_msg) {
				$result_msg ['result'] = curl_getinfo ( $ch );
			}
			log_message ( 'error', 'Faild to call wx api: ' . curl_error ( $ch ) );
			log_message ( 'error', 'curl_getinfo: ' . print_r ( curl_getinfo ( $ch ), true ) );
			log_message ( 'error', 'body : ' . print_r ( $body, true ) );
			curl_close ( $ch );
			return FALSE;
		}
		curl_close ( $ch ); // close curl handle
		
		if ($result_msg) {
			$result_msg ['result'] = $result_json;
		}
		$result = json_decode ( $result_json, true );
		if (! $result) {
			log_message ( 'error', 'Faild to parse the wx api response: ' . print_r ( $result_json, TRUE ) );
			return FALSE;
		}
		
		if (array_key_exists ( 'errcode', $result ) && $result ['errcode']) {
			log_message ( 'error', "Failed to call WX API: url = {$url}, body = {$body}, result = {$result_json}" );
			return FALSE;
		}
		
		return $result;
	}
}

if (! function_exists ( 'get_wx_access_token' )) {
	function get_wx_access_token($type = WX_API) {
		$config_key_mapping = [ 
				WX_API => 'wx_access_token',
				WX_XCH_API => 'wx_xch_access_token',
				XCH_SOLITAIRE => 'wx_xch_access_token',
				XCH_RESTAURANT => 'wx_restaurant_access_token' 
		];
		if (! array_key_exists ( $type, $config_key_mapping )) {
			log_message ( 'error', 'get_wx_access_token: error type ' . $type );
			return false;
		}
		$config_key = $config_key_mapping [$type];
		
		$access_token = NULL;
		$values = get_config_value ( $config_key, true );
		if ($values ['extra_value_1'] < time ()) {
			log_message ( 'debug', "The wx access token [{$config_key}] is timeout. Retrieving..." );
			
			$CI = & get_instance ();
			$token_server = $CI->config->config ['wx_token_server'];
			
			$success = false;
			if (strpos ( site_url (), $token_server ) !== FALSE) {
				// retrieve the wx access token from the weixin server
				$appid = $CI->config->config ['wx'] [$type] ['appid'];
				$appsecret = $CI->config->config ['wx'] [$type] ['appsecret'];
				$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$appid&secret=$appsecret";
				$result = call_wx_api ( $url );
				if ($result) {
					$success = true;
					$access_token = $result ['access_token'];
					$refresh_time = time () + 7000;
				}
			} else {
				// retrieve the wx acces token from our product server
				$url = "https://{$token_server}/weixin/get_access_token_config?type={$type}";
				$result = call_wx_api ( $url );
				if ($result ['result']) {
					$success = true;
					$access_token = $result ['object'] ['value'];
					$refresh_time = $result ['object'] ['extra_value_1'];
				}
			}
			if ($success) {
				log_message ( 'debug', "Refreshed the wx access token successfully. token = $access_token" );
				set_config_value ( $config_key, $access_token, array (
						'extra_value_1' => $refresh_time 
				) );
			}
		} else {
			log_message ( 'debug', "Got the wx access token from the table." );
			$access_token = $values ['value'];
		}
		return $access_token;
	}
}

if (! function_exists ( 'get_wx_jsapi_ticket' )) {
	function get_wx_jsapi_ticket() {
		$access_token = get_wx_access_token ();
		if (! $access_token) {
			return false;
		}
		
		$jsapi_ticket = NULL;
		$values = get_config_value ( 'wx_jsapi_ticket', true );
		if ($values ['extra_value_1'] < time ()) {
			log_message ( 'debug', "The wx js api ticket is timeout. Retrieving..." );
			
			$CI = & get_instance ();
			$token_server = $CI->config->config ['wx_token_server'];
			
			$success = false;
			if (strpos ( site_url (), $token_server ) !== FALSE) {
				// retrieve the wx ticket from the weixin server
				$url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=$access_token";
				$result = call_wx_api ( $url );
				if ($result) {
					$success = true;
					$jsapi_ticket = $result ['ticket'];
					$refresh_time = time () + 7000;
				}
			} else {
				// retrieve the wx ticket from our product server
				$url = "https://{$token_server}/weixin/get_jsapi_ticket_config";
				$result = call_wx_api ( $url );
				if ($result ['result']) {
					$success = true;
					$jsapi_ticket = $result ['object'] ['value'];
					$refresh_time = $result ['object'] ['extra_value_1'];
				}
			}
			if ($success) {
				log_message ( 'debug', "Refreshd the js api ticket successfully. js_api_token = $jsapi_ticket" );
				set_config_value ( 'wx_jsapi_ticket', $jsapi_ticket, array (
						'extra_value_1' => $refresh_time 
				) );
			}
		} else {
			log_message ( 'debug', "Got the js api ticket from the table." );
			$jsapi_ticket = $values ['value'];
		}
		return $jsapi_ticket;
	}
}

if (! function_exists ( 'get_wx_js_sign_package' )) {
	function get_wx_js_sign_package($url) {
		$jsapi_ticket = get_wx_jsapi_ticket ();
		if (! $jsapi_ticket) {
			return false;
		}
		
		$timestamp = time ();
		
		// create nonce string
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
		$nonce_str = "";
		for($i = 0; $i < 16; $i ++) {
			$nonce_str .= substr ( $chars, mt_rand ( 0, strlen ( $chars ) - 1 ), 1 );
		}
		
		$string = "jsapi_ticket=$jsapi_ticket&noncestr=$nonce_str&timestamp=$timestamp&url=$url";
		$signature = sha1 ( $string );
		
		$CI = & get_instance ();
		$sign_package = array (
				"appId" => $appid = get_config_value ( 'wx_appid' ),
				"nonceStr" => $nonce_str,
				"timestamp" => $timestamp,
				"url" => $url,
				"signature" => $signature,
				"rawString" => $string 
		);
		return $sign_package;
	}
}

if (! function_exists ( 'get_weixin_user_info' )) {
	function get_weixin_user_info($weixin_open_id, $access_token = NULL) {
		if (! $access_token) {
			$access_token = get_wx_access_token ();
		}
		$url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=$access_token&openid=$weixin_open_id&lang=zh_CN";
		$result = call_wx_api ( $url );
		return $result;
	}
}

if (! function_exists ( 'send_weixin_message' )) {
	function send_weixin_message($access_token, $weixin_open_id, $message) {
		$url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=$access_token&openid=$weixin_open_id&lang=zh_CN";
		$data = array (
				'touser' => $weixin_open_id,
				'msgtype' => 'text',
				'text' => array (
						'content' => $message 
				) 
		);
		
		$result = call_wx_post_api ( $url, json_encode ( $data ) );
		return $result;
	}
}

if (! function_exists ( 'get_sns_access_info_array' )) {
	/**
	 * Query WX user API to get the user info by code
	 *
	 * @param string $code
	 * @param string $wx_api_type
	 *        	[wx_api | wx_web_api | wx_app_api]
	 * @return array The array of access information.
	 * @example $result = {
	 *          "access_token":"ACCESS_TOKEN",
	 *          "expires_in":7200,
	 *          "refresh_token":"REFRESH_TOKEN",
	 *          "openid":"OPENID",
	 *          "scope":"SCOPE"
	 *          }
	 */
	function get_sns_access_info_array($code, $wx_api_type = 'wx_api') {
		if (WEEE_DEV_DEBUG) {
			return array (
					"access_token" => "DEBUG_ACCESS_TOKEN",
					"expires_in" => 7200,
					"refresh_token" => "DEBUG_REFRESH_TOKEN",
					"openid" => "DEBUG_WX_SNS_OPENID",
					"scope" => "snsapi_userinfo" 
			);
		}
		
		$CI = & get_instance ();
		$appid = $CI->config->config [$wx_api_type] ['appid'];
		$appsecret = $CI->config->config [$wx_api_type] ['appsecret'];
		$url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=$appid&secret=$appsecret&code=$code&grant_type=authorization_code";
		$result = call_wx_api ( $url );
		if ($result) {
			log_message ( 'debug', 'get_sns_access_info_array: ' . print_r ( $result, true ) );
		}
		return $result;
	}
}

if (! function_exists ( 'refresh_wx_access_token' )) {
	/**
	 * Refresh WX access token
	 *
	 * @param string $refresh_token
	 * @param string $wx_api_type
	 *        	[wx_api | wx_web_api | wx_app_api]
	 * @return array The result of refreshed token information.
	 * @example $result = {
	 *          "access_token":"ACCESS_TOKEN",
	 *          "expires_in":7200,
	 *          "refresh_token":"REFRESH_TOKEN",
	 *          "openid":"OPENID",
	 *          "scope":"SCOPE"
	 *          }
	 */
	function refresh_wx_access_token($refresh_token, $wx_api_type) {
		$CI = & get_instance ();
		$appid = $CI->config->config [$wx_api_type] ['appid'];
		$url = "https://api.weixin.qq.com/sns/oauth2/refresh_token?appid=$appid&grant_type=refresh_token&refresh_token=$refresh_token";
		$result = call_wx_api ( $url );
		if ($result) {
			log_message ( 'debug', 'refresh_wx_access_token: ' . print_r ( $result, true ) );
		}
		return $result;
	}
}

if (! function_exists ( 'auth_wx_access_token' )) {
	/**
	 * Check the authority of the access token for the user(openid)
	 *
	 * @param string $access_token
	 * @param string $openid
	 *        	[wx_api | wx_web_api | wx_app_api]
	 * @return TRUE/FALSE.
	 * @example $result e.g. {"errcode":0,"errmsg":"ok" }, { "errcode":40003,"errmsg":"invalid openid"}
	 */
	function check_wx_access_token_auth($access_token, $openid) {
		$CI = & get_instance ();
		$url = "https://api.weixin.qq.com/sns/auth?access_token=$access_token&openid=$openid";
		$result = call_wx_api ( $url );
		if ($result) {
			return true;
		} else {
			return false;
		}
	}
}

if (! function_exists ( 'get_sns_weixin_user_info' )) {
	function get_sns_weixin_user_info($access_token, $weixin_open_id) {
		if (WEEE_DEV_DEBUG) {
			return array (
					"openid" => "DEBUG_WX_SNS_OPENID",
					"nickname" => "DEBUG_NICK_NAME",
					"sex" => "1",
					"province" => "PROVINCE",
					"city" => "CITY",
					"country" => "COUNTRY",
					"headimgurl" => "http://wx.qlogo.cn/mmopen/g3MonUZtNHkdmzicIlibx6iaFqAc56vxLSUfpb6n5WKSYVY0ChQKkiaJSgQ1dZuTOgvLLrhJbERQQ4eMsv84eavHiaiceqxibJxCfHe/46",
					"unionid" => "DEBUG_WX_UNION_ID" 
			);
		}
		
		$url = "https://api.weixin.qq.com/sns/userinfo?access_token=$access_token&openid=$weixin_open_id&lang=zh_CN";
		$result = call_wx_api ( $url );
		return $result;
	}
}

if (! function_exists ( 'send_wx_template_mesage' )) {
	function send_wx_template_mesage($weixin_open_id, $template_id, $details_url, $parameters, $target_id = NULL, $update_type = API_HISTORY_UPDATE_TYPE_SYNC) {
		$body = json_encode ( array (
				'touser' => $weixin_open_id,
				'template_id' => $template_id,
				'url' => $details_url,
				'topcolor' => '#FF0000',
				'data' => $parameters 
		), JSON_UNESCAPED_UNICODE );
		
		$CI = & get_instance ();
		$CI->load->model ( 'api_history_model' );
		$data = [ 
				'type' => API_HISTORY_TYPE_WEIXIN,
				'target_id' => $target_id ? $target_id : '',
				'parameter_1' => $template_id,
				'input' => $body 
		];
		$insert_id = $CI->api_history_model->insert_api_history ( $data );
		if (! $insert_id) {
			return false;
		}
		
		switch ($update_type) {
			case API_HISTORY_UPDATE_TYPE_NO_SEND :
				$result = $insert_id;
				break;
			case API_HISTORY_UPDATE_TYPE_SYNC :
				$result = send_wx_template_message_by_id ( $insert_id, $body );
				break;
			case API_HISTORY_UPDATE_TYPE_ASYNC :
				$this->load->helper ( 'message' );
				$message_id_array = [ 
						$insert_id 
				];
				$result = api_send_ultra_messages_by_id ( $message_id_array );
				break;
			default :
				;
		}
		
		return $result;
	}
}

if (! function_exists ( 'send_wx_template_message_by_id' )) {
	function send_wx_template_message_by_id($api_history_id, $body) {
		$CI = & get_instance ();
		$body_data = json_decode ( $body, true );
		if (! pass_whitelist ( $body_data ['touser'], UTIL_WHITELIST_TYPE_WX_SNS )) {
			$data = [ 
					'status' => API_HISTORY_STATUS_SUCCESSED,
					'message' => '',
					'duration_time' => 0 
			];
			$CI->api_history_model->update_api_history ( $api_history_id, $data );
			log_message ( 'debug', 'Skip weixin template message sending for development. body:' . $body );
			return true;
		}
		
		$access_token = get_wx_access_token ();
		$url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=$access_token";
		$result_msg = [ 
				'start_time' => '',
				'end_time' => '',
				'result' => '' 
		];
		$result = call_wx_post_api ( $url, $body, $result_msg );
		$start_time = strtotime ( $result_msg ['start_time'] );
		$end_time = strtotime ( $result_msg ['end_time'] );
		$data = [ 
				'status' => $result ? API_HISTORY_STATUS_SUCCESSED : API_HISTORY_STATUS_FAILED,
				'message' => json_encode ( $result_msg ),
				'duration_time' => $end_time - $start_time 
		];
		$CI->api_history_model->update_api_history ( $api_history_id, $data );
		return $result;
	}
}

if (! function_exists ( 'send_wx_xch_template_message' )) {
	function send_wx_xch_template_message($weixin_open_id, $template_id, $page, $form_id, $parameters) {
		$body = json_encode ( array (
				'touser' => $weixin_open_id,
				'template_id' => $template_id,
				'page' => $page,
				'form_id' => $form_id,
				'data' => $parameters,
				'color' => '',
				'emphasis_keyword' => '' 
		), JSON_UNESCAPED_UNICODE );
		
		$access_token = get_wx_access_token ( WX_XCH_API );
		$url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=$access_token";
		$result = call_wx_post_api ( $url, $body, $result_msg );
		return $result;
	}
}

if (! function_exists ( 'build_wx_template_parameters' )) {
	function build_wx_template_parameters($parameters, $color_default = '#173177', $color_remark = '#BBBBBB', $color_customized = null) {
		$parameters_with_color = array ();
		foreach ( $parameters as $parameter_key => $parameter_value ) {
			if (isset ( $color_customized [$parameter_key] )) {
				$color = $color_customized [$parameter_key];
			} else if ('remark' === $parameter_key) {
				$color = $color_remark;
			} else {
				$color = $color_default;
			}
			$parameters_with_color [$parameter_key] = [ 
					'value' => $parameter_value,
					'color' => $color 
			];
		}
		return $parameters_with_color;
	}
}
