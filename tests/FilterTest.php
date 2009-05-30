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
		$this->assertNotEquals('alt', lf_filter_default(array(), 'alt'), 'Empty array should _not_ trigger the alternative text');
		$this->assertNotEquals('alt', lf_filter_default(array("not_empty"), 'alt'), 'Non-empty array should _not_ trigger the alternative text');
	}

	public function testSafe() {
		$this->assertEquals("<div></div>", lf_filter_safe("&lt;div&gt;&lt;/div&gt;", null));
	}

	public function testSpacesToUnderscores() {
		$this->assertEquals("_foo_bar_", lf_filter_spaces_to_underscores(" foo bar ",null));
	}

	public function testUnderscoresToSpaces() {
		$this->assertEquals(" foo bar ", lf_filter_underscores_to_spaces("_foo_bar_",null));
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
