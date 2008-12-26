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