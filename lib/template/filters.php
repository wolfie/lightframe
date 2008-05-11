<?php
function lf_filter_uppercase($string, $arg) {
	return strtoupper($string);
}

function lf_filter_lowercase($string, $arg) {
	return strtolower($string);
}

function lf_filter_capitalize($string, $arg) {
	return ucwords($string);
}

function lf_filter_capitalisefirst($string, $arg) {
	return ucfirst($string);
}

function lf_filter_default($string, $arg) {
	if (!$string)
		return $arg;
	else
		return $string;
}

function lf_filter_safe($string, $arg) {
	return htmlspecialchars_decode($string, ENT_QUOTES);
}
?>