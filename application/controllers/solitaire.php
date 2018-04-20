<?php
defined('BASEPATH') OR exit('No direct script access allowed');

define ( 'MAX_END_TIME', 2145888000 );
define ( 'NO_LIMITED_QUANTITY', "-1" );
define ( 'SOLITAIRE_CLOSED_STATUS', 'C' );
define ( 'SOLITAIRE_OPENED_STATUS', 'A' );

define ( 'PATH_EVENT_IMAGE_SHARE', '/var/uploads/weee_img/xch_solitaire_share/' );
define ( 'PATH_EVENT_IMAGE_USER', '/var/uploads/weee_img/xch_solitaire_share/user/' );
define ( 'URL_EVENT_IMAGE_SHARE_BASE', '/static_img/xch_solitaire_share/' );
/**
 *
 * @property Event_model $event_model
 */
class Solitaire extends CI_Controller {
	function __construct() {
		parent::__construct ();
		$this->load->model ( 'user_model' );
		$this->load->model ( 'event_model' );
		$this->load->helper ( 'utils' );
	}
	function api($path1 = null, $path2 = null, $path3 = null) {
		log_message ( 'debug', 'REQUEST_URI = ' . $_SERVER ['REQUEST_URI'] );
		log_message ( 'debug', '$this->input->post() = ' . print_r ( $this->input->post (), true ) );
		if ($path1 === 'events') {
			if (! $path2) {
				return $this->_query_user_events ();
			}
			$event_id = $path2;
			if ($event_id === 'new' || $event_id === 'post') {
				return $this->_update_event ();
			}
			
			if ($event_id === 'join') {
				return $this->_query_user_join_events ();
			}
			
			$action = $path3;
			if (! $action) {
				return $this->_simple_get_event ( $event_id );
			}
			switch ($action) {
				case 'update' :
					return $this->_update_event ( $event_id );
				case 'markend' :
					return $this->_mark_event_end ( $event_id );
				case 'signups' :
					return $this->_query_event_signups ( $event_id );
				case 'signup_post' :
					return $this->_sign_up_post ( $event_id );
				case 'copy' :
					return $this->_copy_event ( $event_id );
				case 'delete' :
					return $this->_delete_event ( $event_id );
			}
		} else if ($path1 === 'help') {
			return $this->_help_settings ();
		} else if ($path1 === 'email') {
			return $this->_email ();
		} else if ($path1 === 'share') {
			return $this->_share ();
		}
		return output_json_result ( false, 'Not implemented' );
	}
	private function _query_user_events() {
		$user_id = $this->input->get ( 'user_id' );
		$offset = $this->input->get ( 'offset' ) ? $this->input->get ( 'offset' ) : 0;
		$limit = $this->input->get ( 'limit' ) ? $this->input->get ( 'limit' ) : 10;
		$user = $this->user_model->get_weee_user_by_global_id ( $user_id );
		if (! $user) {
			return output_json_result ( false, 'Invalid user ID' );
		}
		
		$events_with_details = $this->event_model->query_solitaire_events_and_deals ( $user_id, $limit, $offset );
		$count = $this->event_model->count_solitaire_events_and_deals ( $user_id );
		$events = [ ];
		foreach ( $events_with_details as $event ) {
			$event ['rec_create_time'] = strtotime ( $event ['rec_create_time'] );
			$event ['closed'] = ($event ['status'] == SOLITAIRE_CLOSED_STATUS || $event ['end_time'] < time ());
			$events [] = $event;
		}
		return output_json_result ( true, null, [ 
				'count' => $count,
				'offset' => $offset,
				'limit' => $limit,
				'event' => $events 
		] );
	}
	private function _query_user_join_events() {
		$user_id = $this->input->get ( 'user_id' );
		$offset = $this->input->get ( 'offset' ) ? $this->input->get ( 'offset' ) : 0;
		$limit = $this->input->get ( 'limit' ) ? $this->input->get ( 'limit' ) : 10;
		$user = $this->user_model->get_weee_user_by_global_id ( $user_id );
		if (! $user) {
			return output_json_result ( false, 'Invalid user ID' );
		}
		$join_events_with_details = $this->event_model->query_join_events_and_deals ( $user_id, $limit, $offset );
		$count = $this->event_model->count_join_events_and_deals ( $user_id );
		$events = [ ];
		foreach ( $join_events_with_details as $event ) {
			$event ['rec_create_time'] = strtotime ( $event ['rec_create_time'] );
			$event ['closed'] = ($event ['status'] == SOLITAIRE_CLOSED_STATUS || $event ['end_time'] < time ());
			$events [] = $event;
		}
		return output_json_result ( true, null, [ 
				'count' => $count,
				'offset' => $offset,
				'limit' => $limit,
				'event' => $events 
		] );
	}
	private function _query_event_signups($event_id) {
		log_message ( 'debug', print_r ( $this->input->get (), true ) );
		$offset = $this->input->get ( 'offset' ) ? $this->input->get ( 'offset' ) : 0;
		$limit = $this->input->get ( 'limit' ) ? $this->input->get ( 'limit' ) : 10;
		$where_input = $this->input->get ( 'where' );
		$event = $this->event_model->get_event ( $event_id );
		$user_id = $this->input->get ( 'userid' ) ? $this->input->get ( 'userid' ) : null;
		$user_is_creator = false;
		if ($user_id && $user_id == $event ['rec_creator_id']) {
			$user_is_creator = true;
		}
		if (! $event) {
			return output_json_result ( false, 'Invalid event ID' );
		}
		$where ['event_id'] = $event_id;
		if ($where_input) {
			$where [$where_input] = NULL;
		}
		$orderby = 'event_sign_up.id desc';
		$event_sign_up_rows = $this->event_model->get_event_sign_up_records ( $event_id, $where, $limit, $offset, $orderby );
		$count = $this->event_model->count_event_sign_up_records ( $event_id, $where );
		$event_sign_ups = [ ];
		foreach ( $event_sign_up_rows as $event_sign_up_row ) {
			$event_sign_up = array_intersect_key ( $event_sign_up_row, array_flip ( array (
					'id',
					'key',
					'sign_up_num',
					'user_id',
					'alias',
					'headImgUrl',
					'comment',
					'sign_up_time',
					'event_email',
					'phone',
					'name' 
			) ) );
			$event_sign_up ['headImgUrl'] = get_head_image_url ( $event_sign_up ['headImgUrl'], 'large' );
			if ($user_is_creator) {
				$event_sign_up ['comment_desc'] = $this->_creat_event_signup_desc ( $event_sign_up, $event );
			} else {
				$event_sign_up ['comment_desc'] = '备注:' . $event_sign_up ['comment'];
			}
			$event_sign_ups [] = $event_sign_up;
		}
		return output_json_result ( true, null, [ 
				'count' => $count,
				'offset' => $offset,
				'limit' => $limit,
				'where' => $where_input,
				'event_sign_ups' => $event_sign_ups 
		] );
	}
	private function _creat_event_signup_desc($event_sign_up, $event) {
		$lines = [ ];
		if ($event_sign_up ['name'] != "") {
			$lines [] = '真实姓名:' . $event_sign_up ['name'];
		}
		if ($event_sign_up ['phone'] != "") {
			$lines [] = '电话:' . $event_sign_up ['phone'];
		}
		if ($event_sign_up ['event_email'] != "") {
			$lines [] = '邮箱:' . $event_sign_up ['event_email'];
		}
		if ($event_sign_up ['comment'] != "") {
			$lines [] = '备注:' . $event_sign_up ['comment'];
		}
		return implode ( "\n", $lines );
	}
	private function _get_description_text($event_description) {
		$description_array = explode ( "\n----weee----\n", $event_description );
		return $description_array [0];
	}
	private function _simple_get_event($event_id) {
		$event = $this->event_model->get_event ( $event_id );
		if (! $event || $event ['publish'] === 'X') {
			return output_json_result ( false, '该接龙不存在' );
		}
		$description = $event ['description'];
		$description_array = explode ( "\n----weee----\n", $description );
		$event ['description'] = $description_array [0];
		$image_urls = [ ];
		$thumbnail_urls = [ ];
		if (count ( $description_array ) > 1) {
			$image_urls = array_filter ( array_map ( 'trim', explode ( "\n", $description_array [1] ) ) );
			$thumbnail_urls = array_map ( 'get_image_square_thumbnail_image', $image_urls );
		}
		$event ['image_urls'] = $image_urls;
		$event ['thumbnail_urls'] = $thumbnail_urls;
		$event ['closed'] = $event ['status'] == SOLITAIRE_CLOSED_STATUS || $event ['end_time'] < time ();
		
		$additionalOptions = [ ];
		$additionalOptions ['maxQuantity'] = $event ['quantity'] == NO_LIMITED_QUANTITY ? '' : $event ['quantity'];
		$additionalOptions ['end_time'] = $event ['end_time'] == MAX_END_TIME ? '' : $event ['end_time'];
		if ($event ['extra_options'] && $event_option = json_decode ( $event ['extra_options'], true )) {
			$selected_fields = null;
			foreach ( $event_option as $key => $value ) {
				if ($value ['required']) {
					$selected_fields [] = $value ['key'];
				}
			}
			$additionalOptions ['primary_fields'] = $selected_fields ? $selected_fields : '';
		}
		$event ['additionalOptions'] = $additionalOptions;
		$event_creator = $this->user_model->get_weee_user_by_global_id ( $event ['rec_creator_id'] );
		$event ['rec_creator_alias'] = $event_creator ['alias'];
		$event ['rec_creator_img_url'] = get_head_image_url ( $event_creator ['headImgUrl'], 'large' );
		return output_json_result ( true, null, $event );
	}
	private function _update_event($event_id = null) {
		if (! $event_id) {
			$event_id = $this->input->post ( 'event_id' );
		}
		$user_id = $this->input->post ( 'user_id' );
		$user = $this->user_model->get_weee_user_by_global_id ( $user_id );
		if (! $user) {
			return output_json_result ( false, 'Invalid user ID' );
		}
		
		$event_data ['description'] = $this->input->post ( 'description' );
		// ---- update additional info --
		$array = explode ( ',', $this->input->post ( 'primary_fields' ) );
		$extra_options = $this->_get_primary_field_settings ( $array );
		// ---- add auto_close option
		$event_data ['end_time'] = $this->input->post ( 'end_time' ) ? $this->input->post ( 'end_time' ) : MAX_END_TIME;
		$event_data ['quantity'] = $this->input->post ( 'quantity' ) ? $this->input->post ( 'quantity' ) : NO_LIMITED_QUANTITY;
		$event_data ['extra_options'] = json_encode ( $extra_options, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		
		$description = $this->input->post ( 'description' );
		$desc_image_urls = array ();
		for($i = 0; $i < 3; $i ++) {
			$desc_image_url = $this->input->post ( 'desc_image_' . $i );
			if ($desc_image_url) {
				$desc_image_urls [] = $desc_image_url;
			}
		}
		if ($desc_image_urls) {
			$description .= "\n----weee----\n";
			$description .= implode ( "\n", $desc_image_urls );
		}
		$event_data ['description'] = $description;
		
		if ($event_id) {
			$event = $this->event_model->get_event ( $event_id );
			if ($user_id != $event ['rec_creator_id']) {
				return output_json_result ( false, '你没有权限操作' );
			}
			$event_data ['rec_update_time'] = date ( 'Y-m-d H:i:s' );
			
			$event_signup_counts = $this->event_model->count_event_sign_up_records ( $event_id );
			if ($event_data ['quantity'] > 0 && $event_signup_counts >= $event_data ['quantity']) {
				$event_data ['status'] = SOLITAIRE_CLOSED_STATUS;
			}
			$result = $this->event_model->update_event ( $event_id, $event_data );
		} else {
			$event_data = $this->_set_event_default_values ( $event_data, $user_id );
			$result = $this->event_model->insert_event ( $event_data );
			$event_id = $this->db->insert_id ();
			$this->_send_create_event_xw_template_message ( $user, $event_id, $description );
		}
		if ($result) {
			return $this->_simple_get_event ( $event_id );
		} else {
			return output_json_result ( false, lang ( 'error_db' ) );
		}
	}
	private function _set_event_default_values($event_data, $user_id) {
		$event_data ['key'] = $this->_generate_event_key ();
		$event_data ['publish'] = 'N';
		$event_data ['category'] = META_CODE_EVENT_CATEGORY_SOLITAIRE;
		$event_data ['title'] = '接龙报名_' . time ();
		$event_data ['start_time'] = time ();
		$event_data ['rec_creator_id'] = $user_id;
		return $event_data;
	}
	private function _get_primary_field_settings($input) {
		return [ 
				"name" => [ 
						"name" => "真实姓名",
						"key" => 'name',
						"required" => in_array ( 'name', $input ) 
				],
				"phone" => [ 
						"name" => "电话",
						"key" => 'phone',
						"required" => in_array ( 'phone', $input ) 
				],
				"email" => [ 
						"name" => "邮箱",
						"key" => 'email',
						"required" => in_array ( 'email', $input ) 
				] 
		];
	}
	private function _mark_event_end($event_id = null) {
		$event = $this->event_model->get_event ( $event_id );
		if (! $event) {
			return output_json_result ( false, 'Invalid event ID' );
		}
		$open = $this->input->post ( 'open_flag' );
		
		if ($open && $event ['end_time'] < time ()) {
			return output_json_result ( false, lang ( 'de_end_time_warming' ) );
		}
		$event_data ['status'] = $open ? SOLITAIRE_OPENED_STATUS : SOLITAIRE_CLOSED_STATUS;
		$result = $this->event_model->update_event ( $event_id, $event_data );
		if ($result) {
			return $this->_simple_get_event ( $event_id );
		} else {
			return output_json_result ( false, lang ( 'error_db' ) );
		}
	}
	private function _copy_event($event_id) {
		$user_id = $this->input->post ( 'user_id' );
		$user = $this->user_model->get_weee_user_by_global_id ( $user_id );
		if (! $user) {
			return output_json_result ( false, 'Invalid user ID' );
		}
		$event = $this->event_model->get_event ( $event_id );
		if ($user_id != $event ['rec_creator_id']) {
			return output_json_result ( false, '你没有权限操作' );
		}
		
		$event_data ['description'] = '【复制】' . $event ['description'];
		$event_data = $this->_set_event_default_values ( $event_data, $user_id );
		
		// ---- copy auto_close option
		$event_data ['quantity'] = $event ['quantity'];
		$event_data ['extra_options'] = $event ['extra_options'];
		$event_data ['end_time'] = MAX_END_TIME;
		$result = $event_id = $this->event_model->insert_event ( $event_data );
		
		if ($result) {
			$this->_send_create_event_xw_template_message ( $user, $event_id, $event_data ['description'] );
			return $this->_simple_get_event ( $event_id );
		} else {
			return output_json_result ( false, lang ( 'error_db' ) );
		}
	}
	private function _delete_event($event_id) {
		$user_id = $this->input->post ( 'user_id' );
		$user = $this->user_model->get_weee_user_by_global_id ( $user_id );
		if (! $user) {
			return output_json_result ( false, 'Invalid user ID' );
		}
		$event = $this->event_model->get_event ( $event_id );
		if ($user_id != $event ['rec_creator_id']) {
			return output_json_result ( false, '你没有权限操作' );
		}
		
		$count = $this->event_model->count_event_sign_up_records ( $event_id );
		if ($count > 0) {
			return output_json_result ( false, '已经有人报名，不能删除' );
		}
		
		$result = $this->event_model->update_event ( $event_id, [ 
				'publish' => 'X' 
		] );
		if ($result) {
			return output_json_result ( true, '操作成功' );
		} else {
			return output_json_result ( false, lang ( 'error_db' ) );
		}
	}
	private function _sign_up_post($event_id) {
		$user_id = $this->input->post ( 'user_id' );
		$user = $this->user_model->get_weee_user_by_global_id ( $user_id );
		if (! $user) {
			return output_json_result ( false, 'Invalid user ID' );
		}
		$event = $this->event_model->get_event ( $event_id );
		if (! $event) {
			return output_json_result ( false, 'Invalid event ID' );
		}
		
		// Check event status
		$now = time ();
		if ($event ['end_time'] < $now) {
			return output_json_result ( false, lang ( 'event_sign_up_closed_error' ) );
		}
		$user_signup_row = $this->event_model->user_signed_up ( $event_id, $user_id );
		if (! $user_signup_row) {
			$current_signed_up_count = $this->event_model->count_event_sign_up_records ( $event_id );
			if ($event ['quantity'] != NO_LIMITED_QUANTITY && $current_signed_up_count >= $event ['quantity']) {
				return output_json_result ( false, lang ( 'event_sign_up_full_error' ) );
			}
		}
		log_message ( 'debug', '$this->input->post() = ' . print_r ( $this->input->post (), true ) );
		
		$update_data ['event_id'] = $event_id;
		$update_data ['user_id'] = $user_id;
		$update_data ['comment'] = $this->input->post ( 'comment' );
		$update_data ['name'] = $this->input->post ( 'name' ) ? $this->input->post ( 'name' ) : '';
		$update_data ['phone'] = $this->input->post ( 'phone' ) ? $this->input->post ( 'phone' ) : '';
		$update_data ['email'] = $this->input->post ( 'email' ) ? $this->input->post ( 'email' ) : '';
		
		$this->db->trans_start ();
		
		$result = true;
		if ($user_signup_row) {
			// copy the old row into the history table
			$result = $this->event_model->insert_event_sign_up_history ( $user_signup_row );
			if ($result) {
				$post_data ['version'] = $user_signup_row ['version'] + 1;
				$result = $this->event_model->update_event_sign_up ( $user_signup_row ['id'], $update_data );
			}
		} else {
			$update_data ['sign_up_time'] = time ();
			$result = $this->event_model->insert_event_sign_up ( $update_data );
			if ($result) {
				$result = $this->event_model->update_event_sign_up_nums ( $event_id );
			}
			if ($result) {
				$event_signup_counts = $this->event_model->count_event_sign_up_records ( $event_id );
				if ($event ['quantity'] != NO_LIMITED_QUANTITY && $event_signup_counts >= $event ['quantity']) {
					$data ['status'] = SOLITAIRE_CLOSED_STATUS;
					$result = $this->event_model->update_event ( $event_id, $data );
				}
			}
		}
		$this->db->trans_complete ();
		
		if ($result) {
			$this->_send_sign_up_success_wx_template_message ( $event, $update_data ['comment'], $user );
		}
		if ($result) {
			return output_json_result ( true, null );
		} else {
			return output_json_result ( false, lang ( 'error_db' ) );
		}
	}
	private function _email() {
		$event_id = $this->input->post ( 'event_id' );
		if ($event_id) {
			return $this->_email_signup_log ( $event_id );
		}
		$deal_id = $this->input->post ( 'deal_id' );
		if ($deal_id) {
			return $this->_email_deal_order ( $deal_id );
		}
		return output_json_result ( false, 'Invalid parameters' );
	}
	private function _email_signup_log($event_id) {
		$event = $this->event_model->get_event ( $event_id );
		if (! $event) {
			return output_json_result ( false, 'Invalid event ID' );
		}
		
		$email = $this->input->post ( 'email' );
		if (! $this->_valid_email ( $email )) {
			return output_json_result ( false, '请输入正确的电子邮箱地址' );
		}
		
		$sign_up_rows = $this->event_model->get_event_sign_up_records ( $event_id );
		if (count ( $sign_up_rows ) == 0) {
			return output_json_result ( false, '现在暂时还没有报名记录' );
		}
		
		$event ['description'] = $this->_get_description_text ( $event ['description'] );
		$data ['event'] = $event;
		$data ['sign_up_rows'] = $sign_up_rows;
		
		$body = $this->load->view ( 'solitaire/email_sign_up', $data, true );
		
		$this->load->helper ( 'myemail' );
		send_email ( null, $email, '一键接龙报名记录 - Weee!', $body );
		
		$user_id = $event ['rec_creator_id'];
		insert_activity_log ( $event_id, 'solitaire_email_event', $user_id, $email );
		
		return output_json_result ( true );
	}
	private function _email_deal_order($deal_id) {
		$this->load->model ( 'groupbuy_model' );
		$deal = $this->groupbuy_model->get_deal ( $deal_id );
		if (! $deal) {
			return output_json_result ( false, 'Invalid deal ID' );
		}
		$deal ['currency_symbol'] = get_meta_code_value_by_key ( META_TYPE_DEAL_CURRENCY_SYMBOL, $deal ['currency'] );
		$email = $this->input->post ( 'email' );
		if (! $this->_valid_email ( $email )) {
			return output_json_result ( false, '请输入正确的电子邮箱地址' );
		}
		
		$where ['gb_order.deal_id'] = $deal_id;
		$where ['gb_order.status in (\'' . META_CODE_DEAL_ORDER_STATUS_CONFIRMED . '\',\'' . META_CODE_DEAL_ORDER_STATUS_FINISHED . '\')'] = NULL;
		$order_rows = $this->groupbuy_model->query_orders_with_products_desc ( $where, 'id' );
		if (count ( $order_rows ) == 0) {
			return output_json_result ( false, '现在暂时还没有报名记录' );
		}
		$deal_order_products = $this->groupbuy_model->query_deal_products_with_resource ( $deal_id );
		
		foreach ( $deal_order_products as &$product ) {
			$product ['amount'] = $product ['price'] * $product ['product_sold_count'];
		}
		$data ['total_quantity'] = array_sum ( array_column ( $deal_order_products, 'product_sold_count' ) );
		$data ['total_amount'] = array_sum ( array_column ( $deal_order_products, 'amount' ) );
		$data ['deal_order_products'] = $deal_order_products;
		
		$deal ['description'] = $this->_get_description_text ( $deal ['description'] );
		$data ['deal'] = $deal;
		$data ['order_rows'] = $order_rows;
		
		$body = $this->load->view ( 'solitaire/email_order', $data, true );
		$this->load->helper ( 'myemail' );
		send_email ( null, $email, '一键接龙报名记录 - Weee!', $body );
		
		$user_id = $deal ['rec_creator_id'];
		insert_activity_log ( $deal_id, 'solitaire_email_deal', $user_id, $email );
		
		return output_json_result ( true );
	}
	private function _valid_email($str) {
		return (! preg_match ( "/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix", $str )) ? FALSE : TRUE;
	}
	private function _send_create_event_xw_template_message($user, $event_id, $description) {
		$wx_app_id = $this->config->config [WX_XCH_API] ['appid'];
		$user_weixin_relation = $this->user_model->get_user_weixin_relation_where ( [ 
				'wx_app_id' => $wx_app_id,
				'user_id' => $user ['Global_User_ID'] 
		] );
		if (! $user_weixin_relation) {
			log_message ( 'error', '_send_create_event_xw_template_message: Can not get the user weixin open id' );
			return false;
		}
		
		$weixin_open_id = $user_weixin_relation ['openid'];
		$template_id = WEEE_WX_TEMPLATE_ID_XCH_EVENT_CREATE;
		$page = substr ( $this->input->post ( 'page' ), 1 ) . 'event_id=' . $event_id;
		$form_id = $this->input->post ( 'form_id' );
		$description = $this->_get_description_text ( $description );
		if (mb_strlen ( $description ) > 50) {
			$description = mb_substr ( $description, 0, 50 ) . '...';
		}
		$parameters = build_wx_template_parameters ( [ 
				'keyword1' => '创建接龙成功',
				'keyword2' => $description 
		], '#000000' );
		return send_wx_xch_template_message ( $weixin_open_id, $template_id, $page, $form_id, $parameters );
	}
	private function _send_sign_up_success_wx_template_message($event, $comment, $user) {
		$wx_app_id = $this->config->config [WX_XCH_API] ['appid'];
		$user_weixin_relation = $this->user_model->get_user_weixin_relation_where ( [ 
				'wx_app_id' => $wx_app_id,
				'user_id' => $user ['Global_User_ID'] 
		] );
		if (! $user_weixin_relation) {
			log_message ( 'error', "_send_sign_up_success_wx_template_message: wx_app_id = {$wx_app_id} , user_id = {$user ['Global_User_ID']}" );
			return false;
		}
		$weixin_open_id = $user_weixin_relation ['openid'];
		$template_id = WEEE_WX_TEMPLATE_ID_XCH_SIGNUP_SUCCESS;
		$page = substr ( $this->input->post ( 'page' ), 1 );
		$form_id = $this->input->post ( 'form_id' );
		$time_zone = $this->input->post ( 'time_zone' );
		$time = date ( 'Y-m-d h:ia', time () + 3600 * $time_zone );
		$description = $this->_get_description_text ( $event ['description'] );
		if (mb_strlen ( $description ) > 50) {
			$description = mb_substr ( $description, 0, 50 ) . '...';
		}
		$parameters = build_wx_template_parameters ( [ 
				'keyword1' => $description,
				'keyword2' => $user ['alias'],
				'keyword3' => $comment,
				'keyword4' => $time 
		], '#000000' );
		return send_wx_xch_template_message ( $weixin_open_id, $template_id, $page, $form_id, $parameters );
	}
	private function _generate_event_key() {
		for($i = 0; $i < 10; $i ++) {
			$event_key = generate_rand_string ( 5 );
			if (! is_numeric ( $event_key ) && ! $this->event_model->get_event_by_key ( $event_key )) {
				return $event_key;
			}
		}
	}
	private function _help_settings() {
		$help_settings = [ 
				[ 
						'url' => 'https://www.sayweee.com/static_img/2017-04/te-_zXEPTTqJK1JtdB_Txw.jpg',
						'style' => 'width:750rpx;height:1125rpx;' 
				],
				[ 
						'url' => 'https://www.sayweee.com/static_img/2017-04/gaZzvOsTRXiw2JxxMXF8tw.jpg',
						'style' => 'width:750rpx;height:1125rpx;' 
				],
				[ 
						'url' => 'https://www.sayweee.com/static_img/2017-04/M55AytBRQjOGYbC_qwdUkw.jpg',
						'style' => 'width:750rpx;height:1125rpx;' 
				],
				[ 
						'url' => 'https://www.sayweee.com/static_img/2017-04/cYtEvZEdTkK0xBJ_ZX9cvA.jpg',
						'style' => 'width:750rpx;height:1125rpx;' 
				],
				[ 
						'url' => 'https://www.sayweee.com/static_img/2017-04/WVyNfOYUReS11J4QK88g7g.jpg',
						'style' => 'width:750rpx;height:1125rpx;' 
				],
				[ 
						'url' => 'https://www.sayweee.com/static_img/2017-04/L8DiujrQTi2Ea66r2mx__g.jpg',
						'style' => 'width:750rpx;height:1125rpx;' 
				] 
		];
		return output_json_result ( true, null, $help_settings );
	}
	function api_get_sess_key_by_code() {
		return output_json_result ( false, '临时关闭接口' );
		$code = $this->input->get ( 'code' );
		$result = $user = $this->_get_user_by_code ( $code, $error, $wx_open_id );
		if ($result) {
			return output_json_result ( true, NULL, [ 
					'sess_key' => $user ['Global_User_ID'],
					'open_id' => $wx_open_id 
			] );
		} else {
			return output_json_result ( false, $error );
		}
	}
	private function _get_user_by_code($code, &$error, &$wx_open_id, &$session_key = NULL) {
		$appid = $this->config->config [WX_XCH_API] ['appid'];
		$appsecret = $this->config->config [WX_XCH_API] ['appsecret'];
		// success: {"session_key":"vOzq8kL6nK\/LSBiSF6FdLw==","expires_in":2592000,"openid":"o7SLq0NkVPY5L48PG7qraN5ydY5U"}
		// fail: {"errcode":40029,"errmsg":"invalid code, hints: [ req_id: t3eEBA0682ns80 ]"}
		$url = "https://api.weixin.qq.com/sns/jscode2session?appid={$appid}&secret={$appsecret}&js_code={$code}&grant_type=authorization_code";
		$result = call_wx_api ( $url );
		if (! $result) {
			$wx_open_id = NULL;
			$error = 'Failed to query user information from the WeChat server.';
			return FALSE;
		}
		
		$wx_open_id = $result ['openid'];
		$session_key = $result ['session_key'];
		
		$user_weixin_relation = $this->user_model->get_user_weixin_relation ( $appid, $wx_open_id );
		if ($user_weixin_relation && $user_weixin_relation ['user_id']) {
			$user = $this->user_model->get_weee_user_by_global_id ( $user_weixin_relation ['user_id'] );
			if ($user) {
				return $user;
			}
		}
		
		$error = 'New user';
		return FALSE;
	}
	function api_wx_xcx_get_sess_key() {
		$code = $this->input->post ( 'code' );
		
		$result = $user = $this->_get_user_by_code ( $code, $error, $wx_open_id, $session_key );
		if ($result) {
			return output_json_result ( true, NULL, [ 
					'sess_key' => $user ['Global_User_ID'],
					'open_id' => $wx_open_id 
			] );
		} else if (! $wx_open_id) {
			return output_json_result ( false, $error );
		}
		
		$appid = $this->config->config [WX_XCH_API] ['appid'];
		$iv = $this->input->post ( 'iv' );
		$encrypted_data = $this->input->post ( 'encryptedData' );
		$rawData = $this->input->post ( 'rawData' );
		$signature = $this->input->post ( 'signature' );
		
		$signature2 = sha1 ( $rawData . $session_key );
		
		// data transport err
		if ($signature != $signature2) {
			return output_json_result ( false, 'trans_err' );
		}
		
		$mingwen = $this->aes_decode ( $session_key, $iv, $encrypted_data );
		$data = json_decode ( $mingwen, true );
		if (! $data) {
			return output_json_result ( false, 'Failed to decode' );
		}
		if (! isset ( $data ['unionId'] ) || ! $data ['unionId'] || ! isset ( $data ['openId'] ) || ! $data ['openId']) {
			return output_json_result ( false, 'Error data' );
		}
		
		$wx_open_id = $data ['openId'];
		$wx_union_id = $data ['unionId'];
		$alias = isset ( $data ['nickName'] ) ? $data ['nickName'] : '';
		$head_img_url = isset ( $data ['avatarUrl'] ) ? $data ['avatarUrl'] : '';
		
		$user = $this->user_model->get_weee_user ( array (
				'wxUnionId' => $wx_union_id 
		) );
		
		if ($user) {
			$user_id = $user ['Global_User_ID'];
			$user_weixin_relation = $this->user_model->get_user_weixin_relation ( $appid, $wx_open_id );
			if (! $user_weixin_relation) {
				$this->user_model->insert_user_weixin_relation ( [ 
						'user_id' => $user_id,
						'wx_app_id' => $appid,
						'openid' => $wx_open_id,
						'unionid' => $wx_union_id,
						'nickname' => $alias,
						'headimgurl' => $head_img_url 
				] );
			}
		} else {
			$uuid = gen_uuid ();
			$user_id = $this->user_model->create_weee_user_from_weixin_sns ( $uuid, $alias, $wx_open_id, $wx_union_id, $head_img_url, null, $appid );
			if (! $user_id) {
				return output_json_result ( false, 'Failed to create user.' );
			}
		}
		
		$resData ['sess_key'] = $user_id;
		$resData ['open_id'] = $wx_open_id;
		return output_json_result ( true, NULL, $resData );
	}
	// applet aes decode
	private function aes_decode($sessionKey, $iv, $encryptedData) {
		$aesKey = base64_decode ( $sessionKey );
		$aesIV = base64_decode ( $iv );
		$aesCipher = base64_decode ( $encryptedData );
		
		$module = mcrypt_module_open ( MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '' );
		mcrypt_generic_init ( $module, $aesKey, $aesIV );
		
		$decrypted = mdecrypt_generic ( $module, $aesCipher );
		mcrypt_generic_deinit ( $module );
		mcrypt_module_close ( $module );
		
		$pad = ord ( substr ( $decrypted, - 1 ) );
		if ($pad < 1 || $pad > 32) {
			$pad = 0;
		}
		
		return substr ( $decrypted, 0, (strlen ( $decrypted ) - $pad) );
	}
	private function _share() {
		$event_id = $this->input->get ( 'event_id' );
		if ($event_id) {
			$path = "pages/share/share?event_id={$event_id}";
			$event = $this->event_model->get_event ( $event_id );
			return $this->_share_image ( $event ['rec_creator_id'], $path );
		}
		$deal_id = $this->input->get ( 'deal_id' );
		if ($deal_id) {
			$this->load->model ( 'groupbuy_model' );
			$deal = $this->groupbuy_model->get_deal ( $deal_id );
			$path = "pages/gb/view?deal_id={$deal_id}";
			return $this->_share_image ( $deal ['rec_creator_id'], $path );
		}
		return output_json_result ( false, 'Invalid parameters' );
	}
	private function _share_image($user_id, $path) {
		$base_url = $_SERVER ['REQUEST_SCHEME'] . '://' . $_SERVER ['SERVER_NAME'];
		$url = $base_url . "/xch/api_xch_qrcode?type=xch_solitaire&path=" . urlencode ( $path );
		$qrcode = curl_synchronous_call ( $url, [ ] );
		$result = json_decode ( $qrcode, true );
		if (! $result || ! isset ( $result ['result'] )) {
			return output_json_result ( 'false', isset ( $result ['message'] ) ? $result ['message'] : 'Get Qrcode failed' );
		}
		$qrcode = $result ['object'];
		$share_image_path = PATH_EVENT_IMAGE_SHARE . $qrcode ['id'] . '.jpg';
		$url = $base_url . URL_EVENT_IMAGE_SHARE_BASE . $qrcode ['id'] . '.jpg';
		
		$header = get_headers ( $url, 1 );
		if (preg_grep ( "/200/", $header )) {
			return output_json_result ( true, '', [ 
					"path" => $share_image_path,
					"share_url" => $url 
			] );
		}
		// ----get & save header image by type
		$user = $this->user_model->get_weee_user_by_global_id ( $user_id );
		$user_image_path = PATH_EVENT_IMAGE_USER . $qrcode ['id'] . '.jpg';
		if (empty ( $user ['headImgUrl'] )) {
			$user ['headImgUrl'] = site_url ( 'css/img/avatar_unknown.png' );
		}
		if (! ($img = file_get_contents ( $user ['headImgUrl'] ))) {
			return output_json_result ( 'false', 'Download user image failed' );
		}
		
		$result = file_put_contents ( $user_image_path, $img );
		if (! $result) {
			return output_json_result ( 'false', 'Save user image failed' );
		}
		
		$canvas = $this->_draw_image ( $qrcode ['path'], $user_image_path, $user ['alias'] );
		$result = $canvas->writeImage ( $share_image_path );
		if (! $result) {
			return output_json_result ( false, "Imagick save image failed", null );
		}
		
		// ---- update question table
		
		return output_json_result ( true, '', [ 
				"path" => $share_image_path,
				"share_url" => $url 
		] );
	}
	private function _draw_image($xch_qrcode_path, $user_image_path, $user_name) {
		// ---- weee_restaurant background image
		$bg_url = BASEPATH . join ( DIRECTORY_SEPARATOR, array (
				'..',
				'css',
				'img',
				'share-image-bg.png' 
		) );
		$canvas = new Imagick ( $bg_url );
		
		// ---- xch qrcode
		$qrcode_image = new Imagick ();
		$qrcode_image->newImage ( 132, 132, new ImagickPixel ( 'white' ) );
		$qrcode_image->setImageFormat ( 'png' );
		$qrcode = new Imagick ( $xch_qrcode_path );
		$qrcode->thumbnailImage ( 120, 120, false );
		
		$qrcode_image->compositeImage ( $qrcode, Imagick::COMPOSITE_OVER, 6, 6 );
		$qrcode_image->roundCorners ( 66, 66 );
		
		// ---- user header image
		$user_img = new Imagick ( $user_image_path );
		$user_img->thumbnailImage ( 132, 132, false );
		$user_img->roundCorners ( 66, 66 );
		
		$draw = new ImagickDraw ();
		$font = "fonts/msyh.ttc";
		$draw->setGravity ( Imagick::GRAVITY_CENTER );
		$draw->setFont ( $font );
		$draw->setFontSize ( 23 );
		$draw->setFillColor ( new ImagickPixel ( "#000000" ) );
		$draw->annotation ( 0, 15, $user_name . "发起了一条接龙" );
		$draw->annotation ( 0, 51, "长按二维码识别立即参与" );
		
		$draw->composite ( Imagick::COMPOSITE_OVER, 0, 165, 132, 132, $qrcode_image );
		$draw->composite ( Imagick::COMPOSITE_OVER, 0, - 100, 132, 132, $user_img );
		$canvas->drawimage ( $draw );
		return $canvas;
	}
}
