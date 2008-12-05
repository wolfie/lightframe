<?php

/**
 *
 * @method string keep_where_*()
 * @method string discard_where_*()
 * @see Entries::KEEP_KEYWORD
 * @see Entries::DISCARD_KEYWORD
 */
class Entries implements ArrayAccess, Iterator, Countable {
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
			$this->keep = array();
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
		// Handle 'keep_where_*' and 'discard_where_*' methods
		if (strpos($name, Entries::KEEP_KEYWORD) === 0) {
			$criterion = '';
			$additives = array();

			$data = substr($name, strlen(Entries::KEEP_KEYWORD));
			$data = explode(Entries::SUBCRITERIA_SEPARATOR, $data);
			$criterion = array_shift($data);
			$additives = $data;

			if (isset($this->modelObject->$criterion)) {
				$this->keep[] = $this->modelObject->$criterion->_sqlCriteria($additives, $arguments);
				$this->dirty = true;
			} else {
				throw new BadMethodCallException($this->modelName.' doesn\'t contain a Field called '.$criterion);
			}
		} else {
			throw new BadMethodCallException('\''.$name.'\' is not a valid method for '.get_class($this));
		}
	}

	private function fetch() {
		if ($this->dirty) {
			$this->dirty = false;
			$this->iteratorPointer = -1;
			$query = '';
			$sql = new SQL();

			$query = 'SELECT * FROM '.
				$this->modelObject->_getSQLTableName().
				$this->getWhereClause();

			$this->resultSetSQL = $sql->query($query);

			// store a cached size of the count
			$this->count = count($this->resultSetSQL);

			$this->rewind();
		}
	}

	private function getWhereClause() {
		if (count($this->keep) > 0) {
			return ' WHERE '.implode(' AND ',$this->keep);
		}
	}

	public function count() {
//		echo '{count}';

		if ($this->count >= 0 && !$this->dirty) {
			return $this->count;
		} else {
			
			// otherwise, do a light COUNT(*) query
			$sql = new SQL();
			$result = $sql->query('SELECT COUNT(*) FROM '.
				$this->modelObject->_getSQLTableName().
				$this->getWhereClause()
			);

			// the answer is wrapped in row and column arrays
			return (int) current(current($result));
		}
	}

	public function offsetExists($offset) {
//		echo '{oe:'.$offset.'}';
		$this->fetch();

		return (isset($this->resultSetModel[$offset])
			|| isset($this->resultSetSQL[$offset]));
	}

	public function offsetGet($offset) {
//		echo '{og:'.$offset.'}';
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
//		echo '{c}';
		$this->fetch();
		return $this->offsetGet($this->iteratorPointer);
	}

	public function key() {
//		echo '{k}';
		$this->fetch();
		return $this->iteratorPointer;
	}

	public function next() {
//		echo '{n}';
		$this->fetch();
		$this->iteratorPointer++;
		return $this->current();
	}

	public function valid() {
//		echo '{v}';
		$this->fetch();

		return(isset($this->resultSetModel[$this->iteratorPointer]) ||
			isset($this->resultSetSQL[$this->iteratorPointer]));
	}

	public function rewind() {
//		echo '{r}';
		$this->iteratorPointer = 0;
		return $this->current();
	}

}
