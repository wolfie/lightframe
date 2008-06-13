<?php
/**
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License v2.0
 * @author Henrik Paul
 */

/**
 * A metaclass for fields.
 * 
 * This extends upcoming fields
 *
 */
abstract class Field {
	/**
	 * The equivalent SQL type
	 *
	 * @var string
	 */
	protected $sqltype = '';
	/**
	 * Settings associated with the field
	 *
	 * @var array
	 */
	protected $settings = array();
	/**
	 * An internal representation of the field's value
	 *
	 * @var mixed
	 */
	protected $value = null;

	/**
	 * General Field constructor
	 * 
	 * Setup default sqltype and settings. It's recommended, but not required,
	 * for the extended Fields to run this constructor.
	 *
	 * @param array $args
	 */
	function __construct($args) {
		if ($this->sqltype === '') {
			$this->sqltype = strtoupper(substr(get_class($this), 0, -5));
		}
		
		// Set the global default values
		$defaults['null'] = false; // is null allowed
		$defaults['default'] = null; // the default value
		$defaults['max_length'] = null;
		
		$this->settings = $this->mergeArgs($defaults, $args);
	}

	/**
	 * To String
	 * 
	 * Cast $this->value into a string and return it
	 *
	 * @return string
	 */
	function __toString() {
		return (string) $this->value;
	}
	
	/**
	 * Return the relvar information for CREATE TABLE
	 *
	 * @return string
	 */
	function _getSQLInfo() {
		if ($this->settings['max_length'] !== null) {
			return $this->sqltype.'('.$this->settings['max_length'].')';
		}
		else {
			return $this->sqltype;
		}
	}
	
	/**
	 * Validate and change the internal value
	 *
	 * @param mixed $value
	 */
	function set($value=null) {
		if ($value !== null && !$this->validate($value)) {
			throw new InvalidFormatException(array($value,get_class($this)));
		}
		
		$this->value = $value;
	}
	
	/**
	 * Return the internal value
	 *
	 * @return mixed
	 */
	function get() {
		return $this->value;
	}

	/**
	 * Return $this->value as it would be inserted into SQL
	 *
	 * @return string
	 */
	function sql() {
		// if the value is null, try with the default value
		if ($this->value === null) {
			$this->value = $this->settings['default'];
		}
		
		// if it's still null...
		if ($this->value === null) {
			if ($this->settings['null']) {
				return 'NULL';
			}
			else {
				throw new NullNotAllowedException();
			}
		}
		else {
			return $this->toSQL($this->value);
		}
	}
	
	/**
	 * Accessor for $this->settings
	 *
	 * @param string $setting
	 * @return mixed
	 */
	function getSetting($setting) {
		if (!isset($this->settings[$setting])) {
			throw new Exception('Setting \''.$setting.'\' not set for \''.get_class($this).'\'');
		}
		else {
			return $this->settings[$setting];
		}
	}
	
	/**
	 * A method to merge two settings arrays (default with user defined) and return the result
	 *
	 * @param array $defaults
	 * @param array $args
	 * @return array
	 */
	protected function mergeArgs($defaults, $args) {
		if (!is_array($args) && $args !== null) {
			trigger_error(get_class($this).' needs an array argument');
		}
		if ($args !== null) {
			return array_merge($defaults, $args);
		}
		else {
			return $defaults;
		}
	}
	
	/**
	 * Convert the value into internal value from a SQL result
	 */
	abstract function fromSQL($input);
	/**
	 * Convert the value into SQL-compatible mode from an internal value
	 */
	abstract function toSQL();
	/**
	 * Parse the input from a Entires->where() and return a SQL-compatible value
	 */
	abstract function fromToFilter($input);
	/**
	 * Assert whether the input is an acceptable input
	 */
	abstract function validate($input);
}




// TODO: get these to a file of their own

class TextField extends Field {
	function __construct($args=null) {
		parent::__construct($args);
	}
	
	function validate($input) {
		return is_string($input);
	}
	
	function toSQL() {
		$sql = new SQL();
		return $sql->escape((string)$this->value);
	}
	
	function fromSQL($input) {
		return $input;
	}
	
	function fromToFilter($input) {
		if ($this->validate($input)) {
			$sql = new SQL();
			return $sql->escape($input);
		}
		else {
			throw new InvalidFormatException(array($input,get_class($this)));
		}
	}
}

// TODO: make it really a varchar field
class CharField extends Field {
	function __construct($args=null) {
		$defaults['max_length'] = 20;
		parent::__construct($this->mergeArgs($defaults,$args));
	}
	
	function validate($input) {
		return (is_string($input) && strlen($input) <= $this->settings['max_length']);
	}
	
	function toSQL() {
		$sql = new SQL();
		return $sql->escape($this->value);
	}
	
