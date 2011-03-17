<?php
include "../../../wp-config.php";

if(!class_exists('NeOAuth')){
	include dirname(__FILE__).'/163OAuth.php';
}

$to = new NeOAuth($ne_consumer_key, $ne_consumer_secret);

	
$tok = $to->getRequestToken(get_option('home'));

$_SESSION["ne_oauth_token_secret"] = $tok['oauth_token_secret'];
if($_GET['callback_url']){
	$callback_url = $_GET['callback_url'];
}else{
	$callback_url = get_option('home');
}
$request_link = $to->getAuthorizeURL($tok['oauth_token'],true,$callback_url);

header('Location:'.$request_link);
?>
