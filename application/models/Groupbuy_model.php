<?php

/**
 *
 * @property CI_DB_active_record $db
 */
class Groupbuy_model extends CI_Model {
	public function get_deal($id) {
		return $this->db->get_where ( 'gb_deal', [
				'id' => $id
		] )->row_array ();
	}
	public function get_deal_by_key($key) {
		return $this->db->get_where ( 'gb_deal', [
				'key' => $key
		] )->row_array ();
	}
	public function insert_deal($deal_data) {
		$this->db->insert ( 'gb_deal', $deal_data );
		if ($this->db->_error_number ()) {
			log_message ( 'debug', '$this->db->last_query() = ' . print_r ( $this->db->last_query (), true ) );
			log_message ( 'error', 'Groupbuy_model.insert_deal: ' . $this->db->_error_number () . ':' . $this->db->_error_message () );
			return false;
		}
		return $this->db->insert_id ();
	}
	public function update_deal($id, $deal_data) {
		$this->db->update ( 'gb_deal', $deal_data, [
				'id' => $id
		] );
		if ($this->db->_error_number ()) {
			log_message ( 'error', '$this->db->last_query() = ' . print_r ( $this->db->last_query (), true ) );
			log_message ( 'error', 'Groupbuy_model.update_deal: ' . $this->db->_error_number () . ':' . $this->db->_error_message () );
			return false;
		}
		return true;
	}
	public function query_orders_with_products_desc($where = null, $orderby = '', $limit = null, $offset = null) {
		$this->db->select ( 'gb_order.*, weee.user.alias, weee.user.headImgUrl, weee.user.member,
				IFNULL(gb_pick_up_point.name, gb_order.pick_up_point) pick_up_point_name,
				GROUP_CONCAT(concat(IFNULL(gb_order_product.product_title, gb_product.title), \' x \', gb_order_product.quantity) SEPARATOR "\n") products_desc', FALSE );
		$this->db->join ( 'gb_order_product', 'gb_order.id = gb_order_product.order_id', 'left' );
		$this->db->join ( 'gb_product', 'gb_product.id = gb_order_product.product_id', 'left' );
		$this->db->join ( 'weee.user', 'weee.user.Global_User_ID = gb_order.rec_creator_id' );
		$this->db->join ( 'gb_pick_up_point', 'gb_order.pick_up_point = gb_pick_up_point.id', 'left' );
		$this->db->group_by ( 'gb_order.id' );
		if ($orderby)
			$this->db->order_by ( $orderby );
			$query = $this->db->get_where ( 'gb_order', $where, $limit, $offset );
			if ($this->db->_error_number ()) {
				log_message ( 'debug', '$this->db->last_query() = ' . print_r ( $this->db->last_query (), true ) );
				log_message ( 'error', 'Groupbuy_model.query_orders_with_products_desc: ' . $this->db->_error_number () . ':' . $this->db->_error_message () );
				return false;
			}
			// log_message ( 'debug', '$this->db->last_query() = ' . print_r ( $this->db->last_query (), true ) );
			return $query->result_array ();
	}
	public function query_deal_products_with_resource($deal_id) {
		$this->db->select ( 'gb_deal_product.*,
				gb_sales_resource.quantity,
				gb_sales_resource.sold_count,
				gb_sales_resource.product_num,
				IFNULL(gb_resource_pool_item.special_sales_resource_id ,gb_resource_pool_item.sales_resource_id) pool_sales_resource_id', false );
		$this->db->join ( 'gb_sales_resource', 'gb_deal_product.resource_id = gb_sales_resource.id', 'left' );
		$this->db->join ( 'gb_deal', 'gb_deal.id = gb_deal_product.deal_id' );
		$this->db->join ( 'gb_deal_resource', 'gb_deal_resource.id = gb_deal.resource_id', 'left' );
		$this->db->join ( 'gb_deal_resource_product', 'gb_deal_resource_product.deal_resource_id = gb_deal.resource_id AND gb_deal_resource_product.product_id = gb_deal_product.product_id', 'left' );
		$this->db->join ( 'gb_resource_pool_item', 'gb_resource_pool_item.id = gb_deal_resource_product.pool_item_id', 'left' );
		$this->db->order_by ( 'pos' );
		$query = $this->db->get_where ( 'gb_deal_product', [
				'deal_id' => $deal_id
		] );
		$deal_products = $query->result_array ();
		foreach ( $deal_products as &$deal_product ) {
			if ($deal_product ['resource_id']) {
				$deal_product ['resource'] = [
						'id' => $deal_product ['resource_id'],
						'quantity' => $deal_product ['quantity'],
						'sold_count' => $deal_product ['sold_count'],
						'product_num' => $deal_product ['product_num']
				];
			} else {
				$deal_product ['resource'] = NULL;
			}
			unset ( $deal_product ['resource_id'] );
			unset ( $deal_product ['quantity'] );
			unset ( $deal_product ['sold_count'] );
			unset ( $deal_product ['product_num'] );
		}
		return $deal_products;
	}
	public function count_orders($where = null) {
		if ($where) {
			$this->db->where ( $where );
		}
		return $this->db->count_all_results ( 'gb_order' );
	}
	public function get_order($order_id) {
		$this->db->select ( 'gb_order.*, IFNULL( gb_pick_up_point.name, gb_order.pick_up_point ) pick_up_point_name, IFNULL( gb_deal_pick_up_point.address, gb_pick_up_point.address ) pick_up_point_address, gb_deal_pick_up_point.comment pick_up_point_comment, gb_pick_up_point.code' );
		$this->db->join ( 'gb_pick_up_point', 'gb_order.pick_up_point = gb_pick_up_point.id', 'left' );
		$this->db->join ( 'gb_deal_pick_up_point', 'gb_order.pick_up_point = gb_deal_pick_up_point.pick_up_point_id and gb_deal_pick_up_point.deal_id = gb_order.deal_id', 'left' );
		$order = $this->db->where ( 'gb_order.id', $order_id )->get ( 'gb_order' )->row_array ();
		if (! $order) {
			return $order;
		}
	
		$this->db->select ( 'gb_order_product.*,
				IFNULL(gb_order_product.product_title, gb_product.title) title,
				IFNULL(gb_order_product.product_image_url, gb_product.image_url) image_url,
				gb_product.vender_id,
				gb_product.short_title,
				gb_product.location_code,
				gb_product.distribution_category,
				gb_product.is_estimated_price,
				gb_product.catalogue_num,
				gb_product.sale_points,
				gb_product.min_order_quantity,
				gb_product.product_feature', false );
		$this->db->join ( 'gb_product', 'gb_product.id = gb_order_product.product_id' );
		$order ['products'] = $this->db->get_where ( 'gb_order_product', [
				'order_id' => $order_id
		] )->result_array ();
	
		return $order;
	}
	public function query_orders($where = null, $orderby = '', $limit = null, $offset = null) {
		if ($orderby)
			$this->db->order_by ( $orderby );
			$query = $this->db->get_where ( 'gb_order', $where, $limit, $offset );
			// log_message ( 'debug', '$this->db->last_query() = ' . print_r ( $this->db->last_query (), true ) );
			return $query->result_array ();
	}
	public function update_order($id, $order_data) {
		return $this->update_order_where ( $order_data, [
				'id' => $id
		] );
	}
	public function update_order_where($order_data, $where) {
		$this->db->update ( 'gb_order', $order_data, $where );
		if ($this->db->_error_number ()) {
			log_message ( 'error', '$this->db->last_query() = ' . print_r ( $this->db->last_query (), true ) );
			log_message ( 'error', 'Groupbuy_model.update_order_where: ' . $this->db->_error_number () . ':' . $this->db->_error_message () );
			return false;
		}
		return true;
	}
	public function update_order_seq($order_id, $deal_id, $order_data, $order_products_data, $order_products_db) {
		log_message ( 'debug', '$order_data = ' . print_r ( $order_data, true ) );
		log_message ( 'debug', '$order_products_data = ' . print_r ( $order_products_data, true ) );
	
		log_message ( 'debug', 'Groupbuy_model._update_order_seq: updated order data.' );
		$result = $this->update_order ( $order_id, $order_data );
		if (! $result) {
			return FALSE;
		}
	
		$order_products_db = array_combine ( array_column ( $order_products_db, 'product_id' ), $order_products_db );
		$deal_products = $this->query_deal_products_with_resource ( $deal_id );
		$deal_products = array_combine ( array_column ( $deal_products, 'product_id' ), $deal_products );
	
		$order_product_fields = array_flip ( [
				'order_id',
				'product_id',
				'price',
				'quantity',
				'sub_total',
				'tax',
				'sub_total_with_tax',
				'product_title',
				'product_image_url'
		] );
		log_message ( 'debug', 'Groupbuy_model.update_order_seq: updated deal product sold count.' );
		$order_products_insert = [ ];
		$order_products_update = [ ];
		$deal_product_quantity_diffs = [ ];
		foreach ( $order_products_data as $idx => $order_product_data ) {
			$order_product_data = array_intersect_key ( $order_product_data, $order_product_fields );
			$product_id = $order_product_data ['product_id'];
			$order_product_data ['order_id'] = $order_id;
				
			// update product_sold_count @ gb_deal_product
			$sold_count = $order_product_data ['quantity'];
			if (key_exists ( $product_id, $order_products_db )) {
				$deal_product_quantity_diffs [$product_id] = $sold_count - $order_products_db [$product_id] ['quantity'];
				$order_product_data ['id'] = $order_products_db [$product_id] ['id'];
				unset ( $order_products_db [$product_id] );
				$order_products_update [] = $order_product_data;
			} else {
				$deal_product_quantity_diffs [$product_id] = $sold_count;
				$order_products_insert [] = $order_product_data;
			}
		}
		foreach ( $order_products_db as $order_product_db ) {
			$deal_product_quantity_diffs [$order_product_db ['product_id']] = - $order_product_db ['quantity'];
		}
		foreach ( $deal_product_quantity_diffs as $product_id => $deal_product_quantity_diff ) {
			// update product_sold_count @ gb_deal_product
			$result = $this->update_deal_product_sold_count ( $deal_id, $product_id, $deal_product_quantity_diff );
			if ($result) {
				return false;
			}
				
			// update sold_count @ gb_sales_resource
			if (key_exists ( $product_id, $deal_products )) {
				$deal_product = $deal_products [$product_id];
				// the limitaion of the resource
				if ($deal_product ['resource']) {
					$result = $this->update_sales_resource ( $deal_product ['resource'] ['id'], 0, $deal_product_quantity_diff, 0 );
					if (! $result) {
						return false;
					}
				}
				// the limitation of the pool
				if ($deal_product ['pool_sales_resource_id']) {
					$result = $this->update_sales_resource ( $deal_product ['pool_sales_resource_id'], 0, $deal_product_quantity_diff, 0 );
					if (! $result) {
						return false;
					}
				}
			}
		}
	
		log_message ( 'debug', 'Groupbuy_model.update_order_seq: inserted order products.' );
		if ($order_products_insert) {
			$this->db->insert_batch ( 'gb_order_product', $order_products_insert );
			if ($this->db->_error_number ()) {
				log_message ( 'error', '$this->db->last_query() = ' . print_r ( $this->db->last_query (), true ) );
				log_message ( 'error', 'Groupbuy_model.update_order_seq: ' . $this->db->_error_number () . ':' . $this->db->_error_message () );
				return false;
			}
		}
		log_message ( 'debug', 'Groupbuy_model.update_order_seq: update order products.' );
		foreach ( $order_products_update as $order_product_update ) {
			$result = $this->update_order_product ( $order_product_update ['id'], $order_product_update );
			if (! $result) {
				return false;
			}
		}
		log_message ( 'debug', 'Groupbuy_model.update_order_seq: delete order products.' );
		foreach ( $order_products_db as $order_product_db ) {
			$result = $this->delete_order_product ( $order_id, $order_product_db ['product_id'] );
			if (! $result) {
				return false;
			}
		}
	
		return true;
	}
	public function insert_order_activity($order_id, $type, $user_id, $comment = null, $amount = null) {
		return $this->db->insert ( 'gb_order_activity', [
				'order_id' => $order_id,
				'type' => $type,
				'comment' => $comment,
				'rec_creator_id' => $user_id,
				'amount' => $amount
		] );
	}
	public function get_product($id) {
		return $this->db->get_where ( 'gb_product', [
				'id' => $id
		] )->row_array ();
	}
	public function query_deal_products_with_vendor($deal_id, $where = null, $orderby = 'pos', $limit = null) {
		$this->db->select ( 'gb_product.id product_id ,gb_product.*,
				IFNULL(gb_deal_product.product_title, gb_product.title) deal_product_title,
				IFNULL(gb_deal_product.product_image_url, gb_product.image_url) deal_product_image_url,
				gb_deal_product.status deal_product_status,
				gb_deal_product.product_sold_count,
				gb_deal_product.price deal_product_price,
				gb_deal_product.pos,
				IFNULL(gb_catalogue.view_pos, 999) category_pos,
				gb_vender.title vender_title', false );
		$this->db->join ( 'gb_product', 'gb_product.id = gb_deal_product.product_id' );
		$this->db->join ( 'gb_catalogue', 'gb_catalogue.num = gb_product.catalogue_num', 'left' );
		$this->db->join ( 'gb_vender', 'gb_vender.id = gb_product.vender_id', 'left' );
		if ($where) {
			$this->db->where ( $where );
		}
		$this->db->ar_orderby [] = $orderby;
		$query = $this->db->get_where ( 'gb_deal_product', [
				'deal_id' => $deal_id
		], $limit );
		return $query->result_array ();
	}
}
?>
