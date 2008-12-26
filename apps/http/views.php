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
 * Show a 404 not found
 *
 * @param array $args
 * @return Response
 */
function http404($args) {
	$response = new Response(array(), 'http/404.html', true);
	$response->header->status = HTTPHeaders::NOT_FOUND;
	return $response;
}

/**
 * Show a 500 internal server error
 *
 * @param array $args
 * @return Response
 */
function http500($args) {
	$template = 'http/500.html';
	$context = array();
	
	if (isset($args['message'])) {
		$context['reason'] = $args['message'];
	}
	
	if (LF_DEBUG) {
		$context['stacktrace'] = $args['backtrace'];
	}
	
	$response = new Response($context, $template, true);
	$response->header->status = HTTPHeaders::INTERNAL_ERROR;
	return $response;
}

/**
 * Show a 401 unauthorized
 * 
 * This code is to be used when logging in would help
 *
 * @param unknown_type $args
 * @return Response
 */
function http401($args) {
	$response = new Response(array(), 'http/401.html', true);
	$response->header->status = HTTPHeaders::UNAUTHORIZED;
	return $response;
}

/**
 * Show a 403 forbidden
 * 
 * This code is to be used when logging in does not help
 *
 * @param array $args
 * @return Response
 */
function http403($args) {
//	$template = 'http/403.html';
	$response = new Response(array(),'http/403.html',true);
	$response->header->status = HTTPHeaders::FORBIDDEN;
	return $response;
}