<?php

// get instagram IDs from page id

require_once('api/apiconfig.php') ;
require_once('api/apifunctions.php') ;

use Instagram\Page\Page;

require('vendor/autoload.php') ;

$blufDB = init_database('live') ;
$keyQ = $blufDB->query("SELECT vartext AS token FROM systemConfig WHERE varname = 'igtoken'") ;
$key = $keyQ->fetch_assoc() ;


$config = array( // instantiation config params
	'page_id' => '<FACEBOOK_PAGE_ID>', // update this with your facebook page id, found using the insta_pages.php script
	'access_token' => $key['token'],
);

// instantiate page
$page = new Page($config);

// get info
$pageInfo = $page->getSelf();

print_r($pageInfo) ;
