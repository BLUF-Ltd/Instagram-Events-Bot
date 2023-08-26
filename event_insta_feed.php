<?php


// Insta calendar aggregator
// Posts to Instagram with the details of events happening tomorrow, from the BLUF calendar
//
// Version 2.0
// Date: 2023-07-09
// Refactored to provide same options as the telegram and fediverse feeds
// Version 1.1
// Date: 2023-07-07
// Add --weekly flag for a weekly summary, randomize text
// Version 1.0
// Date: 2023-07-06

require_once('api/apiconfig.php') ;
require_once('api/apifunctions.php') ;
require_once('core/BLUFclasses.php') ;

// This is where we define our instagram user id
// It's a tremendously tedious (because it's Meta) faff to actually get this
// Your insta account needs to be associated with a page you manage
//
// 	1. create a facebook app
// 	2. get an OAuth token
// 	3. swap it for a long lived token
//	4. use the token to get a list of your pages,
// 	5. find the appropriate page, and use that to get the page info
//	6. buried in that, you'll find the instagram user id
//
require_once('KEYSinstagram.php') ;

use Instagram\User\Media;
use Instagram\User\MediaPublish;

require_once('vendor/autoload.php') ;

$blufDB = init_database('live') ;
$cache = new \BLUF\Cache\connection('live') ;

// Because (sigh...) Instagram / Meta uses OAuth, we need to perform the tedious dance from
// time to time to get the sodding token, which we store in our database
// You'll need some method (manual or automatic) to keep this up to date
$keyQ = $blufDB->query("SELECT vartext AS token FROM systemConfig WHERE varname = 'igtoken'") ;
$key = $keyQ->fetch_assoc() ;

$config = array( // instantiation config params
	'user_id' => IG_USERID,
	'access_token' =>  $key['token'],
);


date_default_timezone_set('UTC') ;
setlocale(LC_ALL, 'en_EN.UTF8') ;

// Vary the texts a little
$tomorrow = array( "Here's your round up of events in the BLUF calendar starting tomorrow, ",
"Not sure where to go? Check out our list of events happening tomorrow, ",
"Gear up and get out! Here's what's happening tomorrow, ",
"Support our kinky spaces - why not check out one of these events tomorrow, ") ;

$thisweek = array( "Check out what's happening this week in the BLUF Calendar",
"Here's your summary of what's happening this week in the BLUF Calendar",
"Made your weekend plans yet? Here's a roundup of what's happening this week",
"The BLUF Calendar is packed with events. Here's what's happening this week") ;

// set up the posting mode
$options = getopt('', array('mode:','test'), $rest) ;
$text = trim(implode(' ', array_slice($argv, $rest)));

// connect to our BLUF data sources
$blufDB = init_database('live') ;
$cache = new \BLUF\Cache\connection('live') ;

