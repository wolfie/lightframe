<?php
require_once 'testdata/TestModel.php';
require_once '../lib/exceptions.php';

class ModelTest extends PHPUnit_Framework_TestCase {
	private $testModel = null;

	protected function setUp() {
		$this->testModel = new TestModel();
	}

	protected function tearDown() {
		unset($this->testModel);
	}

	public function testTextField() {
		$this->assertTrue(is_string($this->testModel->text));
		$this->assertFalse(is_object($this->testModel->text));
		$this->assertEquals('', $this->testModel->text);
	}

	public function testIntField() {
		$this->assertTrue(is_integer($this->testModel->int));
		$this->assertFalse(is_object($this->testModel->int));
		$this->assertEquals(0, $this->testModel->int);

		$this->testModel->int = $this->testModel->int + 1;
		$this->assertEquals(1, $this->testModel->int);

		$this->testModel->int += 1;
		$this->assertEquals(2, $this->testModel->int);

		$this->testModel->int++;
		$this->assertEquals(3, $this->testModel->int);

		$this->testModel->int = (int)($this->testModel->int / 2);
		$this->assertEquals(1, $this->testModel->int);
	}

	public function testManyToOneField() {
//		$this->assertTrue(is_object($this->testModel->manyToOne));
//		$this->assertTrue($this->testModel->manyToOne instanceof Model);
	}
}