	function fromSQL($input) {
		return $input;
	}
	
	function fromToFilter($input) {
		if ($this->validate($input)) {
			$sql = new SQL();
			return $sql->escape($input);
		}
		else {
			throw new InvalidFormatException(array($input,get_class($this)));
		}
	}
}

abstract class TimeField extends Field {
	function __construct($args=null) {
		parent::__construct($args);
	}
}

class DateTimeField extends TimeField {
	function __construct($args=null) {
		$defaults['date_format'] = 'd.m.Y H:i:s'; // TODO: move this to config file?
		parent::__construct($this->mergeArgs($defaults, $args));
	}
	
	function validate($input) {
		return is_numeric($input);
	}
	
	function toSQL() {
		return '\''.date('Y-m-d H:i:s', $this->value).'\'';
	}
	
	function fromSQL($input) {
		return strtotime($input);		
	}
	
	function fromToFilter($input) {
		if (is_numeric($input)) {
			return (int)$input;
		}
		else {
			throw new InvalidFormatException(array($input,get_class($this)));
		}
	}
	
	function __toString() {
		return date($this->settings['date_format'], $this->value);
	}
}

class IntField extends Field {
	function __construct($args=null) {
		parent::__construct($args);
	}
	
	function validate($input) {
		return ((int)$input === $input);
	}
	
	function toSQL() {
		return (string)$this->value;
	}
	
	function fromSQL($input) {
		return (int)$input;
	}
	
	function fromToFilter($input) {
		if (is_numeric($input)) {
			return (int)$input;
		}
		else {
			throw new InvalidFormatException(array($input,get_class($this)));
		}
	}
}

// in foreign key fields, $this->value contains the contents of the refernced model
class ForeignKeyField extends Field {
	private $isEvaluated = false;
	
	function __construct($model=null) {
		if (!is_string($model)) {
			trigger_error(get_class($this).' requires a Model name (string) as an argument');
		}
		if (!class_exists($model)) {
			trigger_error($model.' is not an existing Model');
		}
		
		// TODO: lazy creation
		$this->value = $model;
		$this->sqltype = 'INT';
		
		parent::__construct(array());
	}
	
	
	function __get($name) {
		$this->animateField();
		
		if (!$this->isEvaluated && isset($this->value->id)) {
			$this->value->get($this->value->id);
			$this->isEvaluated = true;
		}
		return $this->value->$name;
	}
	
	/*
	 * until someone can get arrays converted into an amount of
	 * arguments, this gets cludged like this.
	 */ 
	
	/**
	 * Pass function calls to the referenced model
	 *
	 * @param string $name
	 * @param string $args
	 * @return mixed
	 */
	function __call($name, $args) {
		$this->animateField();
		$this->isEvaluated = true;
		
		// TODO: works?
		return call_user_func_array(array(&$this,'value',$name), $args);
		/*
		if (isset($args[0])) {
			return $this->value->$name($args[0]);
		}
		else {
			return $this->value->$name();
		}
		*/
	}
	
	function get() {
		$this->animateField();
		return parent::get();
	}
	
	/**
	 * Make sure that the field is alive and kicking.
	 */
	private function animateField() {
		if (!($this->value instanceof Field)) {
			$field = $this->value;
			$this->value = new $field();
		}
	}
	
	function fromSQL($input) {
		$this->animateField();
		$this->value->get((int)$input);
	}
	
	function toSQL() {
		return (string)$this->value->id;
	}
	
	function fromToFilter($input) {
		trigger_error(get_class($this).' can\'t be used as a part in a filter');
	}
	
	function validate($input) {
		// unused
	}
	
	function set($input) {
		if ($input instanceof Model) {
			$this->value = $input;
		}
		elseif ((int)$input === $input) {
			$this->value->id = $input;
//			$this->value->get($input);
		}
	}
}

class BoolField extends Field {
	function __construct($args = null) {
		switch (LF_SQL_RDBMS) {
			case 'mysql': $this->sqltype = 'EVAL(\'f\',\'t\')';
			case 'pgsql': $this->sqltype = 'BOOLEAN';
			case 'sqlite': $this->sqltype = 'BOOLEAN';
		}
		parent::__construct($args);
	}
	
	function _getSQLInfo() {
		return $this->sqltype;
	}
	
	function fromSQL($input) {
		return $input === 'f' ? false : true;
	}
	
	function toSQL() {
		$sql = new SQL();
		return $this->value === false ? $sql->escape('f') : $sql->escape('t');
	}
	
	function fromToFilter($input) {
		$input = strtolower($input);
		switch ($input) {
			case 'f':
			case 'false':
			case '0':
				return 'f';
			default:
				return 't';
		}
	}
	
	function validate($input) {
		return true;
	}
}

?>
