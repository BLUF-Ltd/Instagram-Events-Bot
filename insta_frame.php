<?php

// BLUF event pic scaler for Instagram - fit images in a square frame

// Version 1.0
// Date: 2023-07-06

if (!isset($_REQUEST['image'])) {
	exit ;
}

// this is the path to where all our event photos are
// the image param is set by the Apache Redirect
//
// # For autoscaling event images to insta
// RewriteRule ^/photos/eventsq/(.*) /utils/insta_frame.php?image=$1 [L]
//
$path = '/var/bluf/site/httpdocs/photos/events/' . $_REQUEST['image'] ;

$size = getimagesize($path) ;

if ($size[0] == $size[1]) {
	if ($size['mime'] == 'image/png') {
		header('Content-Type: image/png') ;
	} else {
		header('Content-Type: image/jpeg') ;
	}
	readfile($path) ;
} else {
	if ($size['mime'] == 'image/png') {
		$orig = imagecreatefrompng($path) ;
	} else {
		$orig = imagecreatefromjpeg($path) ;
	}

	if ($size[0] > $size[1]) {
		$offset = ($size[0] - $size[1])/2 ;
		$new = imagecreatetruecolor($size[0], $size[0]) ;
		imagecopy($new, $orig, 0, $offset, 0, 0, $size[0], $size[1]) ;
	} else {
		$offset = ($size[1] - $size[0])/2 ;
		$new = imagecreatetruecolor($size[1], $size[1]) ;
		imagecopy($new, $orig, $offset, 0, 0, 0, $size[0], $size[1]) ;
	}
}

if ($size['mime'] == 'image/png') {
	header("Content-Type: image/png") ;
	imagepng($new, null) ;
} else {
	header("Content-Type: image/jpeg") ;
	imagejpeg($new, null) ;
}
