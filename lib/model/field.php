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

abstract class Field {
	/**
	 * the Field's actual value
	 * @var mixed
	 */
	protected $value;

	/**
	 * Has the Field been changed?
	 * @var boolean
	 */
	protected $dirty = false;

	/**
	 * The name of the Field in a Model
	 * @var string
	 */
	protected $fieldName;

	/**
	 * Has the Field been inflated?
	 * @var boolean
	 */
	protected $inflated = false;

	const OPERATOR_NOT_FOUND = -1;
	const EQUAL = 0;
	const GREATER_THAN = 1;
	const GREATER_THAN_OR_EQUAL = 2;
	const LESS_THAN = 3;
	const LESS_THAN_OR_EQUAL = 4;
	const BETWEEN = 5;
	const NOT_EQUAL = 6;

	public function __toString() {
		return (string)$this->value;
	}

	public function set($value) {
		if ($this->valueIsValidNative($value)) {

			// Don't make any changes if the value is the same
			if ($value === $this->value)
				return;

			$this->value = $value;
			$this->dirty = true;
			$this->inflated = true;
		} else {
			throw new LightFrameException('"'.$value.'" is not a valid value for '.get_class($this));
		}
  }

	public function _setFromSQL($value) {
		if ($this->valueIsValidSQL($value)) {

			// Don't make any changes if the value is the same
			if ($value === $this->value) {
				return;
			}

			$this->value = $value;
			$this->dirty = false;
			$this->inflated = false;

		} else {
			throw new LightFrameException('"'.$value.'" from SQL is not a valid value for '.get_class($this));
		}
	}

	public function get() {
		if (!$this->inflated) {
			$this->inflate();
		}
		return $this->value;
  }

	/**
	 * Has the value changed since it was last retrieved?
	 * @return boolean
	 */
	public function isDirty() {
		return $this->dirty;
	}

	/**
	 * Has the value been inflated since it was last retrieved?
	 * @return boolean
	 */
	 public function isInflated() {
		 return $this->inflated;
	 }

	/**
	 * <p>
	 * Mark the Field as not dirty.
	 * </p>
	 */
	public function _markAsNotDirty() {
		$this->dirty = false;
	}

	protected function parseNormalOperators($operator) {
		switch (strtoupper($operator)) {
			case 'IS':
			case 'EQUALS':
			case 'EQUAL_TO':
			case 'EQUALS_TO':
			case 'EQ':
				return Field::EQUAL;

			case 'GREATER':
			case 'GREATER_THAN':
			case 'GT':
				return Field::GREATER_THAN;

			case 'GREATER_OR_EQUALS':
			case 'GREATER_OR_EQUAL_TO':
			case 'GREATER_THAN_OR_EQUALS':
			case 'GREATER_THAN_OR_EQUAL_TO':
			case 'GTEQ':
				return Field::GREATER_THAN_OR_EQUAL;

			case 'LESS':
			case 'LESS_THAN':
			case 'LT':
				return Field::LESS_THAN;

			case 'LESS_OR_EQUALS':
			case 'LESS_OR_EQUAL_TO':
			case 'LESS_THAN_OR_EQUALS':
			case 'LESS_THAN_OR_EQUAL_TO':
			case 'LTEQ':
				return Field::LESS_THAN_OR_EQUAL;

			case 'BETWEEN':
			case 'IS_BETWEEN':
				return Field::BETWEEN;

			case 'NOT':
			case 'IS_NOT':
			case 'NOT_EQUAL':
			case 'NEQ':
				return Field::NOT_EQUAL;
		}

			return Field::OPERATOR_NOT_FOUND;
	}

	protected function renderNormalOperators($operatorType) {
		switch ($operatorType) {
			case Field::EQUAL:
				return '=';
			case Field::GREATER_THAN:
				return '>';
			case Field::GREATER_THAN_OR_EQUAL:
				return '>=';
			case Field::LESS_THAN:
				return '<';
			case Field::LESS_THAN_OR_EQUAL:
				return '<=';
			case Field::NOT_EQUAL:
				return '<>';
			case Field::BETWEEN:
				return 'BETWEEN';
		}

		return null;
	}

