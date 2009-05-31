<?php
class NullModel extends Model {
	function getSQLTableName() {
		return "null";
	}
}