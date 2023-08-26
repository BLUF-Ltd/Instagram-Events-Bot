<?php

// get instagram user pages from access token


require_once('api/apiconfig.php') ;
require_once('api/apifunctions.php') ;

use Instagram\User\User;

require('vendor/autoload.php') ;

$blufDB = init_database('live') ;
$keyQ = $blufDB->query("SELECT vartext AS token FROM systemConfig WHERE varname = 'igtoken'") ;
$key = $keyQ->fetch_assoc() ;


$config = array( // instantiation config params
	'access_token' => $key['token'],
);

// instantiate and get the users info
$user = new User($config);

// get the users pages
$pages = $user->getUserPages();

print_r($pages) ;
