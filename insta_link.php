<?php

// Insta / FB OAuth callback

require('KEYSinstagram.php') ;
require('vendor/autoload.php') ;
require_once('api/apiconfig.php') ;
require_once('api/apifunctions.php') ;

use Instagram\AccessToken\AccessToken;

$config = array( // instantiation config params
	'app_id' => IG_APPID, // facebook app id
	'app_secret' => IG_APPSECRET, // facebook app secret
);

// we also need to specify the redirect uri in order to exchange our code for a token
// this points to our instalink.php script
$redirectUri = IG_REDIRECT;

// instantiate our access token class
$accessToken = new AccessToken($config);

// exchange our code for an access token
$newToken = $accessToken->getAccessTokenFromCode($_GET['code'], $redirectUri);

if (!$accessToken->isLongLived()) { // check if our access token is short lived (expires in hours)
	// exchange the short lived token for a long lived token which last about 60 days
	$newToken = $accessToken->getLongLivedAccessToken($newToken['access_token']);
}

$blufDB = init_database('live') ;

$store = $blufDB->stmt_init() ;

$store->prepare("REPLACE INTO systemConfig SET varname = 'igtoken', vartext = ?, enableui = 'n'") ;
$store->bind_param('s', $newToken['access_token']) ;

mail(ADMIN_EMAIL, 'ig token', print_r($newToken, true)) ;
