# BLUF events Instagram bot

_August 2023_

This document describes how we built the BLUF Instagram bot, which posts automated announcements of events 
to our BLUFhq account.

## Getting started
There are some pre-requisites for making this work; it may be possible to make it work in other ways, but this
is the way we did it.

1. You need a Page on Facebook that's linked to your account
2. You need an Instagram Business account that's associated with that page (you can probably get this set up in the Meta business suite)

Then you have to find out the Instagram user id of the account you want to post as. This being Meta, it involves jumping through
hoops; there's nothing as straightforward as simply tapping an icon somewhere to see the id.


## Setting up your environment
To handle interaction with the Instagram api, we're using the [Instagram graph api php SDK library](https://github.com/jstolpe/instagram-graph-api-php-sdk).
You'll need to add this to your system using composer:

		composer require jstolpe/instagram-graph-api-php-sdk
		
You will also need somewhere to store the Instagram user ID, and your access token. Since the former doesn't change, while the latter
may expire every 60 days or so, we have defined the user ID in a KEYSinstagram.php file that's included in our scripts, while the 
token is stored in the database. The KEYS file looks something like this by the time we've finished.

		<?php
		
		// keys for Instagram Graph API
		//
		define('IG_APPID','get_this_from_facebook') ;
		define('IG_APPSECRET','and_this_too') ;
		define('IG_REDIRECT','https://somewhere.on.your.server/path/insta_link.php') ;
		define('IG_USERID','') ; 

Your first step is creating an app on the Facebook developer's site, which will furnish you with the app ID and app secret, which you can
then save as the first two items in this file (or somewhere else safe).

Once you have those, you can then use them to let a user log in with Facebook, to retrieve an Access Token. That will initially be a
'short lived' one, so you can swap it for a longer one. Save that somewhere, either in a file or - as we do - in the database. You can then
use a simple script to update it from time to time.

Included in this repository are two scripts, [insta_login.php](insta_login.php) and [insta_link.php](insta_link.php). The former simply presents
a Login with Facebook button, which will request the necessary permissions, and the latter is the script you'll be redirected back to 
which, in our case, saves the token in the database. These need to be put somewhere you can run them from the web browser, because of the redirects.
The other scripts can be run from the command line, if you prefer.

Now it's time for the hoop-jumping. You need to take the access token you stored, and you can then use that to request a list of the user's
Facebook pages. There may, of course, be several, and the script that we've provided at [insta_pages.php](insta_pages.php) will list them.

_These scripts are based on the examples you can find in the [Wiki](https://github.com/jstolpe/instagram-graph-api-php-sdk/wiki) for the library we're using; many thanks to the creator. You may find, as
I did, that his YouTube videos help make this whole process much clearer._

		php8.2 insta_pages.php

When you run the insta_pages script, you'll see a list of pages, and you need to find the one that's linked to the Instagram account that
you want to access. Scroll through, and make a note of the 'id' field for that page. You'll then need to use that id in the 
[insta_page_info.php](insta_page_info.php) script, which will reveal the id of the linked Instagram business account.

		php8.2 insta_page_info.php

Now, you finally have the id that you need, which can be added to your keys file, as the IG_USERID value, if you're storing things the same
way we are. This is the ID that's used in the main script that posts to Instagram.

There are some other files you'll see included in the scripts, which are mostly related to our BLUF events object, and
the setting up of a connection to our database. Rather than repeat ourselves, you can find information on that in the 
[README for our Telegram bot](https://github.com/BLUF-Ltd/Telegram-Events-Bot).


## The main Instagram feed
The main part of this project is the event_insta_feed.php file. This is a script that's designed to be run at intervals, for example
from cron. It takes at least one parameter, which is the mode option, eg 

		php8.2 event_insta_feed.php --mode=daily

This script has fewer options than our Telegram one. One of the considerations was that we don't want to post absolutely identical
images as single posts too often. It'll look a bit spammy, and won't catch the attention of our followers as much. We also have a
selection of different text intros for the various types of post, again to avoid the impression of looking too spammy.

Here's a guide to the different modes:

### daily
In this mode, the bot will create a post with one of its four introductory messages and a carousel of up to ten images (if there's more than one)
for the events starting the next day. The body of the post will have the names and locations of the events.

### weekly
We run this on a Tuesday, and it posts an single image saying how many events there are coming up this week, with a text caption that
takes one of the four introductory messages, and then lists the dates, names and locations of all the events coming up this week. The image
is automatically created by the [instapic.php](instapic.php) script, which combines a random choice of background image with the number of
upcoming events.

### new
This runs daily, and if there are any events that have been newly classified, it will post the full description of the event prefixed by 
"New in the BLUF Calendar" for any event that has an image available. Each event gets a separate post.

The overall result is that
+ an event gets a post of its own on they day it's added to the calendar, as long as it has an image
+ on the week it occurs, its date, name and location are included in the weekly roundup post
+ on the day before it occurs, its date, name and location are including, with the image if available, in a post featuring all the event for that date 

## The insta_frame script
One additional tool included in this repository is the [insta_frame.php](insta_frame.php) script, which is included for the sake of completeness.
In our setup, it's usually called by an Apache redirect, so all references to event poster images that have eventsq in the path are passed
to this script, which places them in a square frame, if they are not already square. This is because sometimes, depending on the proportions,
the Instagram algorithms can miss off parts of an image that's not square. So we simply copy it into the centre of a square image with the
same dimensions as the longest side. Obviously this could be tweaked, by performing other scaling, or cacheing the results, but it'll do for our
purposes.

The redirect in our host configuration file is like this

	# For autoscaling event images to insta
	RewriteRule ^/photos/eventsq/(.*) /utils/insta_frame.php?image=$1 [L]   


### Conclusion
I hope this has given you some inspiration; obviously, you'll have to amend some things to deal with how your database is set up, and what
information you store about events. A couple of caveats: I can't provide help integrating this in to your own system, and if your may be dealing with
very large images that you want to post to Instagram, do check out the Wiki for the SDK; if things are going to take some time to upload, you do
really need to check that they have been successfully added to your Instagram containers in those post functions, before you actually send the post.
And you absolutely must do that if you're using video. The Wiki and the You Tube videos explain this pretty well.
