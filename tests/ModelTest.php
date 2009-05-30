<?php
require_once 'testdata/TestModel.php';
require_once '../lib/exceptions.php';
require_once 'settings.php';


class ModelTest extends PHPUnit_Framework_TestCase {
	/** @var TestModel */
	private $testModel = null;

	protected function setUp() {
		$this->testModel = new TestModel();
		file_put_contents("sqlite.db", "");
		$pdo = new PDO('sqlite:sqlite.db');
		$pdo->query("CREATE TABLE ".$this->testModel->getSQLTableName()." text, int, manyToOne");
	}

	protected function tearDown() {
		unset($this->testModel);
		unlink("sqlite.db");
	}

	public function testExistingFieldsAccessibility() {
		$this->testModel->int;
		$this->testModel->text;
		$this->testModel->manyToOne;
	}


	/** 
	 * @expectedException LightFrameException
	 */
	public function testNotExistingFieldsAccessibility() {
		$this->testModel->notExistingValues;
	}

	public function testTextField() {
		$this->assertType('string', $this->testModel->text);
		$this->assertNotType('object', $this->testModel->text);
		$this->assertType('TextField', $this->testModel->text__field);
		$this->assertEquals('', $this->testModel->text);
		$this->assertEquals($this->testModel->text, $this->testModel->text__value);
	}

	public function testIntField() {
		$this->assertType('int', $this->testModel->int);
		$this->assertNotType('object', $this->testModel->int);
		$this->assertType('IntField', $this->testModel->int__field);
		$this->assertEquals(0, $this->testModel->int);
		$this->assertEquals($this->testModel->int, $this->testModel->int__value);


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
		$this->assertType('null', $this->testModel->manyToOne);
		$this->assertNotType('object', $this->testModel->manyToOne);
		$this->assertType('ManyToOneField', $this->testModel->manyToOne__field);
		$this->assertEquals($this->testModel->manyToOne, $this->testModel->manyToOne__value);


		$oldValue = $this->testModel->manyToOne;
		try {
			$this->testModel->manyToOne = 0;
			$this->fail("ManyToOne should not accept integers");
		} catch (LightFrameException $e) {
			// We wanted this...
			$this->assertEquals($oldValue, $this->testModel->manyToOne, "The value must not change after a failed assignment");
		}

		try {
			$this->testModel->manyToOne = "text";
			$this->fail("ManyToOne should not accept strings");
		} catch (LightFrameException $e) {
			// We wanted this...
			$this->assertEquals($oldValue, $this->testModel->manyToOne, "The value must not change after a failed assignment");
		}

//		$this->assertTrue(is_object($this->testModel->manyToOne));
//		$this->assertTrue($this->testModel->manyToOne instanceof Model);
	}
}