switch ($options['mode']) {

	case 'weekly':
		// a single post, for This week
		$tQ = $blufDB->query("SELECT * FROM events WHERE private = 'n' AND cancelled = 'n' AND startdate >= CURRENT_DATE() AND startdate < DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY) ORDER BY startdate ASC") ;
		if ($tQ->num_rows > 0) {
			$image = 'https://bluf.com/utils/instapic.php?count=' . $tQ->num_rows ; // a banner image with a counter

			$text = $thisweek[rand(0, 3)] . "\n\n" ;

			$when = '' ;

			while ($event = $tQ->fetch_assoc()) {
				$event_when = IntlDateFormatter::formatObject(new DateTime($event['startdate']), 'EEEE d LLLL') ;

				if ($when != $event_when) {
					$text .= "\n\n" .$event_when . "\n\n" ;
					$when = $event_when ;
				}

				$where = (strlen($event['location']) == 0) ? $event['city'] : $event['location'] ;
				$text .= $event['title'] . ', ' . $where . "\n\n" ;
			}

			$text .= "\n\nCheck out full details at bluf.com/e/thisweek" ;
		}

		if (isset($options['test'])) {
			print_r($text) ;
		} else {
			insta_post_single($config, $image, $text) ;
		}


		break ;

	case 'daily':

		// get tomorrow's events in the calendar, group all the images on a new post
		$tQ = $blufDB->query("SELECT id FROM events WHERE startdate = DATE_ADD(CURRENT_DATE(), INTERVAL +1 DAY) AND private = 'n' AND cancelled = 'n'") ;

		$when = strftime('%A, %e %B', time()+86400) ;

		$text = $tomorrow[rand(0, 3)] . $when . "\n\n" ;

		$images = array() ;

		if ($tQ->num_rows > 0) {
			while ($e = $tQ->fetch_assoc()) {
				$event = new \BLUF\Calendar\event($e['id']) ;

				$when = IntlDateFormatter::formatObject(new DateTime($event->startdate), 'EEEE d LLLL') ;

				$where = (strlen($event->venue) == 0) ? $event->city : $event->venue ;

				if ($event->poster != null) {
					// image urls ending in sx are autoscaled; those ending in sq have padding added to make them square,
					// which avoids Instagram cutting things off if the ratio is too out of whack
					$images[] = preg_replace('#eventsx/#', 'eventsq/', $event->poster) ;
				}
				$text .= $event->name . ', ' . $where . "\n\n" ;
			}
			$text .= "\n\nCheck out full details at bluf.com/events" ;
			if (count($images) == 1) {
				insta_post_single($config, $images[0], $text) ;
			} elseif (count($images) > 1) {
				insta_post_multiple($config, $images, $text) ;
			} else {
				print("No images found ... skipping\n") ;
			}
		} else {
			print("No events found... quitting\n") ;
			exit ;
		}


		break ;

	case 'new':
		// get events added to the calendar today, and do a post for each one
		$eventSQL = "SELECT id FROM events, eventClassification WHERE eventid = id AND classification != 'unclassified' AND classifiedtime > DATE_ADD(NOW(), INTERVAL -1 DAY) AND private = 'n' AND cancelled = 'n' AND creator > 0" ;

		$events = $blufDB->query($eventSQL) ;

		while ($e = $events->fetch_assoc()) {
			// See our Telegram bot for a full explanation of our BLUF event object
			// You need to get name, time, place and details from your database
			$event = new \BLUF\Calendar\event($e['id']) ;

			if ($event->poster == null) {
				printf("Skipping %s - no image\n", $event->name) ;
			} else {
				$image = preg_replace('#eventsx/#', 'eventsq/', $event->poster) ;

				$post_text = sprintf("New in the BLUF calendar: %s\n\n", $event->name) ;

				// this code handles our multilingual texts (see our Telegram bot for a fuller description)
				// end result is the english version, parsed to plain text
				$desc = new \BLUF\Text\multilingual($event->long_description) ;

				if (trim($desc->default) == '') {
					$description = $desc->lang_en ;
				} else {
					$description = $desc->default ;
				}
				$parser = new \BLUF\Text\parser($description) ;

				$when = IntlDateFormatter::formatObject(new DateTime($event->startdate), 'EEEE d LLLL') ;

				$where = (strlen($event->venue) == 0) ? $event->city : $event->venue ;

				$post_text .= $when . "\n\n" . $parser->PlainText() . "\n\n" . $where ;

				$post_text .= "\n\nSee more on bluf.com/e/" . $event->id . "\n" ;

				insta_post_single($config, $image, $post_text) ;
				sleep(rand(5, 20)) ; // avoid flooding
			}
		}
		break ;

	}


// These functions do the real work
// First, this is for a post with a single image
function insta_post_single($config_array, $image, $caption)
{
	$media = new Media($config_array);

	$imageContainerParams = array( // container parameters for the image post
		'caption' => $caption, // caption for the post
		'image_url' => $image, // url to the image must be on a public server
	);

	// create image container
	$imageContainer = $media->create($imageContainerParams);

	// get id of the image container
	$imageContainerId = $imageContainer['id'];


	// instantiate media publish
	$mediaPublish = new MediaPublish($config_array);

	// post our container with its contents to instagram
	$publishedPost = $mediaPublish->create($imageContainerId);
}

// This posts a 'carousel' of multiple images - there's a limit of
// ten per post.
function insta_post_multiple($config_array, $images, $caption)
{
	$media = new Media($config_array);

	$c = 0 ;
	$containerIDs = array() ;
	while (($c < 10) && ($c < count($images))) {
		$imageContainerParams = array( // container parameters for the image post
			'image_url' => $images[$c], // url to the image must be on a public server
			'is_carousel_item' => true, // is this in a carousel
		);

		// create image container
		$imageContainer = $media->create($imageContainerParams);

		// get id of the image container
		$containerIDs[] = $imageContainer['id'];

		$c++ ;
	}


	$carouselContainerParams = array( // container parameters for the carousel post
		'caption' => $caption, // caption for the post
		'children' => $containerIDs
	);

	$carouselContainer = $media->create($carouselContainerParams);

	// get id of the image container
	$carouselContainerId = $carouselContainer['id'];

	// instantiate media publish
	$mediaPublish = new MediaPublish($config_array);

	// post our container with its contents to instagram
	$publishedPost = $mediaPublish->create($carouselContainerId) ;
}
