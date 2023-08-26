<?php

// code to link to Instagram authentication and request permissions

require('KEYSinstagram.php') ;
require('vendor/autoload.php') ;

use Instagram\FacebookLogin\FacebookLogin;

$config = array( // instantiation config params
	'app_id' => IG_APPID, // facebook app id
	'app_secret' => IG_APPSECRET, // facebook app secret
);

// uri facebook will send the user to after they login
$redirectUri = IG_REDIRECT;

$permissions = array( // permissions to request from the user
	'instagram_basic',
	'instagram_content_publish',
	'instagram_manage_insights',
	'instagram_manage_comments',
	'pages_show_list',
	'ads_management',
	'business_management',
	'pages_read_engagement'
);

// instantiate new facebook login
$facebookLogin = new FacebookLogin($config);

// display login dialog link
echo '<a href="' . $facebookLogin->getLoginDialogUrl($redirectUri, $permissions) . '">' .
	'Log in with Facebook' .
'</a>';