	/**
	 * <p>
	 * Set a Field's name
	 * </p>
	 *
	 * <p>
	 * This can be done only once.
	 * </p>
	 *
	 * @param string $name
	 */
	public function _setName($name) {
		if (!isset($this->fieldName)) {
			$this->fieldName = $name;
		} else {
			throw new BadMethodCallException('Can\'t set a Field\'s name twice');
		}
	}

  /**
	 * <p>
	 * Convert the SQL result from its SQL representation to its PHP-native
	 * representation and store it into the private member <code>$value</code>.
	 * </p>
	 *
	 * <p>
	 * When inflating, the value is already in <code>$this-&gt;value</code>.
	 * </p>
	 *
	 * @return void
	 * @see LF_SQL_RDBMS
	 */
	abstract public function inflate();

	/**
	 * <p>
	 * Convert the PHP native representation in <code>$value</code> to a string
	 * representation for SQL, as required for the currently set-up RMDBS.
	 * </p>
	 *
	 * @return string SQL representation of the <code>Field</code>'s value.
	 * @see LF_SQL_RDBMS
	 */
	abstract public function deflate();

	/**
	 * <p>
	 * Check whether the input value, coming from SQL, is valid or not.
	 * </p>
	 *
	 * @param string $value A string returned by SQL
	 * @return boolean
	 */
	abstract public function valueIsValidSQL($value);

	/**
	 * <p>
	 * Check whether the input value, coming from the PHP code, is valid or not.
	 * </p>
	 *
	 * @param mixed $value A value that the program sets
	 * @return boolean
	 */
	abstract public function valueIsValidNative($value);

	/**
	 * <p>
	 * Convert arbitrary strings into SQL where-clauses.
	 * </p>
	 *
	 * @param array(string) $subcriteria
	 *   <p>This argument contains all subcriteria for the function call. For example,
	 *   in the code "<code>$entries->keep_where_foo__less(10)</code>" the "less"
	 *   would be the first (and only) subcriteria.</p>
	 *
	 * @param array(string) $arguments
	 *   <p>All the arguments passed to the method. In the code example
	 *   <code>$entries->keep_where_foo__between(0,10)</code>", both integers
	 *   0 and 10 would be passed as arguments.</p>
	 *
	 *   <p>The implementing is responsible for checking for correct amount and
	 *   data types. If an erroneous method call is detected, throw an
	 *   <code>BadMethodCallException</code>.</p>
	 *
	 * @return array
	 *   <p>The returned array is a two-dimensional array. The first string is
	 *   a category keyword, which tells what kind of data the array contains. The
	 *   second string is a target SQL entity and the last one is an argument
	 *   that determines what is done to the SQL entity (this depends on the
	 *   category keyword).</p>
	 *
	 *   <p>Example:</p>
	 *   <code><pre>
	 *   [join]
	 *    [thistable.fk] => "othertable"
	 *    [othertable.ffk] => "athirdtable"
	 *   [where]
	 *    [0] => "`athirdtable`.`somecol` == 'something'"
	 *   </pre></code>
	 *
	 *   <p>The above call would render a SQL clause similar to:</p>
	 *
	 *   <code><pre>
	 *   SELECT * FROM foo f
	 *     INNER JOIN othertable o ON f.fk = o.id
	 *     INNER JOIN athirdtable a ON o.ffk = a.id
	 *   WHERE
	 *     a.somecol == 'something'
	 *   </pre></code>
	 *
	 *   <p><em>Note:</em> The asterisk-keys within join sub-array are converted
	 *   to the current model's table name</p>
	 */
	abstract public function _sqlCriteria(array $subcriteria, array $arguments);
}

