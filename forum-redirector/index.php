<?php
// This script will redirect everything in this folder to the new location.
// NOTE: This script also has an accompaning file called .htaccess.
// This file is hidden on a lot of systems so you may need to look at your view preferences to see it.
// Don't miss the .htaccess file!

// 1. Set the location of your new community here.

$target = 'http://vanilla.local';

// 2. Put this script and the .htaccess file into a folder and give it a test.
//    I recommend you make a new folder for the file while testing. You don't need to move it until you've launched.
//    Let's say you put it in a folder that can be accessed from http://yourdomain.com/forumtest
//    You want to go to your web browser and try out some addresses like: http://yourdomain.com/forumtest/test1, http://yourdomain.com/forumtest/test1/test2, http://yourdomain.com/forumtest/test1/test3?foo=bar

// 3. The script does its thing here. You don't have to do anything if the script works.

$p = $_GET['_p'];
unset($_GET['_p']);

$url = rtrim($target, '/').'/'.ltrim($p, '/');
if (count($_GET) > 0)
	$url .= '?'.http_build_query($_GET);

//header("Location: $url", true, 301);

// 4. Did this script not work? Are you sure you copied the .htaccess file?
//    a) Comment out the line above that starts with "header".
//    b) Uncomment the code below.
//    c) Browse to your pages in step 2, and look at some of the debug output.

/*
header("Content-Type: text/plain");

echo
"Here's the path we read: $p
Here's the url we think we should redirect to: $url

Here's some other information that may help. Have a look at the information provided to this page and see if there's any data there that will help you redirect correctly.

";

echo "SERVER: ".print_r($_SERVER, true);
*/