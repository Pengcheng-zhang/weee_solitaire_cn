<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 *
 * @property Image_model $image_model
 * @property User_model $user_model
 */
class Admin_image extends CI_Controller {
	function __construct() {
		parent::__construct ();
		$this->load->model ( 'image_model' );
		$this->load->model ( 'user_model' );
		$this->load->helper ( 'form' );
		$this->load->helper ( 'uuid' );
		$this->load->helper ( 'image' );
	}
	function index() {
		$where = null;
		$query_type = $this->input->post ( 'query_type' );
		if ($query_type) {
			$where ['type'] = $query_type;
		}
		$query_string = $this->input->post ( 'query_string' );
		if ($query_string) {
			$where ['description like'] = '%' . $query_string . '%';
		}
		
		$images = $this->image_model->query_images ( $where );
		
		$data = $this->input->post ();
		$data ['title'] = 'Image Management';
		$data ['current_nav_key'] = 'admin_image';
		$data ['images'] = $images;
		
		$this->load->view ( 'admin/header', $data );
		$this->load->view ( 'admin/admin_image', $data );
		$this->load->view ( 'templates/footer' );
	}
	function proxy() {
		$url = $this->input->get ( 'url' );
		if ($url) {
			$headers = get_headers ( $url, 1 );
			if ($headers) {
				log_message ( 'debug', 'headers: ' . print_r ( $headers, true ) );
				header ( 'Content-Length: ' . $headers ['Content-Length'] );
				header ( 'Content-Type: ' . $headers ['Content-Type'] );
				readfile ( $url );
				die ();
				exit ();
			}
		}
	}
	function api_query_image($image_id) {
		$image = $this->image_model->get_images ( $image_id );
		$this->output->set_content_type ( 'application/json' )->set_output ( json_encode ( $image ) );
	}
	function api_update_image() {
		// log_message ( 'debug', print_r ( $this->input->post (), true ) );
		$data = $this->input->post ();
		$id = $data ['id'];
		$server_path = $data ['server_path'];
		$image_data = $data ['image_data'];
		$data = array (
				'type' => $data ['type'],
				'description' => $data ['description'] 
		);
		
		if ($id) {
			if ($image_data) {
				$result = $this->_save_uploaded_image_for_admin_page ( $image_data, $server_path );
				if (! $result) {
					echo 'Failed to save the image on the server';
					return;
				}
			}
			$result = $this->image_model->update_image ( $id, $data );
		} else {
			
			if (! $image_data) {
				echo 'Please choose a image file';
				return;
			}
			$this->load->helper ( 'uuid' );
			$img_relative_path = date ( 'Y-m' ) . DIRECTORY_SEPARATOR . encode_uuid_base64 ( gen_uuid () ) . '.jpg';
			$server_path = UPLOAD_STATIC_IMG_PATH . $img_relative_path;
			$result = $this->_save_uploaded_image_for_admin_page ( $image_data, $server_path );
			if (! $result) {
				echo 'Failed to save the image on the server';
				return;
			}
			$data ['server_path'] = $server_path;
			$data ['url'] = site_url ( 'static_img/' . $img_relative_path );
			$result = $this->image_model->insert_image ( $data );
		}
		if (! $result) {
			echo 'Failed to update the database: ' . $this->db->_error_message ();
		}
	}
	private function _save_uploaded_image_for_admin_page($image_data, $server_path) {
		$this->load->helper ( 'image' );
		$result = save_jpg_data_to_server ( $image_data, $server_path );
		if (! $result) {
			log_message ( 'error', 'Failed to save the uploaded image on the server.' );
			return $result;
		}
		
		$thumbnail_image_path = create_square_thumbnail_image ( $server_path, WEEE_IMAGE_THUMBNAIL_SIZE, true );
		if ($thumbnail_image_path) {
			return $thumbnail_image_path;
		} else {
			log_message ( 'error', 'Failed to resize the uploaded image.' );
			return false;
		}
	}
	/**
	 * Save the jpg image data into the server tmp images folder, create the square thumbnail of the image.
	 *
	 * @return json_result. If everything is ok, the object in the json_result contains the url of the saved image and thumbnail.
	 *         array('img_url', 'img_thumbnail_url')
	 */
	public function upload_image() {
		$image_data = $this->input->post ( 'image_data' );
		if (! $image_data) {
			return output_json_result ( false, 'Please choose a image file.' );
		}
		$user_temp_folder = $this->input->post ( 'temp_folder' );
		
		$image_path = $this->_get_image_path ( $user_temp_folder );
		$result = save_jpg_data_to_server ( $image_data, $image_path );
		if (! $result) {
			log_message ( 'error', 'Admin_image.upload_image: Failed to save the uploaded image on the server.' );
			return output_json_result ( false, lang ( 'error_save_image' ) );
		}
		return $this->_create_thumbnail_image_and_output_result ( $image_path );
	}
	/**
	 * The method is for weixin image upload.
	 * Download the image from the weixin server by 'server_id' and save the jpg image data into the server tmp images folder, create the square thumbnail of the image.
	 *
	 * @return json_result. If everything is ok, the object in the json_result contains the url of the saved image and thumbnail.
	 *         array('img_url', 'img_thumbnail_url')
	 */
	public function wx_upload_image() {
		log_message ( 'debug', '$this->input->post () = ' . print_r ( json_encode ( $_POST ), true ) );
		$server_id = $this->input->post ( 'server_id' );
		if (! $server_id) {
			return output_json_result ( false, 'Internal Error: empty server id' );
		}
		$user_temp_folder = $this->input->post ( 'temp_folder' );
		
		$this->load->helper ( 'wx_api' );
		$access_token = get_wx_access_token ();
		$picture_url = "http://file.api.weixin.qq.com/cgi-bin/media/get?access_token=$access_token&media_id=$server_id";
		$content = file_get_contents ( $picture_url );
		if (! $content) {
			log_message ( 'error', 'Admin_image.wx_upload_image: Failed to download the image: ' . $picture_url );
			return output_json_result ( false, lang ( 'error_save_image' ) );
		}
		$image_path = $this->_get_image_path ( $user_temp_folder );
		$server_folder_path = dirname ( $image_path );
		if (! file_exists ( $server_folder_path )) {
			mkdir ( $server_folder_path, 0777, true );
		}
		file_put_contents ( $image_path, $content );
		
		resize_image ( $image_path, WEEE_ARTICLE_IMAGE_SIZE, false );
		
		return $this->_create_thumbnail_image_and_output_result ( $image_path );
	}
	public function upload_image_file() {
		log_message ( 'debug', '$_FILES = ' . print_r ( $_FILES, true ) );
		
		$user_temp_folder = $this->input->post ( 'user_temp_folder' );
		$image_path = $this->_get_image_path ( $user_temp_folder );
		$server_folder_path = dirname ( $image_path );
		if (! file_exists ( $server_folder_path )) {
			mkdir ( $server_folder_path, 0777, true );
		}
		
		$config ['upload_path'] = $server_folder_path;
		$config ['allowed_types'] = 'gif|jpg|png';
		// $config['max_size'] = '100';
		// $config['max_width'] = '1024';
		// $config['max_height'] = '768';
		$config ['file_name'] = basename ( $image_path );
		$this->load->library ( 'upload', $config );
		
		if (! $this->upload->do_upload ( 'file' )) {
			$message = $this->upload->display_errors ();
			log_message ( 'debug', 'upload_image_file: error = ' . $message );
			return output_json_result ( false, $message );
		}
		log_message ( 'debug', '$this->upload->data(): ' . print_r ( $this->upload->data (), true ) );
		
		resize_image ( $image_path, WEEE_ARTICLE_IMAGE_SIZE, false );
		
		return $this->_create_thumbnail_image_and_output_result ( $image_path );
	}
	private function _get_image_path($user_temp_folder) {
		$folder_path = $user_temp_folder ? UPLOAD_STATIC_TMP_IMG_PATH : (UPLOAD_STATIC_IMG_PATH . date ( 'Y-m' ) . '/');
		return $folder_path . encode_uuid_base64 ( gen_uuid () ) . '.jpg';
	}
	private function _create_thumbnail_image_and_output_result($orignail_image_path) {
		$orignail_image_name = substr ( $orignail_image_path, strrpos ( $orignail_image_path, DIRECTORY_SEPARATOR ) + 1 );
		
		$thumbnail_image_path = create_square_thumbnail_image ( $orignail_image_path, WEEE_IMAGE_THUMBNAIL_SIZE, true );
		if ($thumbnail_image_path) {
			$img_url = site_url ( UPLOAD_STATIC_IMG_WEB_PATH . substr ( $orignail_image_path, strlen ( UPLOAD_STATIC_IMG_PATH ) ) );
			return output_json_result ( true, '', array (
					'orignail_img_path' => $orignail_image_path,
					'img_url' => $img_url,
					'img_thumbnail_url' => get_image_square_thumbnail_image ( $img_url ) 
			) );
		} else {
			log_message ( 'error', 'Admin_image._create_thumbnail_image_and_output_result: Failed to create the thumbnail image: ' . $orignail_image_path );
			return output_json_result ( false, lang ( 'error_save_image' ) );
		}
	}
	/**
	 * The method is for test
	 */
	public function resize_server_image() {
		$path = $this->input->post ( 'path' );
		$length = $this->input->post ( 'length' );
		$master_dim_for_long_side = $this->input->post ( 'master_dim_for_long_side' );
		if (! $path || ! $length) {
			return output_json_result ( false, 'Empty input.' );
		}
		
		$result = resize_image ( $path, $length, $master_dim_for_long_side );
		if ($result) {
			return output_json_result ( true );
		} else {
			return output_json_result ( false, 'Falied to resize the image. Please check the log for details.' );
		}
	}
	public function compress_image_files() {
		$path = $this->input->post ( 'path' );
		if (! $path) {
			return output_json_result ( false, 'Empty input.' );
		}
		
		if (is_file ( $path )) {
			echo $this->_compress_image ( $path );
		} else {
			$count = 0;
			foreach ( glob ( $path . '/*.*' ) as $file ) {
				log_message ( 'debug', '$file = ' . print_r ( $file, true ) );
				if (preg_match ( '/jpg$/', $file )) {
					$count += $this->_compress_image ( $file );
				}
			}
			echo "Convertd file: $count";
		}
	}
	private function _compress_image($file) {
		try {
			$need_save = false;
			$image = new Imagick ( $file );
			log_message ( 'debug', '$image->getimageformat () = ' . print_r ( $image->getimageformat (), true ) );
			if ($image->getimagewidth () > WEEE_ARTICLE_IMAGE_SIZE && $image->getimageheight () > WEEE_ARTICLE_IMAGE_SIZE) {
				$need_save = true;
				$image->resizeimage ( WEEE_ARTICLE_IMAGE_SIZE, WEEE_ARTICLE_IMAGE_SIZE, Imagick::FILTER_LANCZOS, 1, true );
			}
			if ('PNG' === $image->getimageformat ()) {
				$need_save = true;
			}
			if ($need_save) {
				$image->setimageformat ( 'jpeg' );
				file_put_contents ( $file, $image );
			}
			$image->destroy ();
		} catch ( Exception $e ) {
			$need_save = false;
		}
		return $need_save;
	}
}