abstract class StringField extends Field {

	public function valueIsValidSQL($value) {
		return true;
	}

	public function valueIsValidNative($value) {
		return is_string($value);
	}

}

abstract class NumberField extends Field {
}

class IntField extends NumberField {

	public function inflate() {
		$this->value = (int)$this->value;
		$this->inflated = true;
	}

	public function deflate() {
		return (string) $this->value;
	}

	public function valueIsValidSQL($value) {
		return preg_match('/^[0-9]+$/',$value) === 1;
	}

	public function valueIsValidNative($value) {
		return is_int($value);
	}

	public function _sqlCriteria(array $subcriteria, array $arguments) {
		// surplus $subcriteria ignored
		$operator = $subcriteria[0];
		$operatorString = '';

		/*
		 * We need a non-empty operator (even if it might be an invalid one -- taken
		 * care of later on).
		 */
		if ($operator === '') {
			throw new BadMethodCallException('operator was empty for '.get_class($this));
		}

		$operatorType = $this->parseNormalOperators($operator);

		if ($operatorType === Field::OPERATOR_NOT_FOUND) {
			throw new BadMethodCallException($operator.' not understood');
		} else {
			$operatorString = $this->renderNormalOperators($operatorType);
		}

		if ($operatorType === Field::BETWEEN) {
			if (count($arguments) !== 2) {
				throw new InvalidArgumentException('\''.$operator.'\' requires exactly two arguments');
			}

			if (!$this->valueIsValidNative($arguments[0]
						|| !$this->valueIsValidNative($arguments[1]))) {
				throw new InvalidArgumentException();
			}

			return $this->fieldName.' '.$operatorString.' '.$arguments[0].' AND '.$arguments[1];
		} else {
			if (count($arguments) !== 1) {
				throw new InvalidArgumentException('\''.$operator.'\' requires exactly one argument');
			}

			return array('where' => $this->fieldName.' '.$operatorString.' '.$arguments[0]);
		}

	}
}

class TextField extends StringField {

	const STARTS_WITH = -2;
	const ENDS_WITH = -3;
	const CONTAINS = -4;

	public function inflate() {
		$this->value = (string)$this->value;
		$this->inflated = true;
	}

	public function deflate() {
		$sql = new SQL();
		return $sql->escape($this->value);
	}

