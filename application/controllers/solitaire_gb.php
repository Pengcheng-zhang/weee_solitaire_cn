<?php
defined('BASEPATH') OR exit('No direct script access allowed');
define ( 'MAX_END_TIME', 2145888000 );
/**
 *
 * @property Groupbuy_model $groupbuy_model
 */
class Solitaire_Gb extends CI_Controller {
	function __construct() {
		parent::__construct ();
		$this->load->model ( 'groupbuy_model' );
	}
	function api($path1 = null, $path2 = null, $path3 = null) {
		log_message ( 'debug', 'REQUEST_URI = ' . $_SERVER ['REQUEST_URI'] );
		log_message ( 'debug', '$this->input->post() = ' . print_r ( $this->input->post (), true ) );
		if ($path1 === 'deals' && $path2) {
			$deal_id = $path2;
			if ($deal_id === 'post') {
				return $this->_update_deal ();
			}
			
			$action = $path3;
			if (! $action) {
				return $this->_get_deal ( $deal_id );
			}
			switch ($action) {
				case 'update' :
					return $this->_update_deal ( $deal_id );
				case 'markend' :
					return $this->_mark_end ( $deal_id );
				case 'orders' :
					return $this->_query_orders ( $deal_id );
				case 'post_order' :
					return $this->_post_order ( $deal_id );
				case 'copy' :
					return $this->_copy_deal ( $deal_id );
				case 'delete' :
					return $this->_delete_deal ( $deal_id );
			}
		}
		return output_json_result ( false, 'Not implemented' );
	}
	private function _query_orders($deal_id) {
		$limit = 5;
		$where_input = $this->input->get ( 'where' );
		$deal = $this->groupbuy_model->get_deal ( $deal_id );
		if (! $deal) {
			return output_json_result ( false, 'Invalid deal ID' );
		}
		
		$user_id = $this->input->get ( 'userid' ) ? $this->input->get ( 'userid' ) : null;
		$user_is_creator = false;
		if ($user_id && $user_id == $deal ['rec_creator_id']) {
			$user_is_creator = true;
		}
		
		$where ['gb_order.deal_id'] = $deal_id;
		$where ['gb_order.status in (\'' . META_CODE_DEAL_ORDER_STATUS_CONFIRMED . '\',\'' . META_CODE_DEAL_ORDER_STATUS_FINISHED . '\')'] = NULL;
		$where_input = $this->input->get ( 'where' );
		if ($where_input) {
			$where [$where_input] = NULL;
			$show_product_details = true;
		}
		$orderby = 'gb_order.id desc';
		$order_rows = $this->groupbuy_model->query_orders_with_products_desc ( $where, $orderby, $limit );
		$count = $this->groupbuy_model->count_orders ( $where );
		
		$primary_fields = '';
		if ($deal ['deal_settings'] && $deal_settings = json_decode ( $deal ['deal_settings'], true )) {
			if (key_exists ( 'primary_fields', $deal_settings )) {
				$primary_fields = $deal_settings ['primary_fields'];
			}
		}
		
		$orders = [ ];
		foreach ( $order_rows as $order_row ) {
			$order_row ['products_desc'] = $this->_build_order_description ( $order_row ['products_desc'], $order_row, $user_is_creator );
			$order_row = array_intersect_key ( $order_row, array_flip ( array (
					'id',
					'seq_num',
					'rec_creator_id',
					'alias',
					'headImgUrl',
					'rec_create_time',
					'products_desc',
					'addr_lastname',
					'email',
					'phone' 
			) ) );
			$order_row ['headImgUrl'] = get_head_image_url ( $order_row ['headImgUrl'], 'large' );
			$order_row ['rec_create_timestamp'] = strtotime ( $order_row ['rec_create_time'] );
			
			$orders [] = $order_row;
		}
		return output_json_result ( true, null, [ 
				'count' => $count,
				'limit' => $limit,
				'where' => $where_input,
				'orders' => $orders 
		] );
	}
	private function _build_order_description($order_products_desc, $order, $user_is_creator) {
		$lines = [ ];
		if ($user_is_creator) {
			if ($order ['addr_lastname']) {
				$lines [] = '真实姓名:' . $order ['addr_lastname'];
			}
			if ($order ['phone']) {
				$lines [] = '电话:' . $order ['phone'];
			}
			if ($order ['email']) {
				$lines [] = '邮箱:' . $order ['email'];
			}
		}
		
		if ($order ['comment']) {
			$lines [] = '备注:' . $order ['comment'];
		}
		$order_comment = implode ( "\n", $lines );
		if ($order_products_desc) {
			$order_description = $order_products_desc;
			if ($order_comment) {
				$order_description .= "\n{$order_comment}";
			}
			return $order_description;
		} else {
			if ($order_comment) {
				return $order_comment;
			} else {
				return '取消了订单';
			}
		}
	}
	private function _get_description_text($event_description) {
		$description_array = explode ( "\n----weee----\n", $event_description );
		return $description_array [0];
	}
	private function _get_deal($deal_id) {
		$deal = $this->groupbuy_model->get_deal ( $deal_id );
		if (! $deal || $deal ['status'] == META_CODE_DEAL_STATUS_DELETED) {
			return output_json_result ( false, '该接龙不存在' );
		}
		$description = $deal ['description'];
		$description_array = explode ( "\n----weee----\n", $description );
		$deal ['description'] = $description_array [0];
		$image_urls = [ ];
		$thumbnail_urls = [ ];
		if (count ( $description_array ) > 1) {
			$image_urls = array_filter ( array_map ( 'trim', explode ( "\n", $description_array [1] ) ) );
			$thumbnail_urls = array_map ( 'get_image_square_thumbnail_image', $image_urls );
		}
		$deal ['image_urls'] = $image_urls;
		$deal ['thumbnail_urls'] = $thumbnail_urls;
		$deal ['rec_create_timestamp'] = strtotime ( $deal ['rec_create_time'] );
		$deal ['currency_symbol'] = get_meta_code_value_by_key ( META_TYPE_DEAL_CURRENCY_SYMBOL, $deal ['currency'] );
		$deal ['closed'] = ($deal ['status'] == META_CODE_DEAL_STATUS_CLOSED) || $deal ['end_time'] < time ();
		
		$additionalOptions = [ ];
		$additionalOptions ['end_time'] = $deal ['end_time'] == MAX_END_TIME ? '' : $deal ['end_time'];
		$additionalOptions ['primary_fields'] = '';
		if ($deal ['deal_settings'] && $deal_settings = json_decode ( $deal ['deal_settings'], true )) {
			if (key_exists ( 'primary_fields', $deal_settings )) {
				$additionalOptions ['primary_fields'] = $deal_settings ['primary_fields'];
			}
			if (key_exists ( 'order_count_limit', $deal_settings )) {
				$additionalOptions ['maxQuantity'] = $deal ['quantity'] = $deal_settings ['order_count_limit'];
			}
		}
		
		$deal_creator = $this->user_model->get_weee_user_by_global_id ( $deal ['rec_creator_id'] );
		$deal ['rec_creator_alias'] = $deal_creator ['alias'];
		$deal ['rec_creator_img_url'] = get_head_image_url ( $deal_creator ['headImgUrl'], 'large' );
		$products = $this->groupbuy_model->query_deal_products_with_resource ( $deal ['id'] );
		$deal ['products'] = $products;
		
		$order = null;
		$user_id = $this->input->get ( 'user_id' );
		if ($user_id) {
			$order = $this->_get_user_order ( $deal_id, $user_id );
		}
		
		return output_json_result ( true, null, [ 
				'deal' => $deal,
				'order' => $order,
				'additionalOptions' => $additionalOptions 
		] );
	}
	private function _get_user_order($deal_id, $user_id) {
		$where ['gb_order.deal_id'] = $deal_id;
		$where ['gb_order.status in (\'' . META_CODE_DEAL_ORDER_STATUS_CONFIRMED . '\',\'' . META_CODE_DEAL_ORDER_STATUS_FINISHED . '\')'] = NULL;
		$where ['gb_order.rec_creator_id'] = $user_id;
		$orderby = 'gb_order.id desc';
		$order_rows = $this->groupbuy_model->query_orders ( $where, $orderby );
		if ($order_rows) {
			$order = $this->groupbuy_model->get_order ( $order_rows [0] ['id'] );
			$order ['products'] = array_column ( $order ['products'], 'quantity', 'product_id' );
			return $order;
		}
		return null;
	}
	private function _update_deal() {
		$user_id = $this->input->post ( 'user_id' );
		$user = $this->user_model->get_weee_user_by_global_id ( $user_id );
		if (! $user) {
			return output_json_result ( false, 'Invalid user ID' );
		}
		
		$this->load->library ( 'form_validation' );
		$this->form_validation->set_rules ( 'description', '团购信息', 'required|max_length[4000]' );
		$this->form_validation->set_rules ( 'currency', '币种', 'required' );
		
		if (! $this->form_validation->set_error_delimiters ( '', '' )->run ()) {
			return output_json_result ( false, validation_errors () );
		}
		
		$description = $this->input->post ( 'description' );
		$description_html = plain_text_2_html ( $description );
		$desc_image_urls = array ();
		for($i = 0; $i < 3; $i ++) {
			$desc_image_url = $this->input->post ( 'desc_image_' . $i );
			if ($desc_image_url) {
				$desc_image_urls [] = $desc_image_url;
				$description_html .= "<img scr='$desc_image_url'>";
			}
		}
		if ($desc_image_urls) {
			$description .= "\n----weee----\n";
			$description .= implode ( "\n", $desc_image_urls );
		}
		
		$post_data = $this->input->post ();
		$deal_id = $this->input->post ( 'deal_id' );
		$deal_data ['description'] = $description;
		$deal_data ['description_html'] = $description_html;
		$deal_data ['currency'] = $this->input->post ( 'currency' );
		
		// ---- add auto_close option
		$deal_data ['end_time'] = $this->input->post ( 'end_time' ) ? $this->input->post ( 'end_time' ) : MAX_END_TIME;
		$extra_options = null;
		if (trim ( $this->input->post ( 'quantity' ) ) != '') {
			$extra_options ['order_count_limit'] = $deal_quantity = $this->input->post ( 'quantity' );
			
			$where ['gb_order.deal_id'] = $deal_id;
			$deal_order_count = $this->groupbuy_model->count_orders ( $where );
			if ($deal_quantity > 0 && $deal_order_count >= $deal_quantity) {
				$deal_data ['status'] = META_CODE_DEAL_STATUS_CLOSED;
			}
		}
		// ---- update additional info --
		if (trim ( $this->input->post ( 'primary_fields' ) ) != '') {
			$extra_options ['primary_fields'] = explode ( ',', $this->input->post ( 'primary_fields' ) );
		}
		
		if ($extra_options) {
			$deal_data ['deal_settings'] = json_encode ( $extra_options, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		} else {
			$deal_data ['deal_settings'] = null;
		}
		// Products ==================================================
		if (! key_exists ( 'product_id_0', $post_data )) {
			return output_json_result ( false, lang ( 'deal_no_product' ) );
		}
		$old_deal_products = array ();
		if ($deal_id) {
			$old_deal_products = $this->groupbuy_model->query_deal_products_with_resource ( $deal_id );
			$old_deal_products = array_combine ( array_column ( $old_deal_products, 'product_id' ), $old_deal_products );
		}
		// Convert the post deal product data into product array
		$result = $this->_build_new_deal_products ( $post_data, $new_deal_products );
		if (! $result) {
			return;
		}
		// Split the product array into A/D/U array
		$result = $this->_split_deal_products_to_adu ( $new_deal_products, $old_deal_products, $add_products, $delete_products, $update_products );
		if (! $result) {
			return;
		}
		// END Products ==================================================
		
		$this->db->trans_start ();
		
		if ($deal_id) {
			$is_create = false;
			$deal = $this->groupbuy_model->get_deal ( $deal_id );
			if ($user_id != $deal ['rec_creator_id']) {
				return output_json_result ( false, '你没有权限操作' );
			}
			$deal_data ['rec_update_time'] = date ( 'Y-m-d H:i:s' );
			$result = $this->groupbuy_model->update_deal ( $deal_id, $deal_data );
		} else {
			$is_create = true;
			$deal_data = $this->_set_deal_default_values ( $deal_data, $user_id );
			$result = $deal_id = $this->groupbuy_model->insert_deal ( $deal_data );
			if ($result) {
				$deal = $this->groupbuy_model->get_deal ( $deal_id );
			}
		}
		
		// update the deal's products
		if ($result) {
			$deal_saver = & load_class ( 'DealSaver', 'biz/deal', '' );
			$result = $deal_saver->save_deal_products ( $deal, $user, $add_products, $delete_products, $update_products, $old_deal_products, true );
		}
		
		// TODO create gl tag
		
		$this->db->trans_complete ();
		
		if ($result) {
			if ($is_create) {
				// send notification
				$this->_send_create_deal_xw_template_message ( $deal );
			}
			return $this->_get_deal ( $deal_id );
		} else {
			return output_json_result ( false, lang ( 'error_db' ) );
		}
	}
	private function _build_new_deal_products(&$post_data, &$new_deal_products) {
		$new_deal_products = array ();
		$errors = array ();
		foreach ( $post_data as $field_name => $value ) {
			if (! preg_match ( '/product_id_(\d+)/', $field_name, $matches )) {
				continue;
			}
			$idx = $matches [1];
			$deal_product = array ();
			$deal_product ['product_id'] = $value;
			$quantity = $post_data ['product_quantity_' . $idx];
			if (is_numeric ( $quantity ) && $quantity > 0) {
				$deal_product ['quantity'] = $quantity;
			} else {
				$deal_product ['quantity'] = NULL;
			}
			$deal_product ['price'] = round ( floatval ( $post_data ['product_price_' . $idx] ), 2 );
			$deal_product ['pos'] = $idx;
			$deal_product ['product_title'] = $post_data ['product_title_' . $idx];
			$deal_product ['product_image_url'] = '';
			array_push ( $new_deal_products, $deal_product );
			
			$error = array ();
			if (! $deal_product ['product_title']) {
				array_push ( $error, '商品名称未设置' );
			}
			if ($deal_product ['price'] <= 0) {
				array_push ( $error, '商品价格输入错误' );
			}
			if ($error) {
				array_push ( $errors, sprintf ( '团购商品的第%u项: ', $idx + 1 ) . implode ( ', ', $error ) );
			}
		}
		if ($errors) {
			output_json_result ( false, implode ( "\n", $errors ) );
			return FALSE;
		}
		return TRUE;
	}
	private function _split_deal_products_to_adu($new_deal_products, $old_deal_products, &$add_products, &$delete_products, &$update_products) {
		$add_products = array ();
		$delete_products = array ();
		$update_products = array ();
		foreach ( $new_deal_products as $new_deal_product ) {
			if (key_exists ( $new_deal_product ['product_id'], $old_deal_products )) {
				array_push ( $update_products, $new_deal_product );
			} else {
				array_push ( $add_products, $new_deal_product );
			}
		}
		foreach ( $old_deal_products as $old_deal_product ) {
			$deleted = true;
			foreach ( $new_deal_products as $new_deal_product ) {
				if ($new_deal_product ['product_id'] == $old_deal_product ['product_id']) {
					$deleted = false;
					break;
				}
			}
			if ($deleted) {
				// If the product has been ordered, the product can not be removed from the deal
				if ($old_deal_product ['product_sold_count'] > 0) {
					$product = $this->groupbuy_model->get_product ( $old_deal_product ['product_id'] );
					output_json_result ( false, sprintf ( lang ( 'deal_product_ordered' ), $product ['title'] ) );
					return FALSE;
				}
				array_push ( $delete_products, $old_deal_product );
			}
		}
		return TRUE;
	}
	private function _set_deal_default_values($deal_data, $user_id) {
		$deal_data ['owner_id'] = $user_id;
		$deal_data ['key'] = $this->_generate_deal_key ();
		$deal_data ['order_type'] = META_CODE_DEAL_ORDER_TYPE_SOLITAIRE;
		$deal_data ['title'] = 'Group buy (solitaire) _' . time ();
		$deal_data ['image_url'] = site_url ( '/css/img/deal_product_default.png' );
		// $deal_data ['end_time'] = time () + 3600 * 24 * 365;
		$deal_data ['payment_mode'] = META_CODE_DEAL_PAYMENT_NONE;
		$deal_data ['sales_person_id'] = $user_id;
		$deal_data ['rec_creator_id'] = $user_id;
		return $deal_data;
	}
	private function _mark_end($deal_id) {
		$deal = $this->groupbuy_model->get_deal ( $deal_id );
		if (! $deal) {
			return output_json_result ( false, 'Invalid deal ID' );
		}
		$open = $this->input->post ( 'open_flag' );
		
		if ($open && $deal ['end_time'] < time ()) {
			return output_json_result ( false, lang ( 'de_end_time_warming' ) );
		}
		$deal_data ['status'] = $open ? META_CODE_DEAL_STATUS_OPEN : META_CODE_DEAL_STATUS_CLOSED;
		$result = $this->groupbuy_model->update_deal ( $deal_id, $deal_data );
		if ($result) {
			return output_json_result ( true );
		} else {
			return output_json_result ( false, lang ( 'error_db' ) );
		}
	}
	private function _delete_deal($deal_id) {
		$user_id = $this->input->post ( 'user_id' );
		$user = $this->user_model->get_weee_user_by_global_id ( $user_id );
		if (! $user) {
			return output_json_result ( false, 'Invalid user ID' );
		}
		
		$deal = $this->groupbuy_model->get_deal ( $deal_id );
		if ($user_id != $deal ['rec_creator_id']) {
			return output_json_result ( false, '你没有权限操作' );
		}
		
		$order_count = $this->groupbuy_model->count_orders ( [ 
				'deal_id' => $deal_id 
		] );
		if ($order_count > 0) {
			return output_json_result ( false, '已经有人报名，不能删除' );
		}
		
		$result = $this->groupbuy_model->update_deal ( $deal_id, [ 
				'status' => META_CODE_DEAL_STATUS_DELETED 
		] );
		if ($result) {
			return output_json_result ( true, '操作成功' );
		} else {
			return output_json_result ( false, lang ( 'error_db' ) );
		}
	}
	private function _copy_deal($deal_id) {
		$user_id = $this->input->post ( 'user_id' );
		$user = $this->user_model->get_weee_user_by_global_id ( $user_id );
		if (! $user) {
			return output_json_result ( false, 'Invalid user ID' );
		}
		
		$deal = $this->groupbuy_model->get_deal ( $deal_id );
		if ($user_id != $deal ['rec_creator_id']) {
			return output_json_result ( false, '你没有权限操作' );
		}
		
		$post_data = $this->input->post ();
		
		$deal_data ['description'] = '【复制】' . $deal ['description'];
		$deal_data ['description_html'] = $deal ['description_html'];
		$deal_data ['currency'] = $deal ['currency'];
		
		// --copy additional setting info ---
		$deal_data ['deal_settings'] = $deal ['deal_settings'];
		$deal_data ['end_time'] = MAX_END_TIME;
		
		// Products ==================================================
		$deal_product_rows = $this->groupbuy_model->query_deal_products_with_resource ( $deal_id );
		foreach ( $deal_product_rows as $deal_product_row ) {
			if ($deal_product_row ['resource']) {
				$deal_product_row ['quantity'] = $deal_product_row ['resource'] ['quantity'];
			} else {
				$deal_product_row ['quantity'] = '';
			}
			$deal_products [$deal_product_row ['product_id']] = $deal_product_row;
		}
		
		$add_products = $deal_products;
		$update_products = [ ];
		$delete_products = [ ];
		// END Products ==================================================
		
		$this->db->trans_start ();
		
		$deal_data = $this->_set_deal_default_values ( $deal_data, $user_id );
		$result = $deal_id = $this->groupbuy_model->insert_deal ( $deal_data );
		if ($result) {
			$deal = $this->groupbuy_model->get_deal ( $deal_id );
		}
		
		// update the deal's products
		if ($result) {
			$deal_saver = & load_class ( 'DealSaver', 'biz/deal', '' );
			$result = $deal_saver->save_deal_products ( $deal, $user, $add_products, $delete_products, $update_products, [ ], false );
		}
		
		// TODO create gl tag
		
		$this->db->trans_complete ();
		
		if ($result) {
			// send notification
			$this->_send_create_deal_xw_template_message ( $deal );
			return $this->_get_deal ( $deal_id );
		} else {
			return output_json_result ( false, lang ( 'error_db' ) );
		}
	}
	private function _post_order($deal_id) {
		$user_id = $this->input->post ( 'user_id' );
		$user = $this->user_model->get_weee_user_by_global_id ( $user_id );
		if (! $user) {
			return output_json_result ( false, 'Invalid user ID' );
		}
		$deal = $this->groupbuy_model->get_deal ( $deal_id );
		if (! $deal || $deal ['status'] == META_CODE_DEAL_STATUS_DELETED) {
			return output_json_result ( false, 'Invalid deal ID' );
		}
		
		// Check deal
		if ($deal ['order_type'] !== META_CODE_DEAL_ORDER_TYPE_SOLITAIRE) {
			return output_json_result ( false, 'Invalid order type' );
		}
		if ($deal ['start_time'] > time ()) {
			return output_json_result ( false, lang ( 'deal_not_start' ) );
		}
		if ($deal ['status'] != META_CODE_DEAL_STATUS_OPEN || $deal ['end_time'] < time ()) {
			return output_json_result ( false, lang ( 'deal_closed' ) );
		}
		
		$user_orders = $this->groupbuy_model->query_orders ( [ 
				'deal_id' => $deal_id,
				'rec_creator_id' => $user_id,
				'status !=' => META_CODE_DEAL_ORDER_STATUS_CANCELLED 
		], 'id desc', 1 );
		$order_id = NULL;
		$order = NULL;
		if ($user_orders) {
			$order_id = $user_orders [0] ['id'];
			$order = $this->groupbuy_model->get_order ( $order_id );
		}
		
		// create order data
		$order_data = [ 
				'deal_id' => $deal_id,
				'sales_person_id' => $deal ['sales_person_id'],
				'payment_mode' => $deal ['payment_mode'],
				'rec_creator_id' => $user_id,
				'close_time' => date ( 'Y-m-d H:i:s' ) 
		];
		
		// create order products data
		$success = $this->_check_order_product_validity ( $this->input->post (), $deal, $order, $order_products_data, $total, $tax, $total_quantity, $error );
		if (! $success) {
			return output_json_result ( false, "{$error} " . lang ( 'deal_check_error' ) );
		}
		
		$order_data_update ['total'] = round ( $total, 2 );
		$order_data_update ['tax'] = round ( $tax, 2 );
		$order_data_update ['final_amount'] = $order_data_update ['total_with_tax'] = $total_with_tax = round ( $total + $tax, 2 );
		$order_data_update ['quantity'] = $total_quantity;
		$order_data_update ['comment'] = trim ( $this->input->post ( 'comment' ) );
		$order_data_update ['email'] = $this->input->post ( 'email' ) ? $this->input->post ( 'email' ) : '';
		$order_data_update ['phone'] = $this->input->post ( 'phone' ) ? $this->input->post ( 'phone' ) : '';
		$order_data_update ['addr_lastname'] = $this->input->post ( 'name' ) ? $this->input->post ( 'name' ) : '';
		
		$order_data += $order_data_update;
		
		$this->db->trans_start ();
		
		$result = true;
		if ($order) {
			$result = $this->groupbuy_model->update_order_seq ( $order_id, $deal_id, $order_data_update, $order_products_data, $order ['products'] );
			if ($result) {
				$lines = [ ];
				foreach ( $order_products_data as $order_product ) {
					$lines [] = "{$order_product['product_title']} X {$order_product['quantity']}";
				}
				$result = $this->groupbuy_model->insert_order_activity ( $order_id, 'modify_product', $user_id, implode ( $lines, "\n" ) );
			}
			$message = '更改成功';
		} else {
			$result = $order_id = $this->groupbuy_model->insert_order_seq ( $deal, $order_data, $order_products_data );
			if ($result) {
				$result = $this->groupbuy_model->insert_order_activity ( $order_id, 'create_order', $user_id );
			}
			if ($result) {
				$result = $this->groupbuy_model->update_order_seq_num ( $deal_id, $order_id );
			}
			
			if ($result) {
				$result = $this->groupbuy_model->update_order ( $order_id, [ 
						'status' => META_CODE_DEAL_ORDER_STATUS_CONFIRMED,
						'rec_confirm_time' => date ( 'Y-m-d H:i:s' ) 
				] );
			}
			if ($result) {
				$result = $this->groupbuy_model->insert_order_activity ( $order_id, 'confirm_order', 0 );
			}
			$message = '报名成功';
			if ($result) {
				if ($deal ['deal_settings'] && $deal_settings = json_decode ( $deal ['deal_settings'], true )) {
					if (key_exists ( 'order_count_limit', $deal_settings )) {
						$deal ['quantity'] = $deal_settings ['order_count_limit'];
						$where ['gb_order.deal_id'] = $deal_id;
						$deal_order_count = $this->groupbuy_model->count_orders ( $where );
						if ($deal ['quantity'] > 0 && $deal_order_count == $deal ['quantity']) {
							$deal_data ['status'] = META_CODE_DEAL_STATUS_CLOSED;
							$result = $this->groupbuy_model->update_deal ( $deal_id, $deal_data );
						}
					}
				}
			}
		}
		
		// TODO follow the owner's tag
		
		$this->db->trans_complete ();
		
		if ($result) {
			// order message notification
			$order = $this->groupbuy_model->get_order ( $order_id );
			$this->_send_post_order_success_wx_template_message ( $deal, $order );
			return output_json_result ( true, $message );
		} else {
			return output_json_result ( false, lang ( 'error_db' ) );
		}
	}
	private function _check_order_product_validity($input_data, $deal, $order, &$order_products, &$total, &$tax, &$total_quantity, &$error) {
		$order_products = array ();
		$total = $tax = $total_quantity = 0;
		
		$deal_product_rows = $this->groupbuy_model->query_deal_products_with_resource ( $deal ['id'] );
		$product_rows = $this->groupbuy_model->query_deal_products_with_vendor ( $deal ['id'] );
		$product_rows = array_combine ( array_column ( $product_rows, 'id' ), $product_rows );
		$deal_products = [ ];
		foreach ( $deal_product_rows as $index => $deal_product_row ) {
			$product_id = $deal_product_row ['product_id'];
			$deal_product_row ['product'] = $product_rows [$product_id];
			$deal_products [$product_id] = $deal_product_row;
		}
		
		$order_products_db = array ();
		if ($order) {
			$order_products_db = $order ['products'];
			$order_products_db = array_combine ( array_column ( $order_products_db, 'product_id' ), $order_products_db );
		}
		
		foreach ( $input_data as $field_name => $order_product_quantity ) {
			if ($order_product_quantity && preg_match ( '/^p_(\d+)$/', $field_name, $matches )) {
				$product_id = $matches [1];
				if (! key_exists ( $product_id, $deal_products )) {
					$error = sprintf ( lang ( 'deal_product_not_exist' ), $product_id );
					return FALSE;
				}
				$deal_product = $deal_products [$product_id];
				if ($deal_product ['resource']) {
					$quantity_db = key_exists ( $product_id, $order_products_db ) ? $order_products_db [$product_id] ['quantity'] : 0;
					if ($order_product_quantity > $quantity_db) {
						$limitation = $deal_product ['resource'];
						$remaining_count = $limitation ['quantity'] - $limitation ['sold_count'];
						if ($remaining_count < $order_product_quantity - $quantity_db) {
							$error = sprintf ( lang ( 'deal_product_not_enough' ), $deal_product ['product_title'] );
							return FALSE;
						}
					}
				}
				$sub_total = $order_product_quantity * $deal_product ['price'];
				$sub_tax = $deal_product ['product'] ['taxable'] === 'Y' ? round ( $sub_total * TAX_RATE, 2 ) : 0;
				array_push ( $order_products, [ 
						'product_id' => $product_id,
						'catalogue_num' => $deal_product ['product'] ['catalogue_num'],
						'price' => $deal_product ['price'],
						'quantity' => $order_product_quantity,
						'sub_total' => $sub_total,
						'tax' => $sub_tax,
						'sub_total_with_tax' => $sub_total + $sub_tax,
						'product_title' => $deal_product ['product_title'],
						'product_image_url' => $deal_product ['product_image_url'],
						'is_estimated_price' => $deal_product ['product'] ['is_estimated_price'] 
				] );
				
				$total += $sub_total;
				$tax += $sub_tax;
				$total_quantity += $order_product_quantity;
			}
		}
		return TRUE;
	}
	private function _send_create_deal_xw_template_message($deal) {
		$wx_app_id = $this->config->config [WX_XCH_API] ['appid'];
		$user_weixin_relation = $this->user_model->get_user_weixin_relation_where ( [ 
				'wx_app_id' => $wx_app_id,
				'user_id' => $deal ['rec_creator_id'] 
		] );
		if (! $user_weixin_relation) {
			log_message ( 'error', '_send_create_deal_xw_template_message: Can not get the user weixin open id' );
			return false;
		}
		
		$weixin_open_id = $user_weixin_relation ['openid'];
		$template_id = WEEE_WX_TEMPLATE_ID_XCH_EVENT_CREATE;
		$page = "pages/gb/view?deal_id={$deal['id']}";
		$form_id = $this->input->post ( 'form_id' );
		$description = $this->_get_description_text ( $deal ['description'] );
		if (mb_strlen ( $description ) > 50) {
			$description = mb_substr ( $description, 0, 50 ) . '...';
		}
		$parameters = build_wx_template_parameters ( [ 
				'keyword1' => '创建接龙成功',
				'keyword2' => $description 
		], '#000000' );
		return send_wx_xch_template_message ( $weixin_open_id, $template_id, $page, $form_id, $parameters );
	}
	private function _send_post_order_success_wx_template_message($deal, $order) {
		$wx_app_id = $this->config->config [WX_XCH_API] ['appid'];
		$user_weixin_relation = $this->user_model->get_user_weixin_relation_where ( [ 
				'wx_app_id' => $wx_app_id,
				'user_id' => $order ['rec_creator_id'] 
		] );
		if (! $user_weixin_relation) {
			log_message ( 'error', '_send_post_order_success_wx_template_message: Can not get the user weixin open id' );
			return false;
		}
		$weixin_open_id = $user_weixin_relation ['openid'];
		$template_id = WEEE_WX_TEMPLATE_ID_XCH_ORDER_SUCCESS;
		$page = "pages/gb/view?deal_id={$deal['id']}";
		$form_id = $this->input->post ( 'form_id' );
		$description = $this->_get_description_text ( $deal ['description'] );
		if (mb_strlen ( $description ) > 50) {
			$description = mb_substr ( $description, 0, 50 ) . '...';
		}
		$order_lines = [ ];
		foreach ( $order ['products'] as $order_product ) {
			$order_lines [] = "{$order_product['product_title']} X {$order_product['quantity']}";
		}
		$order_products_desc = implode ( "\n", $order_lines );
		$order_description = $this->_build_order_description ( $order_products_desc, $order, true );
		
		$currency_symbol = get_meta_code_value_by_key ( META_TYPE_DEAL_CURRENCY_SYMBOL, $deal ['currency'] );
		$parameters = build_wx_template_parameters ( [ 
				'keyword1' => $order ['seq_num'],
				'keyword2' => $description,
				'keyword3' => $order_description,
				'keyword4' => "{$currency_symbol}{$order ['total_with_tax']}" 
		], '#000000' );
		return send_wx_xch_template_message ( $weixin_open_id, $template_id, $page, $form_id, $parameters );
	}
	private function _generate_deal_key() {
		for($i = 0; $i < 10; $i ++) {
			$deal_key = generate_rand_string ( 5 );
			if (! is_numeric ( $deal_key ) && ! $this->groupbuy_model->get_deal_by_key ( $deal_key )) {
				return $deal_key;
			}
		}
	}
}
