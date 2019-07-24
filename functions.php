<?php

//修正管理後台頁尾顯示
function dashboard_footer_design() {
	echo 'Design by <a href="http://www.knockers.com.tw">Knockers</a>';
}
add_filter('admin_footer_text', 'dashboard_footer_design');
//修正管理後台頁尾顯示
function dashboard_footer_developer() {
	echo '<br/><span id="footer-thankyou">Developed by <a href="https://www.mxp.tw">一介資男</a></span>';
}
add_filter('admin_footer_text', 'dashboard_footer_developer');
//修正管理後台顯示
function clean_my_admin_head() {
	$screen = get_current_screen();
	$str = '';
	if (is_admin() && ($screen->id == 'dashboard')) {
		$str .= '<style>#wp-version-message { display: none; } #footer-upgrade {display: none;}</style>';
	}
	echo $str;
}
add_action('admin_head', 'clean_my_admin_head');
//最佳化主題樣式相關
function optimize_theme_setup() {
	//整理head資訊
	remove_action('wp_head', 'wp_generator');
	remove_action('wp_head', 'wlwmanifest_link');
	remove_action('wp_head', 'rsd_link');
	remove_action('wp_head', 'wp_shortlink_wp_head');
	add_filter('the_generator', '__return_false');
	//管理員等級的角色不要隱藏 admin bar
	if (!current_user_can('manage_options')) {
		add_filter('show_admin_bar', '__return_false');
	}
	remove_action('wp_head', 'print_emoji_detection_script', 7);
	remove_action('wp_print_styles', 'print_emoji_styles');
	remove_action('wp_head', 'feed_links_extra', 3);
	//移除css, js資源載入時的版本資訊
	function remove_version_query($src) {
		if (strpos($src, 'ver=')) {
			$src = remove_query_arg('ver', $src);
		}
		return $src;
	}
	add_filter('style_loader_src', 'remove_version_query', 999);
	add_filter('script_loader_src', 'remove_version_query', 999);
	add_filter('widget_text', 'do_shortcode');
}
add_action('after_setup_theme', 'optimize_theme_setup');

//open content block for VC
add_filter('content_block_post_type', '__return_true');

//使用 content block 時會被當作一般的 post 被安插其他處理，自己包過來用
//ref: https://tw.wordpress.org/plugins/custom-post-widget/
function knockers_custom_post_widget_shortcode($atts) {
	extract(shortcode_atts(array(
		'id' => '',
		'slug' => '',
		'class' => 'content_block',
		'suppress_content_filters' => 'yes', //預設不走 the_content 的事件，避免被其他方法給包過
		'title' => 'no',
		'title_tag' => 'h3',
		'only_img' => 'no', //僅輸出特色圖片連結
	), $atts));

	if ($slug) {
		$block = get_page_by_path($slug, OBJECT, 'content_block');
		if ($block) {
			$id = $block->ID;
		}
	}

	$content = "";

	if ($id != "") {
		$args = array(
			'post__in' => array($id),
			'post_type' => 'content_block',
		);

		$content_post = get_posts($args);

		foreach ($content_post as $post):
			$content .= '<div class="' . esc_attr($class) . '" id="custom_post_widget-' . $id . '">';
			if ($title === 'yes') {
				$content .= '<' . esc_attr($title_tag) . '>' . $post->post_title . '</' . esc_attr($title_tag) . '>';
			}
			if ($suppress_content_filters === 'no') {
				$content .= apply_filters('the_content', $post->post_content);
			} else {
				if (has_shortcode($post->post_content, 'content_block') || has_shortcode($post->post_content, 'ks_content_block')) {
					$content .= $post->post_content;
				} else {
					$content .= do_shortcode($post->post_content);
				}
			}
			$content .= '</div>';
		endforeach;
	}
	if ($only_img == "yes") {
		$featured_image = get_the_post_thumbnail_url($id, 'full');
		return $featured_image ? $featured_image : $content;
	}
	return $content;
}
add_shortcode('ks_content_block', 'knockers_custom_post_widget_shortcode');

function logger($file, $data) {
	file_put_contents(
		ABSPATH . "wp-content/{$file}.txt",
		'===' . date('Y-m-d H:i:s', time()) . '===' . PHP_EOL . $data . PHP_EOL,
		FILE_APPEND
	);
}

function check_some_other_plugin() {
	//給CF7啟用短碼機制
	// if (is_plugin_active('contact-form-7/wp-contact-form-7.php')) {
	add_filter('wpcf7_form_elements', 'do_shortcode');
	// }
}
add_action('admin_init', 'check_some_other_plugin');

//阻止縮圖浪費空間
function ks_wp_get_attachment_image_src($image, $attachment_id, $size, $icon) {
	// get a thumbnail or intermediate image if there is one
	$image = image_downsize($attachment_id, 'full');
	if (!$image) {
		$src = false;

		if ($icon && $src = wp_mime_type_icon($attachment_id)) {
			/** This filter is documented in wp-includes/post.php */
			$icon_dir = apply_filters('icon_dir', ABSPATH . WPINC . '/images/media');

			$src_file = $icon_dir . '/' . wp_basename($src);
			@list($width, $height) = getimagesize($src_file);
		}

		if ($src && $width && $height) {
			$image = array($src, $width, $height);
		}
	}
	return $image;
}
add_filter('wp_get_attachment_image_src', 'ks_wp_get_attachment_image_src', 99, 4);
add_filter('intermediate_image_sizes', '__return_empty_array');