	public function _sqlCriteria(array $subcriteria, array $arguments) {
		// excess $subcriteria ignored
		$operator = array_shift($subcriteria);
		$operatorType = null;
		$operatorString = '';

		$operatorType = $this->parseNormalOperators($operator);

		if ($operatorType === Field::OPERATOR_NOT_FOUND) {
			switch (strtoupper($operator)) {
				case 'STARTS_WITH':
				case 'BEGINS_WITH':
				case 'STARTS':
				case 'BEGINS':
				case 'STARTSWITH':
				case 'BEGINSWITH':
					$operatorType = TextField::STARTS_WITH;
					break;

				case 'ENDS_WITH':
				case 'ENDS':
				case 'ENDSWITH':
					$operatorType = TextField::ENDS_WITH;
					break;

				case 'CONTAINS':
				case 'HAS':
					$operatorType = TextField::CONTAINS;
					break;
			}

			if ($operatorType === Field::OPERATOR_NOT_FOUND) {
				throw new BadMethodCallException('Operator \''.$operator.'\' not understood for '.get_class($this));
			}
		}

		if ($operatorType === Field::BETWEEN) {
			// Special case for BETWEEN-operator, even though it's a bit weird a case

			if (count($arguments) !== 2) {
				throw new InvalidArgumentException('\''.$operator.'\' requires exactly two arguments.');
			}

			if (!$this->valueIsValidNative($arguments[0])) {
				throw new InvalidArgumentException('\''.$arguments[0].'\' is not a valid arugment.');
			}

			if (!$this->valueIsValidNative($arguments[1])) {
				throw new InvalidArgumentException('\''.$arguments[1].'\' is not a valid arugment.');
			}

			$sql = new SQL();
			$returnString = $this->fieldName.' BETWEEN '.
				$sql->escape($arguments[0]).' AND '.$sql->escape($arguments[1]);
			unset($sql);

			return array('where' => $returnString);

		} elseif (count($arguments) !== 1) {
			throw new InvalidArgumentException('\''.$operator.'\' requires exactly one argument.');

		} elseif (!$this->valueIsValidNative($arguments[0])) {
			throw new InvalidArgumentException('\''.$arguments[0].'\' is not a valid arugment.');

		} else {
			$from = array('!','%','_','[',']');
			$to = array('!!','!%','!_','![','!]');
			$argument = str_replace($from, $to, $arguments[0]);
			$argument_escaped = '';
			$sql = new SQL();

			switch ($operatorType) {
				case TextField::STARTS_WITH:
					$argument_escaped = $sql->escape($argument.'%').' ESCAPE \'!\'';
					$operatorString = 'LIKE';
					break;

				case TextField::ENDS_WITH:
					$argument_escaped = $sql->escape('%'.$argument).' ESCAPE \'!\'';
					$operatorString = 'LIKE';
					break;

				case TextField::CONTAINS:
					$argument_escaped = $sql->escape('%'.$argument.'%').' ESCAPE \'!\'';
					$operatorString = 'LIKE';
					break;

				default:
					$argument_escaped = $sql->escape($argument);
					$operatorString = '=';
			}

			return array('where' => $this->fieldName.' '.$operatorString.' '.$argument_escaped);
		}
	}
}

class ManyToOneField extends Field {
	/**
	 * @var Model
	 */
	private $referenceModel = null;

	public function __construct($reference) {
		if (!is_string($reference) || !class_exists($reference)) {
			throw new LightFrameException(get_class($this).
				' expects an existing class\' name as a constructor argument'
			);
		}

		$model = new $reference();

		if (!($model instanceof Model)) {
			throw new LightFrameException(get_class($this).
				' expects a Model class\' name as a constructor argument'
			);
		}

		$this->referenceModel = $model;
	}

	public function valueIsValidNative($value) {
		return (is_object($value) && $value instanceof $this->referenceModel);
	}

	public function valueIsValidSQL($value) {
		// This is the best we can do at the moment
		return is_numeric($value) && ($value == (int)$value);
	}

	public function inflate() {
		$value = (int)$this->value;
		$this->value = clone $this->referenceModel;
		$this->value->load($value);

		if ($this->value->id !== $value) {
			// reset the value
			$this->value = $value;
			$this->inflated = false;

			throw new LightFrameException('Could not inflate '.
				get_class($this).'(\''.
				get_class($this->referenceModel).'\') with id '.$value
			);
		} else {
			$this->inflated = true;
		}
	}

	public function deflate() {
		// before trying to deflate, save it to the database
		if ($this->isInflated() && $this->value->_isDirty()) {
			$this->value->save();
		}

		return (string) $this->value->id;
	}

	public function _sqlCriteria(array $subcriteria, array $arguments) {
		$model           =& $this->referenceModel;
		$modelTableName  =  $model->_getSQLTableName();
		$wantedFieldName =  $subcriteria[0];
		$fieldObjectName =  $wantedFieldName.Model::FIELD_OBJECT_SUFFIX;

		if (isset($model->$wantedFieldName)) {
			$passedSubcriteria = $subcriteria;
			array_shift($passedSubcriteria);

			$baseArray = array(
				'join' => array($this->fieldName => $modelTableName)
			);

			$criteriaArray = $model->$fieldObjectName->_sqlCriteria($passedSubcriteria, $arguments);

			return array_merge_recursive($baseArray, $criteriaArray);
		} else {
			// activate the normal 'not found' message
			$model->$wantedFieldName;
		}
	}
}
