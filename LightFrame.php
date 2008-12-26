<?php
/*
 * Copyright 2008 LightFrame Team
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * The main file of the LightFrame framework
 * 
 * All the LightFrame project needs to import is this file. Other requirements
 * are handled internally.
 * 
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License v2.0
 * @author Henrik Paul
 */

set_error_handler('_errorHandler');
set_exception_handler('_exceptionHandler');

define ('LF_NAME', 'LightFrame');
define ('LF_VERSION', 'unfinished');

$GLOBALS['found'] = false;
$GLOBALS['context'] = array();
$GLOBALS['view'] = '';
$GLOBALS['app'] = '';
$GLOBALS['viewfunc'] = '';


// get the settings for the current project
require_once LF_PROJECT_PATH.'/settings.php';

// include accessory files
require_once LF_LIGHTFRAME_PATH.'/lib/response/response.php';
require_once LF_LIGHTFRAME_PATH.'/lib/template/template.php';
require_once LF_LIGHTFRAME_PATH.'/lib/exceptions.php';
require_once LF_LIGHTFRAME_PATH.'/lib/model/model.php';

// before checking url matches, fix the variables for GET method

/*
 * Because the URI is the first part of querystring all the way to the first 
 * question mark, we need to reconstruct the querystring and also the get
 * superglobal.
 */
if (LF_APACHE_MODREWRITE === false) {
	// fix $_GET's first element if mod rewrite isn't enabled
	$key = explode('?',key($_GET));
	$value = array_shift($_GET);
	if (isset($key[1]) && $key[1] !== '') {
		$key = $key[1];
		$_GET = array($key => $value) + $_GET; // array_unshift() doesn't like keys
	}
	
	// $_SERVER[QUERY_STRING] contains an unnecessary argument
	$qstring = explode('?', $_SERVER['REQUEST_URI'], 3);
	$GLOBALS['URL'] = $qstring[1];
	$_SERVER['QUERY_STRING'] = (isset($qstring[2]) ? $qstring[2] : '');
}

// entire $_GET is garbled if mod rewrite is enabled, reconstruct it
else {
	$_GET = array();
	$GLOBALS['URL'] = $_SERVER['QUERY_STRING'];
	
	if (strpos($_SERVER['REQUEST_URI'], '?') !== false) {
		list(,$_SERVER['QUERY_STRING']) = explode('?', $_SERVER['REQUEST_URI'],2);
		
		foreach (explode('&',$_SERVER['QUERY_STRING']) as $value) {
			$value = explode('=',$value,2);
			if (isset($value[1])) {
				$_GET[$value[0]] = $value[1];
			}
			elseif ($value[0] !== '') {
				$_GET[$value[0]] = '';
			}
		}
	}
	else {
		$_SERVER['QUERY_STRING'] = '';
	}
}

if (LF_AUTOFILL_SLASH === true) {
	if (substr($GLOBALS['URL'],-1) !== '/') {
		header('Location: '.LF_SITE_PATH.$GLOBALS['URL'].'/'.($_SERVER['QUERY_STRING'] ? '?'.$_SERVER['QUERY_STRING'] : ''));
		die();
	}
}

// Setup env variable
$GLOBALS['env'] = array (
	'site_path' => LF_SITE_PATH,
);

// Handle possible magic_quotes_gpc presence
// TODO: convert this to, or simply add checkSettings() later on...
_unMagic();

// start checking for url matches
require_once LF_PROJECT_PATH.'/urls.php';

// destroy the connection
_cleanUp();
	
	
/**
 * Add an url and its handler
 * 
 * What this actually does is checks if the current URI matches with the argument
 * $url. If it matches, the view function is launched immediately and further 
 * addURL()-calls are not executed. If it doesn't match, it 
 * returns to the urls.php file, and searches for another match.
 *
 * @param string $url
 * @param string $app
 * @param mixed[optional] $args pass arguments to the view
 */
function addURL($url, $view, $args=array()) {
	// $URI is made static, so it remains in memory between recursive function calls (if the URI is forwarded to another urls.php)
	static $URI = null;
	
	if ($GLOBALS['found'] === true)
		return;
	
	if ($URI === null) {
		$URI = $GLOBALS['URL'];
	
		// TODO: should the regexp extensions be put somewhere more permanent? is this too slow?
		// Regular Expression extensions:
		$from = array(); $to = array();
		$from[] = '[:integer:]'; $to[] = '[1-9][0-9]*'; // an integer without zero-padding
		$URI = str_replace($from, $to, $URI);
	}
	
	$hits = preg_match('@'.$url.'@U', $URI, $matches);
	
	// if the url matches
	if ($hits !== 0) {
		
		// if the url is to be delegated further
		if ($args === 'forward') {
			$URI = preg_replace('@'.$url.'@U', '', $URI, 1); // get rid of what already has been matched
			
			$GLOBALS['app'] = (isset($GLOBALS['app']) ? '' : '/') . $view;
			require_once LF_APPS_PATH.'/'.$view.'/urls.php';
		}
		
		// otherwise activate a view
		else {
			// TODO: Write in the docs that variables from urls are overwritten by the variables in the context argument
			unset($matches[0]); // skip the whole string
			if (isset($args['context'])) {
				$args['context'] = array_merge($args['context'], $matches); // merge enumerated matches with associative arrays.
			}
			else {
				$args['context'] = $matches;
			}
			$GLOBALS['found'] = true;
			echo _callView($view,$args);
			die();
		}
	}
}

