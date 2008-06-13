<?php
/**
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License v2.0
 * @author Henrik Paul
 */

/**
 * Convert string to uppercase
 *
 * @param string $string
 * @param array $arg
 * @return string
 */
function lf_filter_uppercase($string, $arg) {
	return strtoupper($string);
}

/**
 * Convert string to lowercase
 *
 * @param string $string
 * @param array $arg
 * @return string
 */
function lf_filter_lowercase($string, $arg) {
	return strtolower($string);
}

/**
 * Capitalize the first character of each word in a string
 *
 * @param string $string
 * @param array $arg
 * @return string
 */
function lf_filter_capitalize($string, $arg) {
	return ucwords($string);
}

/**
 * Capitalize the first character of a string
 *
 * @param string $string
 * @param array $arg
 * @return string
 */
function lf_filter_capitalisefirst($string, $arg) {
	return ucfirst($string);
}

/**
 * If the input string is empty, show the argument string
 *
 * @param string $string
 * @param array $arg
 * @return string
 */
function lf_filter_default($string, $arg) {
	if (!$string)
		return $arg;
	else
		return $string;
}

/**
 * Display string as raw HTML to the browser
 *
 * @param string $string
 * @param array $arg
 * @return string
 */
function lf_filter_safe($string, $arg) {
	return htmlspecialchars_decode($string, ENT_QUOTES);
}
?>