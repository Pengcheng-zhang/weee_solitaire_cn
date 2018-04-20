<?php

/**
 *
 * @property CI_DB_active_record $db
 */
class Event_model extends CI_Model {
	public function get_event($event_id) {
		$this->db->where ( 'id', $event_id );
		$query = $this->db->get ( 'event' );
		return $query->row_array ();
	}
	public function get_event_by_key($key) {
		return $this->db->where ( 'key', $key )->get ( 'event' )->row_array ();
	}
	public function query_events($where, $orderby = '', $limit = 20, $offset = null) {
		if ($orderby)
			$this->db->order_by ( $orderby );
		$query = $this->db->get_where ( 'event', $where, $limit, $offset );
		// log_message ( 'debug', "SQL = \n" . $this->db->last_query () );
		return $query->result_array ();
	}
	public function query_events_for_index_page($where, $orderby = '', $limit = 20, $offset = null) {
		$this->db->select ( 'user.Global_User_ID, user.alias, user.headImgUrl, event.*, count(event_sign_up.event_id) sign_up_count, CASE WHEN end_time > UNIX_TIMESTAMP() THEN 0 ELSE 1 END close_flag', FALSE );
		$this->db->join ( 'event_sign_up', 'event.id = event_sign_up.event_id', 'left' );
		$this->db->join ( 'weee.user', 'event.rec_creator_id = weee.user.Global_User_ID', 'left' );
		$this->db->group_by ( 'event.id' );
		if ($orderby)
			$this->db->order_by ( $orderby );
		$query = $this->db->get_where ( 'event', $where, $limit, $offset );
		log_message ( 'debug', "SQL = \n" . $this->db->last_query () );
		return $query->result_array ();
	}
	public function count_events($where = NULL) {
		if ($where) {
			$this->db->where ( $where );
		}
		return $this->db->count_all_results ( 'event' );
	}
	public function query_events_with_details($where, $orderby = '', $limit = 20, $offset = null) {
		$this->db->select ( 'user.Global_User_ID, user.alias, user.headImgUrl, event.*, COUNT(event_sign_up.event_id) sign_up_count, CASE WHEN event.end_time > UNIX_TIMESTAMP() THEN 0 ELSE 1 END close_flag', FALSE );
		$this->db->join ( 'event_sign_up', 'event_sign_up.event_id = event.id', 'left' );
		$this->db->join ( 'weee.user', 'event.rec_creator_id = weee.user.Global_User_ID', 'left' );
		$this->db->group_by ( 'event.id' );
		if ($orderby)
			$this->db->order_by ( $orderby );
		
		$query = $this->db->get_where ( 'event', $where, $limit, $offset );
		log_message ( 'debug', '$this->db->last_query() = ' . print_r ( $this->db->last_query (), true ) );
		return $query->result_array ();
	}
	public function query_solitaire_events_and_deals($user_id, $limit = 20, $offset = null) {
		$sql = "select a.* from (
            select id,description,end_time,rec_create_time , 'event' as type, `event`.status from `event`  where   `event`.`rec_creator_id` = ?
            AND `event`.`publish` != 'X'
            AND `event`.`category` = 'solitaire'
            union 
            select id,description ,end_time,rec_create_time ,'deal' as type , status from gb_deal  where sales_person_id = ? AND order_type ='S' AND status != 'X')  a
        order by rec_create_time desc 
        LIMIT ? OFFSET ?";
		$result = $this->db->query ( $sql, [ 
				$user_id,
				$user_id,
				( int ) $limit,
				( int ) $offset 
		] );
		log_message ( 'debug', '$this->db->last_query() = ' . print_r ( $this->db->last_query (), true ) );
		return $result->result_array ();
	}
	public function count_solitaire_events_and_deals($user_id) {
		$sql = "SELECT COUNT(a.`key`) row_count from (
               select id,`key`,description,end_time,rec_create_time , 'event' as type,'0' as status from `event`  where   `event`.`rec_creator_id` = ?
            AND `event`.`publish` != 'X'
            AND `event`.`category` = 'solitaire'
            union 
            select id,`key`,description,end_time,rec_create_time ,'deal' as type , status from gb_deal  where sales_person_id = ? AND order_type ='S' AND publish != 'X')  a
        order by rec_create_time desc";
		$result = $this->db->query ( $sql, [ 
				$user_id,
				$user_id 
		] );
		// log_message ( 'debug', '$this->db->last_query() = ' . print_r ( $this->db->last_query (), true ) );
		return $result->row_array () ['row_count'];
	}
	public function query_join_events_and_deals($user_id, $limit = 20, $offset = null) {
		$sql = "SELECT a.* FROM
            (SELECT 
				`event`.id,`event`.description,`event`.end_time,`event`.rec_create_time,'event' AS type, `event`.status
            FROM
            event_sign_up
            JOIN `event` ON `event`.id = event_sign_up.event_id
            WHERE
               event_sign_up.user_id = ?
               AND `event`.`category` = 'solitaire'
		    UNION 
			SELECT 
                gb_deal.id,gb_deal.description,gb_deal.end_time,gb_deal.rec_create_time,'deal' AS type, gb_deal.status
            FROM
            gb_order
            JOIN gb_deal ON gb_deal.id = gb_order.deal_id
            WHERE
               gb_order.rec_creator_id = ?
               AND gb_deal.order_type = 'S'
            Group by gb_deal.id )  a
            ORDER BY rec_create_time DESC
            LIMIT ? OFFSET ?";
		$result = $this->db->query ( $sql, [ 
				$user_id,
				$user_id,
				( int ) $limit,
				( int ) $offset 
		] );
		log_message ( 'debug', '$this->db->last_query() = ' . print_r ( $this->db->last_query (), true ) );
		return $result->result_array ();
	}
	public function count_join_events_and_deals($user_id) {
		$sql = "SELECT COUNT(a.`key`) row_count from (
            SELECT 
				`event`.id,`event`.`key`,`event`.description,`event`.end_time,`event`.rec_create_time,'event' AS type,'0' AS status
            FROM
            event_sign_up
            JOIN `event` ON `event`.id = event_sign_up.event_id
            WHERE
               event_sign_up.user_id = ?
               AND `event`.`category` = 'solitaire'
		    UNION 
			SELECT 
                gb_deal.id,gb_deal.`key`,gb_deal.description,gb_deal.end_time,gb_deal.rec_create_time,'deal' AS type,gb_deal.status
            FROM
            gb_order
            JOIN gb_deal ON gb_deal.id = gb_order.deal_id
            WHERE
               gb_order.rec_creator_id = ?
               AND gb_deal.order_type = 'S'
            Group by gb_deal.id )  a
            order by rec_create_time desc";
		$result = $this->db->query ( $sql, [ 
				$user_id,
				$user_id 
		] );
		log_message ( 'debug', '$this->db->last_query() = ' . print_r ( $this->db->last_query (), true ) );
		return $result->row_array () ['row_count'];
	}
	public function count_events_with_details($where = NULL) {
		if ($where) {
			$this->db->where ( $where );
		}
		return $this->db->count_all_results ( 'event' );
	}
	public function update_event($id, $data) {
		$result = $this->db->where ( 'id', $id )->update ( 'event', $data );
		if ($this->db->_error_number ())
			log_message ( 'error', 'Event_model.update_event: ' . print_r ( $this->db->error (), true ) );
		return $result;
	}
	public function insert_event($data) {
		$result = $this->db->insert ( 'event', $data );
		if ($this->db->error () ['code'] != 0) {
			log_message ( 'error', 'Event_model.insert_event: ' . print_r ( $this->db->error (), true ) );
		}
		return $result ? $this->db->insert_id () : $result;
	}
	public function inc_event_view_count($id) {
		$this->db->where ( 'id', $id );
		$this->db->set ( 'view_count', '`view_count`+ 1', FALSE );
		$this->db->update ( 'event' );
	}
	public function get_event_sign_up_records($event_id, $where = null, $limit = null, $offset = null, $orderby = null) {
		$this->db->select ( 'event_sign_up.*,weee.user.*,event_sign_up.email as event_email', false );
		$this->db->join ( 'weee.user', 'event_sign_up.user_id = user.Global_User_ID' );
		$this->db->where ( 'event_id', $event_id );
		if ($where) {
			$this->db->where ( $where );
		}
		if ($orderby) {
			$this->db->order_by ( $orderby );
		} else {
			$this->db->order_by ( 'sign_up_time' );
		}
		$query = $this->db->get ( 'event_sign_up', $limit, $offset );
		// log_message ( 'debug', '$this->db->last_query() = ' . print_r ( $this->db->last_query (), true ) );
		return $query->result_array ();
	}
	public function count_event_sign_up_records($event_id, $where = null) {
		$this->db->where ( 'event_id', $event_id );
		if ($where) {
			$this->db->join ( 'weee.user', 'event_sign_up.user_id = user.Global_User_ID' );
			$this->db->where ( $where );
		}
		return $this->db->count_all_results ( 'event_sign_up' );
	}
	public function user_signed_up($event_id, $user_id) {
		$this->db->where ( 'event_id', $event_id );
		$this->db->where ( 'user_id', $user_id );
		$query = $this->db->get ( 'event_sign_up' );
		return $query->row_array ();
	}
	public function sign_up_event($data) {
		$result = $this->db->insert ( 'event_sign_up', array (
				'event_id' => $data ['event_id'],
				'user_id' => $data ['user_id'],
				'email' => $data ['email'],
				'phone' => $data ['phone'],
				'comment' => $data ['comment'],
				'hidden_content' => $data ['hidden_content'],
				'sign_up_time' => time (),
				'wk' => $data ['wk'] 
		) );
		if ($this->db->error () ['code'] != 0)
			log_message ( 'error', 'Event_model.sign_up_event: ' . print_r ( $this->db->error (), true ) );
		return $result;
	}
	public function insert_event_sign_up($data) {
		$result = $this->db->insert ( 'event_sign_up', $data );
		if ($this->db->error () ['code'] != 0)
			log_message ( 'error', 'Event_model.insert_event_sign_up: ' . print_r ( $this->db->error (), true ) );
		return $result;
	}
	public function update_event_sign_up($id, $data) {
		$result = $this->db->update ( 'event_sign_up', $data, [ 
				'id' => $id 
		] );
		if ($this->db->error () ['code'] != 0)
			log_message ( 'error', 'Event_model.update_event_sign_up: ' . print_r ( $this->db->error (), true ) );
		return $result;
	}
	public function update_event_sign_up_nums($event_id) {
		$sql = 'update event_sign_up e1
        join
    (select 
        id,
            @r:=IF(@e = event_id, @r + 1, 1) AS sign_up_num,
            @e:=event_id
    from
        event_sign_up, (SELECT @r:=1) AS r, (SELECT @e:=0) AS e
    where
        event_id = ?
    order by event_id , sign_up_time , id) e2 ON e1.id = e2.id 
set 
    e1.sign_up_num = e2.sign_up_num';
		$result = $this->db->query ( $sql, $event_id );
		if ($this->db->error () ['code'] != 0)
			log_message ( 'error', 'Event_model.update_event_sign_up_nums: ' . print_r ( $this->db->error (), true ) );
		return $result;
	}
	public function insert_event_sign_up_history($sign_up_row) {
		$sign_up_row ['event_sign_up_id'] = $sign_up_row ['id'];
		unset ( $sign_up_row ['id'] );
		$result = $this->db->insert ( 'event_sign_up_history', $sign_up_row );
		if ($this->db->error () ['code'] != 0)
			log_message ( 'error', 'Event_model.insert_event_sign_up_history: ' . print_r ( $this->db->error (), true ) );
		return $result;
	}
}
?>