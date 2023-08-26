<?php

// instapic image creator for BLUF
// Creates an image for instagram with the number of events this week
// in big type, in the centre
// the count url param specifies the number, eg
// 	https://bluf.com/utils/instapic.php?count=17
// if omitted, we pop in a random number

require_once('common/directories.php') ;

$pick = rand(0, 4) ;

// These are our backgrounds; square images with a selection of different
// backgrounds, plus the unchanging text for each image
// Put them in a common directory - it doesn't have to be accessible to the
// web server

$source = '/var/bluf/site/httpdocs/images/thisweek' . $pick . '.png' ;

$count = (isset($_REQUEST['count'])) ? $_REQUEST['count'] : rand(2, 23) ;

$badge = imagecreatefrompng($source) ;

// This is our font. We like it, but there are many other fine ones too
$font = $DIRfonts . 'ChunkFive-Roman.ttf' ;

// This calculation scales to take account of the fact our background images
// were created at 144dpi, while PHP expects 96dpi.
$fontsize = 288  * 144 / 96;

$black = imagecolorallocate($badge, 0, 0, 0) ;

// find out the width of the text box, so we can position it centrally
$bbox = imagettfbbox($fontsize, 0, $font, $count) ;
$width = abs($bbox[2] - $bbox[0]);


$xpos = (imagesx($badge) - $width)/2 ;

imagettftext($badge, $fontsize, 0, $xpos, 700, $black, $font, $count) ;

header('Content-Type: image/png') ;

imagepng($badge, null) ;