//上傳檔案時判斷為圖片時自動加上標題、替代標題、摘要、描述等內容
function ks_set_image_meta_upon_image_upload($post_ID) {

	if (wp_attachment_is_image($post_ID)) {
		$my_image_title = get_post($post_ID)->post_title;
		$my_image_title = preg_replace('%\s*[-_\s]+\s*%', ' ', $my_image_title);
		$my_image_title = ucwords(strtolower($my_image_title));
		$my_image_meta = array(
			'ID' => $post_ID,
			'post_title' => $my_image_title,
			'post_excerpt' => $my_image_title,
			'post_content' => $my_image_title,
		);
		update_post_meta($post_ID, '_wp_attachment_image_alt', $my_image_title);
		wp_update_post($my_image_meta);
	}
}
add_action('add_attachment', 'ks_set_image_meta_upon_image_upload');

function ks_add_theme_caps() {
	$roles = array('editor', 'contributor', 'author', 'shop_manager');
	foreach ($roles as $key => $role) {
		//取得授權角色
		if ($role = get_role($role)) {
			//開通權限，對權限控管，可以補上預設的權限來避免設定疏失
			$role->add_cap('unfiltered_html');
			$role->add_cap('edit_theme_options');
			//開通 https://tw.wordpress.org/plugins/contact-form-cfdb7/ 這外掛使用權限
			$role->add_cap('cfdb7_access');
		}
	}
}
add_action('admin_init', 'ks_add_theme_caps');

/**
 * 如果VC還沒解開角色授權使用的BUG，可以編輯外掛 plugins/js_composer/include/classes/core/access/class-vc-role-access-controller.php 檔案強制修正 can 方法中處理權限的部份。
 **/
function ks_custom_post_type_support_vc($support, $type) {
	$allow_post_type = array('post', 'page', 'content_block');
	return in_array($type, $allow_post_type);
}
add_filter('vc_is_valid_post_type_be', 'ks_custom_post_type_support_vc', 999, 2);

//如果使用CF7，5.1版後都會因為使用reCaptcha導致每頁都會顯示徽章，使用這方法避免
function remove_recaptcha_badge() {
	echo '<style>.grecaptcha-badge{ visibility: collapse !important; }</style>';
}
add_action('wp_footer', 'remove_recaptcha_badge');

//補上客製化檔案格式支援
function mxp_custom_mime_types($mime_types) {
	$mime_types['zip'] = 'application/zip';
	$mime_types['rar'] = 'application/x-rar-compressed';
	$mime_types['tar'] = 'application/x-tar';
	$mime_types['gz'] = 'application/x-gzip';
	$mime_types['gzip'] = 'application/x-gzip';
	$mime_types['tiff'] = 'image/tiff';
	$mime_types['tif'] = 'image/tiff';
	$mime_types['bmp'] = 'image/bmp';
	$mime_types['svg'] = 'image/svg+xml';
	$mime_types['psd'] = 'image/vnd.adobe.photoshop';
	$mime_types['ai'] = 'application/postscript';
	$mime_types['indd'] = 'application/x-indesign';
	$mime_types['eps'] = 'application/postscript';
	$mime_types['rtf'] = 'application/rtf';
	$mime_types['txt'] = 'text/plain';
	$mime_types['wav'] = 'audio/x-wav';
	$mime_types['csv'] = 'text/csv';
	$mime_types['xml'] = 'application/xml';
	$mime_types['flv'] = 'video/x-flv';
	$mime_types['swf'] = 'application/x-shockwave-flash';
	$mime_types['vcf'] = 'text/x-vcard';
	$mime_types['html'] = 'text/html';
	$mime_types['htm'] = 'text/html';
	$mime_types['css'] = 'text/css';
	$mime_types['js'] = 'application/javascript';
	$mime_types['ico'] = 'image/x-icon';
	$mime_types['otf'] = 'application/x-font-otf';
	$mime_types['ttf'] = 'application/x-font-ttf';
	$mime_types['woff'] = 'application/x-font-woff';
	$mime_types['ics'] = 'text/calendar';
	return $mime_types;
}
add_filter('upload_mimes', 'mxp_custom_mime_types', 1, 1);

//降低使用 WP Rocket 外掛使用權限，讓編輯以上的角色可以操作
function mxp_accept_cap_to_use_rocket($cap) {
	return 'edit_pages';
}
add_filter('rocket_capacity', 'mxp_accept_cap_to_use_rocket', 11, 1);

//預設關閉 XML_RPC
add_filter('xmlrpc_enabled', '__return_false');

function add_privacy_page_edit_cap($caps, $cap, $user_id, $args) {
	if ('manage_privacy_options' === $cap) {
		$manage_name = is_multisite() ? 'manage_network' : 'manage_options';
		$caps = array_diff($caps, [$manage_name]);
	}
	return $caps;
}
add_filter('map_meta_cap', 'add_privacy_page_edit_cap', 10, 4);