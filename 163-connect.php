<?php
/*
Plugin Name: 网易连接
Author:  yAnGmU
Author URI: http://yang.mu
Plugin URI: http://appletemple.com/163-connect.html
Description: 使用网易微博账号登陆 WordPress 博客，并且留言使用网易微博的头像。
Version: 1.0
*/
$ne_consumer_key = 'UwtDMZgejeo01o8N';
$ne_consumer_secret = 'dwO0fNDCNRO74LtWfN7hq9TOnXjNzlJQ';
$ne_loaded = false;

add_action('init', 'ne_init');
function ne_init(){
	if (session_id() == "") {
		session_start();
	}
	if(!is_user_logged_in()) {		
        if(isset($_GET['oauth_token'])){
			ne_confirm();
        } 
    } 
}

add_action("wp_head", "ne_wp_head");
function ne_wp_head(){
    if(is_user_logged_in()) {
        if(isset($_GET['oauth_token'])){
			echo '<script type="text/javascript">window.opener.ne_reload("");window.close();</script>';
        }
	}
}

add_action('comment_form', 'ne_connect');
function ne_connect($id=""){
	global $ne_loaded;
	if($ne_loaded) {
		return;
	}
	
	if(is_user_logged_in()){
		global $user_ID;
		$nedata = get_user_meta($user_ID, 'nedata',true);
		
		if($nedata){	
		}
		return;
	}

	$ne_url = WP_PLUGIN_URL.'/'.dirname(plugin_basename (__FILE__));
	
?>
	<script type="text/javascript">
    function ne_reload(){
       var url=location.href;
       var temp = url.split("#");
       url = temp[0];
       url += "#ne_button";
       location.href = url;
       location.reload();
    }
    </script>	
	<style type="text/css"> 
	.ne_button img{ border:none;}
    </style>
	<p id="ne_connect" class="ne_button">
	<img onclick='window.open("<?php echo $ne_url; ?>/163-start.php", "dcWindow","width=800,height=600,left=150,top=100,scrollbar=no,resize=no");return false;' src="<?php echo $ne_url; ?>/163_button.png" alt="用网易微博登陆" style="cursor: pointer; margin-right: 20px;" />
	</p>
<?php
    $ne_loaded = true;
}

add_filter("get_avatar", "ne_get_avatar",10,4);
function ne_get_avatar($avatar, $id_or_email='',$size='32') {
	global $comment;
	if(is_object($comment)) {
		$id_or_email = $comment->user_id;
	}
	if (is_object($id_or_email)){
		$id_or_email = $id_or_email->user_id;
	}
	if($neavatar = get_usermeta($id_or_email, 'neavatar')){
		$avatar = "<img alt='' src='{$neavatar}' class='avatar avatar-{$size}' height='{$size}' width='{$size}' />";
		return $avatar;
	}else {
		return $avatar;
	}
}

function ne_confirm(){
    global $ne_consumer_key, $ne_consumer_secret;
	
	if(!class_exists('NeOAuth')){
		include dirname(__FILE__).'/163OAuth.php';
	}
	
	$to = new NeOAuth($ne_consumer_key, $ne_consumer_secret, $_GET['oauth_token'],$_SESSION['ne_oauth_token_secret']);
	
	$tok = $to->getAccessToken($_REQUEST['oauth_verifier']);

	$to = new NeOAuth($ne_consumer_key, $ne_consumer_secret, $tok['oauth_token'], $tok['oauth_token_secret']);

	$neInfo = $to->OAuthRequest('http://api.t.163.com/account/verify_credentials.json', 'GET',array());

	if($neInfo == "no auth"){
		echo '<script type="text/javascript">window.close();</script>';
		return;
	}
	
	$neInfo = json_decode($neInfo);
	

	if((string)$neInfo->domain){
		$ne_user_name = $neInfo->domain;
	} else {
		$ne_user_name = $neInfo->id;
	}
		
	ne_login($neInfo->id.'|'.$ne_user_name.'|'.$neInfo->screen_name.'|'.$neInfo->url.'|'.$tok['oauth_token'] .'|'.$tok['oauth_token_secret'].'|'.$neInfo->profile_image_url); 
}

function ne_login($Userinfo) {
	$userinfo = explode('|',$Userinfo);
	if(count($userinfo) < 7) {
		wp_die("An error occurred while trying to contact 163 Connect.");
	}

	$userdata = array(
		'user_pass' => wp_generate_password(),
		'user_login' => $userinfo[1],
		'display_name' => $userinfo[2],
		'user_url' => $userinfo[3],
		'user_email' => $userinfo[1].'@t.163.com'
	);

	if(!function_exists('wp_insert_user')){
		include_once( ABSPATH . WPINC . '/registration.php' );
	} 
  
	$wpuid = get_user_by_login($userinfo[1]);
	
	if(!$wpuid){
		if($userinfo[0]){
			$wpuid = wp_insert_user($userdata);
		
			if($wpuid){
				update_usermeta($wpuid, 'neid', $userinfo[0]);
				update_usermeta($wpuid, 'neavatar', $userinfo[6]);
				$ne_array = array (
					"oauth_access_token" => $userinfo[4],
					"oauth_access_token_secret" => $userinfo[5],
				);
				update_usermeta($wpuid, 'nedata', $ne_array);
			}
		}
	} else {
		update_usermeta($wpuid, 'neid', $userinfo[0]);
		update_usermeta($wpuid, 'neavatar', $userinfo[6]);
		$ne_array = array (
			"oauth_access_token" => $userinfo[4],
			"oauth_access_token_secret" => $userinfo[5],
		);
		update_usermeta($wpuid, 'nedata', $ne_array);
	}
  
	if($wpuid) {
		wp_set_auth_cookie($wpuid, true, false);
		wp_set_current_user($wpuid);
	}
}

function ne_sinauser_to_wpuser($neid) {
  return get_user_by_meta('neid', $neid);
}

if(!function_exists('get_user_by_meta')){

	function get_user_by_meta($meta_key, $meta_value) {
	  global $wpdb;
	  $sql = "SELECT user_id FROM $wpdb->usermeta WHERE meta_key = '%s' AND meta_value = '%s'";
	  return $wpdb->get_var($wpdb->prepare($sql, $meta_key, $meta_value));
	}
	
	function get_user_by_login($user_login) {
	  global $wpdb;
	  $sql = "SELECT ID FROM $wpdb->users WHERE user_login = '%s'";
	  return $wpdb->get_var($wpdb->prepare($sql, $user_login));
	}
}

if(!function_exists('connect_login_form_login')){
	add_action("login_form_login", "connect_login_form_login");
	add_action("login_form_register", "connect_login_form_login");
	function connect_login_form_login(){
		if(is_user_logged_in()){
			$redirect_to = admin_url('profile.php');
			wp_safe_redirect($redirect_to);
		}
	}
}

?>