/**
 * Things to be done as the last steps.
 */
function _cleanUp() {
//	var_dump($_GET, count($_GET));die();
	if ($GLOBALS['found'] === false) {
//		$uri = $_SERVER['REQUEST_URI'];
		
//		if (true && $uri{(strlen($uri)-1)} !== '/' && count($_GET) === 0) {
//			die($uri);
//		}
		echo _callView('http/http404');
	}
	die();
}

/**
 * Call a view
 *
 * @param string $view "<app>/<view>"
 * @param array[optional] $args arguments to the view
 * @return string Response
 */
function _callView($view, $args=array()) {
	$filePath = '';

	// split the view into the path to the app and the function name within views.php
	$view = explode('/',$view);
	$GLOBALS['viewfunc'] = array_pop($view);
	if (count($view) > 0) {
		$GLOBALS['app'] = implode('/',$view);
	}
	$GLOBALS['view'] = $GLOBALS['app'].'/'.$GLOBALS['viewfunc'];

	// handle non-existing/non-accessible files
	if (!is_readable(LF_APPS_PATH.'/'.$GLOBALS['app'].'/views.php') || 
		!is_file(LF_APPS_PATH.'/'.$GLOBALS['app'].'/views.php')) {
	
		// check if the view would exist/be readable in the LightFrame path
		if (!is_readable(LF_LIGHTFRAME_PATH.'/apps/'.$GLOBALS['app'].'/views.php') || 
			!is_file(LF_LIGHTFRAME_PATH.'/apps/'.$GLOBALS['app'].'/views.php')) {

			if ($view !== 'http/http500') {
				// application was not found.
				trigger_error('Application '.$GLOBALS['app'].' not found');
			}
			else
				// if a rollback to the built-in 404 is being called, but that isn't found either.
				die('Panic: built-in http500 view not found! Please check your settings, or reinstall LightFrame');
		} 
		else {
			// the app was found in the built-in apps path
			$filePath = LF_LIGHTFRAME_PATH.'/apps/';
		}
	}
	else {
		// the app was found in the userland apps path
		$filePath = LF_APPS_PATH.'/';
	}
	
	// read the app's views.php
	require_once $filePath.$GLOBALS['app'].'/views.php';
	
	if (!function_exists($GLOBALS['viewfunc'])) {
		// view doesn't exist.
		trigger_error('View "'.$GLOBALS['view'].'" does not exist');
	}
	else {
		// start a session?
		if (LF_SESSION_ENABLE && session_id() === '') {
			session_name(LF_SESSION_NAME);
			session_start();
		}
		
		$result = $GLOBALS['viewfunc']($args);
		
		if (!($result instanceof Response)) {
			trigger_error('View \''.$GLOBALS['view'].'\' needs to return a Response object');
		}
		return $result->commit();
		
/*		// 
		if (LF_SESSION_ENABLE && session_id() !== '') {
			session_write_close();
		}
*/
	}
}

function _errorHandler($errno, $errstr, $errfile, $errline, $errcontext) {
	$args = array();

	if (LF_DEBUG) {
		$args['message'] = '"'.$errstr.'" in file "'.$errfile.'" on line '.$errline."\n";
	}
	else {
		$args['message'] = $errstr;
	}
	
	ob_start();
	debug_print_backtrace();
	$args['backtrace'] = ob_get_contents();
	ob_end_clean();
	echo _callView('http/http500', $args);
	die();
}

function _exceptionHandler($exception) {
	$args = array();

	$args['message'] = 'Uncaught Exception ('.get_class($exception).')'.
			' in '. $exception->getFile().':'.$exception->getLine().
			': '.(string)$exception->getMessage();
	$args['backtrace'] = $exception->getTraceAsString();
	echo _callView('http/http500', $args);
	die();
}

/**
 * Unescape magic quotes
 */
function _unMagic() {
	// TODO: someone might want to check whether an array_map would be faster than a foreach method?
	if (get_magic_quotes_gpc() !== 0) {
		$_GET = _recUnMagic($_GET);
		$_POST = _recUnMagic($_POST);
		$_COOKIE = _recUnMagic($_COOKIE);
	}
}

/**
 * The recursive portion to _unMagic()
 * 
 * @param array $array the array to unescape
 * @return array the escaped array
 */
function _recUnMagic($array) {
	$temp = array();
	foreach ($array as $key => $value) {
		if (is_array($value)) {
			$temp[$key] = _recUnMagic($value);
		}
		elseif (is_string($value)) {
			$temp[$key] = stripslashes($value);
		}
		else {
			$temp[$key] = $value;
		}
	}
	return $temp;
}
