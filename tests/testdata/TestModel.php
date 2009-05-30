<?php
require_once '../lib/model/model.php';
require_once '../lib/model/field.php';

class TestModel extends Model {
	function __construct() {
		$this->text = new TextField();
		$this->int = new IntField();
		$this->manyToOne = new ManyToOneField('TestModel');
	}

	function getSQLTableName() {
		return "testmodel";
	}
}