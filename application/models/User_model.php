<?php
define ( 'TABLE_WEIXIN_USER', 'weee_weixin.user' );

/**
 *
 * @property CI_DB_active_record $db
 */
class User_model extends CI_Model {
	public function create_weee_user_from_weixin_sns($uuid, $alias, $wx_open_id, $wx_union_id, $head_img_url, $field_openid = 'wxSnsOpenId', $wx_app_id = null) {
		$user = $this->get_weee_user_by_wx_union_id ( $wx_union_id );
		
		if ($user) {
			$user_id = $user ['Global_User_ID'];
		} else {
			$user_data = array (
					'userId' => $uuid,
					'userType' => 'WX',
					'password' => '',
					'firstName' => '',
					'lastName' => '',
					'alias' => $alias,
					'email' => '',
					'language' => LANGUAGE_CHINESE,
					'wxUnionId' => $wx_union_id,
					'headImgUrl' => $head_img_url 
			);
			$result = $this->db->insert ( 'weee.user', $user_data );
			if ($result) {
				$user_id = $this->db->insert_id ();
				curl_asynchronous_call ( site_url ( 'user/init_username' ), [ 
						'user_id' => $user_id 
				] );
				$user = $this->get_weee_user_by_global_id ( $user_id );
			} else {
				$user = $this->get_weee_user_by_wx_union_id ( $wx_union_id );
				if ($user) {
					$user_id = $user ['Global_User_ID'];
				} else {
					log_message ( 'error', 'create_weee_user_from_weixin_sns: DB ERROR' );
					return false;
				}
			}
		}
		
		if ($field_openid) {
			// If the user exists, only the $field_openid maybe empty if the user is created by mobile app.
			if ($user [$field_openid] != $wx_open_id) {
				return $this->update_weee_user_by_global_id ( array (
						$field_openid => $wx_open_id 
				), $user_id );
			}
		}
		if ($wx_app_id) {
			$user_weixin = $this->get_user_weixin_relation ( $wx_app_id, $wx_open_id );
			if (! $user_weixin) {
				$this->insert_user_weixin_relation ( [ 
						'user_id' => $user_id,
						'wx_app_id' => $wx_app_id,
						'openid' => $wx_open_id,
						'unionid' => $wx_union_id,
						'nickname' => $alias,
						'headimgurl' => $head_img_url 
				] );
			}
		}
		
		return $user_id;
	}
	public function get_user_weixin_relation($wx_app_id, $open_id) {
		return $this->get_user_weixin_relation_where ( [ 
				'wx_app_id' => $wx_app_id,
				'openid' => $open_id 
		] );
	}
	public function get_user_weixin_relation_where($where) {
		$this->db->where ( $where );
		return $this->db->get ( 'weee_weixin.user_weixin' )->row_array ();
	}
	public function insert_user_weixin_relation($data) {
		$result = $this->db->insert ( 'weee_weixin.user_weixin', $data );
		if ($this->db->error () ['code'] != 0) {
			log_message ( 'error', '$this->db->last_query() = ' . print_r ( $this->db->last_query (), true ) );
			log_message ( 'error', 'User_model.insert_user_weixin_relation: ' . print_r ( $this->db->error (), true ) );
		}
		return $result;
	}
	public function get_weee_user_by_global_id($global_user_id) {
		return $this->get_weee_user ( array (
				'Global_User_ID' => $global_user_id 
		) );
	}
	public function get_weee_user($where) {
		$query = $this->db->get_where ( 'weee.user', $where );
		$user = $query->row_array ();
		if ($this->db->error () ['code'] != 0) {
			log_message ( 'error', 'User_model.get_weee_user: ' . print_r ( $this->db->error (), true ) );
		}
		return $user;
	}
}
?>
