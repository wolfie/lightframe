<?php
/**
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License v2.0
 * @author Henrik Paul
 */

/**
 * Show a 404 not found
 * 
 * TODO: Clean up the function, make it more informative
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
 * TODO: Clean up the function
 *
 * @param array $args
 * @return Response
 */
function http500($args) {
	$template = 'http/500.html';
	$context = array();
	
	// print backtrace
	// TODO: fix this into the template once templates are mature enough
	$context['details'] .= htmlspecialchars($args['backtrace']);
	
	$response = new Response($context, $template, true);
	$response->header->status = HTTPHeaders::INTERNAL_ERROR;
	return $response;
}

?>
