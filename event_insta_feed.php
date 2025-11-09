<?php


// Insta calendar aggregator
// Posts to Instagram with the details of events happening tomorrow, from the BLUF calendar
//

// Version 2.4
// Add check for container status, due to Graph API weirdness
// Version 2.3
// Date: 2025-11-04
// More tweaks to avoid issue with robots.txt
// Version 2.2
// Date 2025-09-12
// Update to use event_collection object, so no SQL needed
// Version 2.1
// Date: 2023-12-19
// More graceful behaviour when no events found in weekly mode
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
require_once('vendor/autoload.php') ;
require_once('KEYSinstagram.php') ;


use Instagram\User\Media;
use Instagram\User\MediaPublish;
use Instagram\Container\Container ;

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
		$events = new \BLUF\Calendar\event_collection('thisweek') ;

		//$tQ = $blufDB->query("SELECT * FROM events WHERE private = 'n' AND cancelled = 'n' AND startdate >= CURRENT_DATE() AND startdate < DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY) ORDER BY startdate ASC") ;
		if ($events->countPublic() > 0) {
			$image = 'https://bluf.com/utils/instapic.php?count=' . $events->countPublic() ; // a banner image with a counter

			$text = $thisweek[rand(0, 3)] . "\n\n" ;

			$when = '' ;

			foreach ($events->getPublic() as $id) {
				$event = new \BLUF\Calendar\event($id) ;

				$event_when = IntlDateFormatter::formatObject(new DateTime($event->startdate), 'EEEE d LLLL') ;

				if ($when != $event_when) {
					$text .= "\n\n" .$event_when . "\n\n" ;
					$when = $event_when ;
				}

				$where = (strlen($event->venue) == 0) ? $event->city : $event->venue ;
				$text .= $event->name . ', ' . $where . "\n\n" ;
			}

			$text .= "\n\nCheck out full details at bluf.com/e/thisweek" ;

			if (isset($options['test'])) {
				print_r($text) ;
			} else {
				insta_post_single($config, $image, $text) ;
			}
		} else {
			print("No events found\n") ;
		}

		break ;

	case 'daily':

		// get tomorrow's events in the calendar, group all the images on a new post
		$events = new \BLUF\Calendar\event_collection('tomorrow') ;

		$when = strftime('%A, %e %B', time()+86400) ;

		$text = $tomorrow[rand(0, 3)] . $when . "\n\n" ;

		$images = array() ;

		if ($events->countPublic() > 0) {
			foreach ($events->getPublic() as $id) {
				$event = new \BLUF\Calendar\event($id) ;


				$when = IntlDateFormatter::formatObject(new DateTime($event->startdate), 'EEEE d LLLL') ;

				$where = (strlen($event->venue) == 0) ? $event->city : $event->venue ;

				if ($event->poster != null) {
					// image urls ending in sx are autoscaled; those ending in sq have padding added to make them square,
					// which avoids Instagram cutting things off if the ratio is too out of whack
					//$images[] = preg_replace('#eventsx/#', 'eventsq/', $event->poster) ;
					$img = 'http://ip.bluf.com/' . $event->id . '/' . basename($event->poster) ;
					$images[] = $img ;
					printf("Working on event %d - %s\n", $id, $img) ;
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
		$events = new \BLUF\Calendar\event_collection('new') ;


		foreach ($events->getPublic() as $id) {
			// See our Telegram bot for a full explanation of our BLUF event object
			// You need to get name, time, place and details from your database
			$event = new \BLUF\Calendar\event($id) ;

			if ($event->poster == null) {
				printf("Skipping %s - no image\n", $event->name) ;
			} else {
				//$image = preg_replace('#eventsx/#', 'eventsq/', $event->poster) ;
				$image = 'http://ip.bluf.com/' . $event->id . '/' . basename($event->poster) ;

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
	global $key ;
	$media = new Media($config_array);

	$imageContainerParams = array( // container parameters for the image post
		'caption' => $caption, // caption for the post
		'image_url' => $image, // url to the image must be on a public server
	);

	// create image container
	$imageContainer = $media->create($imageContainerParams);

	if (!isset($imageContainer['id'])) {
		// something went wrong
		printf("Error building container for %s with caption %s", $image, $caption) ;
		return ;
	}

	// get id of the image container
	$imageContainerId = $imageContainer['id'];

	// check the status of the container
	// This is necessary because Instagram now takes time to process all posts
	// Not just reels.
	$container_config = array(
		'container_id' => $imageContainerId,
		'access_token' =>  $key['token']
	) ;
	$c = new Container($container_config) ;

	$c_status = '' ;

	while ($c_status != 'FINISHED') {
		$container_info = $c->getSelf() ;
		$c_status = $container_info['status_code'] ;
		print("$c_status\n") ;
		sleep(2) ;
	}

	if ('FINISHED' == $container_info['status_code']) {
		// instantiate media publish
		$mediaPublish = new MediaPublish($config_array);

		// post our container with its contents to instagram
		$publishedPost = $mediaPublish->create($imageContainerId);
	}
}

// This posts a 'carousel' of multiple images - there's a limit of
// ten per post.
function insta_post_multiple($config_array, $images, $caption)
{
	global $key ;
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


		// report errors
		if (!isset($imageContainer['id'])) {
			mail(ERROR_EMAIL, 'Failed to build insta container', 'URL: ' . $images[$c]) ;
		}

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

	// check the status of the container
	// This is necessary because Instagram now takes time to process all posts
	// Not just reels.

	$container_config = array(
		'container_id' => $carouselContainerId,
		'access_token' =>  $key['token']
	) ;
	$c = new Container($container_config) ;

	$c_status = '' ;

	while ($c_status != 'FINISHED') {
		$container_info = $c->getSelf() ;
		$c_status = $container_info['status_code'] ;
		print("$c_status\n") ;
		sleep(2) ;
	}


	// instantiate media publish
	if ('FINISHED' == $container_info['status_code']) {
		$mediaPublish = new MediaPublish($config_array);

		// post our container with its contents to instagram
		$publishedPost = $mediaPublish->create($carouselContainerId) ;
	}
}
