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
	$template = 'http/base.html';
	$context = array();
	$context['title'] = 'Not Found';
	$context['message'] = isset($args['context']['message']) ? $args['context']['message'] : '';
	$context['details'] = isset($args['context']['details']) ? $args['context']['details'] : '';
	
	$response = new Response($context, $template, true);
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
	$template = 'http/base.html';
	$context = array();
	$context['title'] = 'Internal Server Error';
	$context['message'] = isset($args['context']['message']) ? $args['context']['message'] : '';
	$context['details'] = isset($args['context']['details']) ? $args['context']['details'] : '';
	
	// print backtrace
	// TODO: fix this into the template once templates are mature enough
	$context['details'] .= '<h2>Backtrace</h2>';
	$context['details'] .= '<pre>'.htmlspecialchars($args['backtrace']).'</pre>';
	$response = new Response($context, $template, true);
	$response->header->status = HTTPHeaders::INTERNAL_ERROR;
	return $response;
}

?>
