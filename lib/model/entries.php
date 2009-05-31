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

/**
 *
 * @method string keep_where_*()
 * @method string discard_where_*()
 * @see Entries::KEEP_KEYWORD
 * @see Entries::DISCARD_KEYWORD
 */
class Entries implements ArrayAccess, SeekableIterator, Countable {
	/**
	 * The Entries' model name as a string
	 * @var string
	 */
	private $modelName;

	/**
	 * The Entries' model as an object
	 * @var Model
	 */
	private $modelObject;

	/**
	 * Is the resultset dirty?
	 * @var bool
	 */
	private $dirty;

	/**
	 * Cached and populated Models
	 * @var array(Model)
	 */
	private $resultSetModel;

	/**
	 * Unaccessed Models
	 * @var array(string)
	 */
	private $resultSetSQL;

	/**
	 * Which models we need to join with
	 * @var array
	 */
	private $joins;

	/**
	 * The SQL strings that act as retainers
	 * @var array
	 */
	private $keep;

	/**
	 * The SQL strings that act as omitters
	 * @var array
	 */
	private $discard;

	/**
	 * A pointer that keeps track of the offset we are at
	 * @var int
	 * @see next()
	 * @see rewind()
	 * @see current()
	 * @see key()
	 */
	private $iteratorPointer;

	/**
	 * Cached count value
	 * @var int
	 */
	private $count = -1;

	const KEEP_KEYWORD = 'keep_where_';
	const DISCARD_KEYWORD = 'discard_where_';

	const SUBCRITERIA_SEPARATOR = '__';

	/**
	 * Create a new lazy loading Entries with the Model $model
	 *
	 * @param string $model
	 */
	public function __construct($model) {
		if (is_string($model)) {
			$this->modelName = $model;
			$this->modelObject = new $model();
			$this->dirty = true;
			$this->resultSetModel = array();
			$this->resultSetSQL = array();
			$this->keep = array('select' => array(), 'join' => array(), 'where' => array());
			$this->discard = array();
			$this->joins = array();
			$this->iteratorPointer = -1;

			if (!$this->modelObject instanceof Model) {
				throw new InvalidArgumentException($model.' does not extend Model');
			}
		} else {
			throw new InvalidArgumentException('$model must be a string');
		}
	}

	public function  __call($name, $arguments) {
		// Handle 'keep_where_*' method
		if (strpos($name, Entries::KEEP_KEYWORD) === 0) {
			$criterion = ''; // The Field's name that gets the filter applied to.
			$additives = array(); // Additives are arbitrary commands that can be given to
			$data      = substr($name, strlen(Entries::KEEP_KEYWORD));
			$data      = explode(Entries::SUBCRITERIA_SEPARATOR, $data);

			$criterion = array_shift($data).Model::FIELD_OBJECT_SUFFIX; // we want to access the object itself, not only the value
			$additives = $data;

			$this->keep = array_merge_recursive(
				$this->keep,
				$this->modelObject->$criterion->_sqlCriteria($additives, $arguments)
			);

			$this->dirty = true;

		} else {
			throw new BadMethodCallException('\''.$name.'\' is not a valid method for '.get_class($this));
		}
	}

	/**
	 * <p>
	 * Fetches the data from the database as defined earlier by magic function
	 * calls.
	 * </p>
	 */
	private function fetch() {
		if ($this->dirty) {
			$this->dirty = false;
			$this->iteratorPointer = -1;
			$query = '';
			$sql = new SQL();

			$query = 'SELECT '.
				SQL::toSysId($this->modelObject->_getSQLTableName()).'.* FROM '.
				$this->modelObject->_getSQLTableName().
				$this->getJoinClause().
				$this->getWhereClause();

			$this->resultSetSQL = $sql->query($query);

			// store a cached size of the count
			$this->count = count($this->resultSetSQL);

			$this->rewind();
		}
	}

	private function getWhereClause() {
		if (count($this->keep['where']) > 0) {
			return ' WHERE '.implode(' AND ',$this->keep['where']);
		} else {
			return '';
		}
	}

	private function getJoinClause() {
		$joinString = "";

		foreach ($this->keep['join'] as $referenceColumn => $targetTable) {
			$targetTable = SQL::toSysId($targetTable);

			$joinString .= ' INNER JOIN '.$targetTable.
				' ON '.$targetTable.'.id = '.$referenceColumn;
		}

		return $joinString;
	}

	public function __toString() {
		return '['.get_class($this).': '.$this->modelName.']';
	}

	public function count() {
		if ($this->count >= 0 && !$this->dirty) {
			return $this->count;
		} else {
			// otherwise, do a light COUNT(*) query
			$sql = new SQL();

			$query = 'SELECT COUNT(*) FROM '.
				$this->modelObject->_getSQLTableName().
				$this->getJoinClause().
				$this->getWhereClause()
			;

			$result = $sql->query($query);

			// the answer is wrapped in row and column arrays
			return (int) current(current($result));
		}
	}

	public function offsetExists($offset) {
		if (is_int($offset)) {
			$this->fetch();

			return (isset($this->resultSetModel[$offset])
				|| isset($this->resultSetSQL[$offset]));

		} else {
			return false;
		}
	}

	public function offsetGet($offset) {
		$this->fetch();

		if ($this->offsetExists($offset)) {
			if (isset($this->resultSetModel[$offset])) {
				return $this->resultSetModel[$offset];
			} else {
				$modelName = $this->modelName;
				$model = new $modelName();

				$model->_initFromSQLArray($this->resultSetSQL[$offset]);
				$this->resultSetModel[$offset] = $model;
				unset($this->resultSetSQL[$offset]);

				return $model;
			}
		}

		return null;
	}

	public function offsetSet($offset, $value) {
		throw new ReadOnlyException("Entries can only be read");
	}

	public function offsetUnset($offset) {
		throw new ReadOnlyException("Entries can only be read");
	}

	public function current() {
		$this->fetch();
		return $this->offsetGet($this->iteratorPointer);
	}

	public function key() {
		$this->fetch();
		return $this->iteratorPointer;
	}

	public function next() {
		$this->fetch();
		$this->iteratorPointer++;
		return $this->current();
	}

	public function valid() {
		$this->fetch();

		return(isset($this->resultSetModel[$this->iteratorPointer]) ||
			isset($this->resultSetSQL[$this->iteratorPointer]));
	}

	public function rewind() {
		$this->iteratorPointer = 0;
		return $this->current();
	}

	/**
	 * SeekableIterator::seek($index)
	 *
	 * @param int $index
	 */
	public function seek($index) {
		if (is_integer($index)) {
			if ($index >= 0 && $index < $this->count()) {
				$this->iteratorPointer = $index;
			} else {
				throw new OutOfBoundsException($index.' is not a valid seek position.', null);
			}
		} else {
			throw new InvalidArgumentException(get_class().'::seek($index) requires an integer argument', null);
		}
	}

}
