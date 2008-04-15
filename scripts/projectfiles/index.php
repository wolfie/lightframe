<?php
define('LF_LIGHTFRAME_PATH',''); // The path to where LightFrame is installed
define('LF_PROJECT_PATH',''); // The path where your project files are



// DO NOT TOUCH ANYTHING BELOW THIS LINE //
// UNLESS YOU REALLY, REALLY KNOW WHAT YOU'RE DOING //

if (LF_LIGHTFRAME_PATH === '' || LF_PROJECT_PATH === '') {
	die('<html><body><h1>Error</h1><p>You have not configured your project.</p></body></html>');
}

if (!is_file(LF_LIGHTFRAME_PATH.'LightFrame.php')) {
	echo '<html><body><h1>Error</h1>';
	echo '<p>LightFrame.php was not found, or not readable at '.LF_LIGHTFRAME_PATH;
	echo '</p></body></html>';
	die();
}

require_once LF_LIGHTFRAME_PATH.'LightFrame.php';
?>
