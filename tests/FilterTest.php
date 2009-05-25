<?php

require_once '../lib/template/filters.php';

class FilterTest extends PHPUnit_Framework_TestCase {

	public function testUppercase() {
		foreach ($this->provider() as $string) {
			$this->assertTrue(preg_match('/^[^a-z]*$/',lf_filter_uppercase($string, null)) > 0);
		}
	}

	public function testLowercase() {
		foreach ($this->provider() as $string) {
			$this->assertTrue(preg_match('/^[^A-Z]*$/', lf_filter_lowercase($string, null))>0);
		}
	}

	public function testCapitalize() {
		foreach ($this->provider() as $string) {
			$this->assertTrue(preg_match('/\b[^a-z]/', lf_filter_capitalize($string, null)) >0 );
		}
	}

	public function testCapitalizeFirst() {
		foreach ($this->provider() as $string) {
			$this->assertTrue(preg_match('/^[^a-z]/', lf_filter_capitalisefirst($string, null)) > 0);
		}
	}

	public function testDefault() {
		foreach ($this->provider() as $string) {
			$this->assertEquals($string, lf_filter_default($string, "[fail]"));
		}

		$this->assertEquals('alt', lf_filter_default("", 'alt'), 'Empty string should trigger the alternative text');
		$this->assertEquals('alt', lf_filter_default(0, 'alt'), 'Zero integer should trigger the alternative text');
		$this->assertEquals('alt', lf_filter_default(0.0, 'alt'), 'Zero float should trigger the alternative text');
		$this->assertEquals('alt', lf_filter_default(null, 'alt'), 'Null should trigger the alternative text');
		$this->assertEquals('alt', lf_filter_default(array(), 'alt'), 'Empty array should trigger the alternative text');
		$this->assertEquals('alt', lf_filter_default(array("not_empty"), 'alt'), 'Non-empty array should _not_ trigger the alternative text');
	}

	public function provider() {
		return array(
			"foo bar",
			"Foo Bar",
			"fOO bAR",
			"foo BAR",
			"FOO bar"
		);
	}
}
