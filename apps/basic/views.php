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
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License v2.0
 * @author Henrik Paul
 */

/**
 * Show and render a template given in the 'template' argument
 *
 * @param array $args
 * @return Response
 */
function show($args) {
	if (!isset($args['context'])) {
		$args['context'] = array();
	}
	
	$builtin = (isset($args['builtin']) ? $args['builtin'] : null);
	
	_requireArgument(__FUNCTION__,'template',$args);
	
	return new Response($args['context'], $args['template'], $builtin);
}

/**
 * Redirect the client with a 301 http code to 'url' argument
 *
 * @param array $args
 */
function redirect($args) {
	_requireArgument(__FUNCTION__,'url', $args);
	
	require_once(LF_LIGHTFRAME_PATH.'/lib/response/response.php');
	$headers = new HTTPHeaders();
	$headers->status = HTTPHeaders::MOVED;
	$headers->Location = $args['url'];
	$headers->send();
	die();
}

// TODO: convert into a helper
/**
 * Pass a file from the filesystem 'dir'/'file' with appropriate http headers
 *
 * @param array $args
 * @return Response/null
 */
function passfile($args) {
	$mime = null;
	$e = null;

	try {
		_requireArgument(__FUNCTION__,'dir',$args);
	}
	catch (Exception $e) {
		_requireArgument(__FUNCTION__,'file',$args['context']);
		$args['dir'] = $args['context']['dir'];
	}
	
	try {
		_requireArgument(__FUNCTION__,'file',$args);
	}
	catch (Exception $e) {
		_requireArgument(__FUNCTION__,'file',$args['context']);
		$args['file'] = $args['context']['file'];
	}
	
	$file = $args['dir'].'/'.$args['context']['file'];
	if (!is_readable($file) || !is_file($file)) {
		if (LF_DEBUG) {
			trigger_error($file.' not found or not a file');
		}
		else {
			// TODO: use a 404 helper
			$response = new Response(array('title'=>'Not Found'), 'http/base.html', true);
			$response->status = 404;
			return $response;
		}
	}
	
	/*
	 * because mime_content_type is deprecated and PECL Fileinfo hardly is 
	 * installed, do stuff the hard way.
	 */
	
	$extension = explode('.',$file);
	$extension = array_pop($extension);
	
	// TODO: to config file?
	$blacklist = array(
		'conf', 'php', 'cfg', 'ini', 'htpasswd', 'htaccess'
	);
	
	if (in_array($extension, $blacklist)) {
		// TODO: mail the admin?
		trigger_error('files with the extension \''.$extension.'\' are blacklisted');
	}
	
	// TODO: to config file?
	$mimes = array(
		// text / script
		'html' => 'text/html; charset='.LF_DEFAULT_CHARSET,
		'htm' => 'text/html; charset='.LF_DEFAULT_CHARSET,
		'txt' => 'text/plain; charset='.LF_DEFAULT_CHARSET,
		'css' => 'text/css; charset='.LF_DEFAULT_CHARSET,
		'phps' => 'application/x-httpd-php-source; charset='.LF_DEFAULT_CHARSET,
		'js' => 'application/x-javascript; charset='.LF_DEFAULT_CHARSET,
		'xml' => 'text/xml; charset='.LF_DEFAULT_CHARSET,
		'xhtml' => 'application/xhtml+xml; charset='.LF_DEFAULT_CHARSET,
		'rss' => 'application/rss+xml; charset='.LF_DEFAULT_CHARSET,
	
		// documents
		'pdf' => 'application/pdf',
		'doc' => 'application/msword',
		'xls' => 'application/vnd.ms-excel',
		'rtf' => 'application/rtf',
		'odf' => 'application/vnd.oasis.opendocument.text',
		'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
		'odp' => 'application/vnd.oasis.opendocument.presentation',
	
		// images
		'jpg' => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png' => 'image/png',
		'gif' => 'image/gif',
		'tif' => 'image/tiff',
		'tiff' => 'image/tiff',
		'svg' => 'image/svg+xml',
	
		// interactive
		'swf' => 'application/x-shockwave-flash',
	
		// video
		'mpg' => 'video/mpeg',
		'mpeg' => 'video/mpeg',
		'avi' => 'audio/msvideo',
		'mov' => 'video/quicktime',
		'qt' => 'video/quicktime',
	
		// audio
		'mp3' => 'audio/mpeg',
		'm3u' => 'audio/m-mpegurl',
	
	);
	
	if (isset($mimes[$extension])) {
		$mime = $mimes[$extension];
	}
	else {
		$mime = 'application/octet-stream';
	}
	
	require_once(LF_LIGHTFRAME_PATH.'/lib/response/response.php');
	$headers = new HTTPHeaders();
	$headers->{'Content-Type'} = $mime;
	$headers->{'Content-Length'} = (string)filesize($file);
	$headers->send();
	readfile($file);
	die();
}

/**
 * Auxiliary function to force argument existence
 * 
 * If the argument is not present, an Exception is thrown
 *
 * @param string $function ftn name
 * @param string $arg required argument
 * @param &array $args an array to search
 */
function _requireArgument($function,$arg,&$args) {
	if (!isset($args[$arg])) {
		throw new Exception($function.' view requries \''.$arg.'\' argument');
	}
}