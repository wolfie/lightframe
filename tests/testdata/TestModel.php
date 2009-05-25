<?php
require_once '../lib/model/model.php';
require_once '../lib/model/field.php';

if (!defined('LF_APPS_PATH')) {
	define('LF_APPS_PATH', dirname(__FILE__));
}

if (!defined('LF_SQL_UNIQUE_PREFIX')) {
	define('LF_SQL_UNIQUE_PREFIX','');
}

if (!defined('LF_SQL_RDBMS')) {
	define('LF_SQL_RDBMS', 'sqlite');
}

class TestModel extends Model {
	function __construct() {
		$this->text = new TextField();
		$this->int = new IntField();
		$this->manyToOne = new ManyToOneField('TestModel');
	}
}