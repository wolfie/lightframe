<?php
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
	
	// TODO:print backtrace
	$context['details'] .= htmlspecialchars($args['backtrace']);
	
	$response = new Response($context, $template, true);
	$response->header->status = HTTPHeaders::INTERNAL_ERROR;
	return $response;
}

/**
 * Show a 403 forbidden
 *
 * @param array $args
 */
function http403($args) {
	$template = 'http/403.html';
	$response = new Response(array(),'http/403.html',true);
	$response->header->status = HTTPHeaders::FORBIDDEN;
	return $response;
}

?>
