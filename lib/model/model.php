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

require_once 'entries.php';
require_once 'field.php';
require_once 'sql.php';

abstract class Model {
	/** The unique identifier */
	private $id = -1;

	/** Is the model retrieved from the database or not? */
	private $_retrieved = false;

	/** Array containing the Model's fields */
	private $_fields = array();

	/** Since the calculation is taxing, it's best to cache the result */
	private $_cachedTableName = null;

	//	const TABLE_NAME = 'table_name';
	//	const ORDER_BY = 'order_by';
	const FIELD_VALUE_SUFFIX = '__value';
	const FIELD_OBJECT_SUFFIX = '__field';

	/**
	 * <p>Magic method to set fields.</p>
	 *
	 * <p>
	 * The first time __set() is run (in the constructor of the Model), it is
	 * expected that <code>$value</code> is an instance of Field. Consecutive
	 * calls are directed to that Field, instead of overwriting the Field itself.
	 * </p>
	 *
	 * <p>
	 * Trying to define a Field twice, or failing to assign a Field the first time
	 * will result in Exceptions.
	 * </p>
	 *
	 *
	 * @param string $name
	 * @param mixed $value
	 */
	final public function __set($name, $value) {
		if ($value instanceof Field) {
			if (!isset($this->_fields[$name])) {
				$this->_fields[$name] = $value;
				$this->_fields[$name]->_setName(SQL::toSysId($this->_getSQLTableName()).'.'.SQL::toSysId($name));
			} else {
				throw new UnexpectedValueException($name.' is already given a Field type.');
			}
		} elseif (isset($this->_fields[$name])) {
			$this->_fields[$name]->set($value);
		} else {
			throw new UnexpectedValueException('The value must be a Field object');
		}
	}

	/**
	 * Magic method to get fields
	 *
	 * $name must be previously set, or an exception is thrown.
	 *
	 * @param string $name
	 * @return Field
	 */
	final public function __get($name) {
		if ($name === 'id') {
			// 'id' is a special case
			return $this->id;
		} elseif (isset($this->_fields[$name])) {
			// get the value instead of the raw object
			return $this->_fields[$name]->get();
		} else {
			// Get the value explicitly instead of the raw object
			$value = explode(Model::FIELD_VALUE_SUFFIX, $name, 2);
			if (isset($value[1])) {
				return $this->_fields[$value[0]]->get();
			}

			// Get the raw object explicitly, instead of the value
			$object = explode(Model::FIELD_OBJECT_SUFFIX, $name, 2);
			if (isset($object[1])) {
				return $this->_fields[$object[0]];
			}

			throw new LightFrameException('field \''.$name.'\' is not defined for model '.get_class($this));
		}
	}

	/**
	 * Magic method to unset (reset) a field
	 *
	 * @param string $name
	 */
	final function __unset($name) {
		if (isset($this->_fields[$name])) {
			// this will be magically overridden by the Field
			unset($this->_fields[$name]);
		}
	}

	final function __isset($name) {
		return isset($this->_fields[$name]);
	}

	/**
	 * Is the model retrieved from the database or not?
	 *
	 * @return boolean
	 */
	final public function _isRetrieved() {
		return $this->_retrieved;
	}

	/**
	 * Has the values of this entity changed?
	 *
	 * @return boolean
	 */
	final public function _isDirty() {
		foreach ($this->_fields as $field) {
			if ($field->isDirty()) {
				return true;
			}
		}
		return false;
	}

	/**
	 * <p>
	 * Get the SQL string to reflect the state of the model.
	 * </p>
	 *
	 * <p>
	 * Note that this does not change the internal state of this Model. If you
	 * choose to submit the SQL manually, LightFrame will probably be unable to
	 * track the Model's changes, therefore making the state of this object
	 * unreliable.
	 * </p>
	 *
	 * @return mixed
	 *    A string containing the SQL required to persist the current
	 *    object is returned. If there is nothing to persist, this
	 *    returns <code>null</code>.
	 */
	final public function _getStoreSQL() {
		$query = '';
		if (!$this->_isRetrieved()) {
			$insertData = array();

			foreach ($this->_fields as $key => $value) {
				$key = SQL::toSysId($key);
				$insertData[$key] = $value->deflate();
			}

			$query =
				'INSERT INTO '.
				SQL::toSysId($this->_getSQLTableName()).
				' ('.implode(',',array_keys($insertData)).') '.
				'VALUES ('.implode(',',$insertData).')';

			return $query;

		} elseif ($this->_isDirty()) {
			$updateData = array();

			foreach ($this->_fields as $key => $value) {
				if ($value->isDirty()) {
					$updateData[] = SQL::toSysId($key).' = '.$value->deflate();
				}
			}

			$query  = 'UPDATE '.$this->_getSQLTableName();
			$query .= ' SET '.implode(', ',$updateData);
			$query .=' WHERE id = '.$this->id;

			return $query;

		} else {

			// There's nothing to change in the DB
			return null;
		}
	}

	/**
	 * <p>Retrieve the Model from the database with the id <code>$id</code>.</p>
	 *
	 * @param int $id
	 */
	final public function load($id) {
		if ($id !== (int)$id) {
			throw new LightFrameException('$id needs to be an integer');
		}

		$result = array();
		$sql = new SQL();

		$query = 'SELECT * FROM '.
		SQL::toSysId($this->_getSQLTableName()).
			' WHERE id = '.$id.' LIMIT 1';

		$result = $sql->query($query);

		// skip setting the model, if we didn't get a hit.
		if (is_array($result) && count($result) > 0) {
			$this->_initFromSQLArray($result[0]);
		}
	}

	/**
	 * <p>
	 * Initialize the model from an associative SQL array.
	 * </p>
	 *
	 * @param array $array
	 */
	final public function _initFromSQLArray($array) {
		// Check that this is a valid array
		if (!isset($array['id']) || !is_numeric($array['id'])) {
			throw new LightFrameException('Input array needs to contain a numeric element "id"');
		} else {
			foreach (array_keys($array) as $key) {
				if (!is_string($key)) {
					throw new LightFrameException('Input array needs to be an associative array');
				}
			}
		}

		// this would only mess up the for-loop below, so set it manually
		$this->id = (int)$array['id'];
		unset($array['id']);

		foreach (array_keys($array) as $resultKey) {
			if (isset($this->_fields[$resultKey])) {
				$this->_fields[$resultKey]->_setFromSQL($array[$resultKey]);
			}
		}

		$this->_retrieved = true;
	}

	/**
	 * Save the Model's current state
	 */
	final public function save() {
		$query = $this->_getStoreSQL();

		if ($query === null) {
			return;
		}

		if ($query !== null) {
			$sql = new SQL();
			$oldId = $this->id;

			$sql->query($query);
			$this->_retrieved = true;

			if ($oldId === -1) {
				$this->id = $sql->getLastID($this->_getSQLTableName());
			}

			foreach ($this->_fields as $field) {
				$field->_markAsNotDirty();
			}
		}
	}

	/**
	 * <p>
	 * Update the model from the database according to the current id
	 * </p>
	 *
	 * <p>
	 * This method disregards the value of $_retrieved and
	 * </p>
	 */
	final public function merge() {
		if (is_int($this->id) && $this->id >= 0) {
			$this->load($this->id);
		}
	}

	/**
	 * <p>
	 * Delete this entry from the database, and clear all information within this
	 * object.
	 * </p>
	 *
	 * @param boolean $forceBlindDelete
	 */
	final public function delete($forceBlindDelete = false) {
		if (($this->_isRetrieved() || $forceBlindDelete)
				&& is_int($this->id)
				&& $this->id >= 0 ) {

			$sql = new SQL();
			$sql->query('DELETE FROM '.$this->_getSQLTableName().' WHERE id = '.$this->id);
		}
		
		$this->clear();
	}

	/**
	 * <p>
	 * Construct the SQL table name for this Model
	 * </p>
	 *
	 * <p>
	 * It is encouraged that the user overrides this method manually, if the app
	 * and/or model contains any non-alphanumeric (a-Z, 0-9) characters, to be
	 * sure that the table name doesn't collide with another one.
	 * </p>
	 *
	 * @return string
	 */
	final public function _getSQLTableName() {
		// Use the cached table name
		if ($this->_cachedTableName != null) {
			return $this->_cachedTableName;
		}

		// Warning: this might lead to wonky results if $appName contains non-ASCII
		// characters
		else {
			$reflector = new ReflectionObject($this);
			$appName = dirname($reflector->getFileName());

			if (strpos($appName, LF_APPS_PATH) === 0) {
				// The app part
				$tableName = substr($appName, strlen(LF_APPS_PATH) + 1);

				// The model part
				$tableName .= '/'.get_class($this);

				// Fix "App/Mötley Crüe/MyModel" -> "App_MtleyCre_MyModel"
				$nameLength = strlen($tableName);
				for ($i=0; $i<$nameLength; $i++) {
					$char = ord($tableName{$i});

					// either a forward or backward slash
					if ($char === 47 || $char === 92) {
						$tableName{$i} = '_';
					}

					// not [a-zA-Z0-9]
					elseif ($char <= 47
						|| ($char >= 58 && $char <= 64)
						|| ($char >= 91 && $char <= 96)
						|| $char >= 123) {
						$tableName{$i} = '';
					}
				}

				$tableName = $this->_getUniquePrefix().$tableName;

				$this->_cachedTableName = $tableName;

				return $tableName;
			} else {
				trigger_error('Cannot autogenerate a SQL table name for model '.get_class($this));
			}
		}
	}

	/**
	 * Get an unique prefix for the table name. This enables the user to install
	 * several LightFrame projects inside on database.
	 *
	 * @return string
	 */
	final private function _getUniquePrefix() {
		// FIXME: see #115 - Move the syntax check for LF_SQL_UNIQUE_PREFIX
		// this also recognizes whether the configuration is empty
		if (preg_match('/^[0-9a-zA-Z]+$/', LF_SQL_UNIQUE_PREFIX)) {
			return LF_SQL_UNIQUE_PREFIX.'_';
		}
	}


	//	final private function getSQLColumns($omitId = false, $asArray = false) {
	//		$tableName = $this->getSQLTableName();
	//		$result = "";
	//
	//		if (!$omitId) {
	//			if ($asArray) {
	//				$result = array(SQL::toSysId($tableName).
	//					'.'.SQL::toSysId('id'));
	//			} else {
	//				$result = SQL::toSysId($tableName).
	//					'.'.SQL::toSysId('id').
	//					' AS '.SQL::toSysId($tableName.'_id');
	//			}
	//		}
	//
	//		foreach (array_keys($this->_fields) as $key) {
	//			if ($asArray) {
	//				$result[] = SQL::toSysId($tableName).'.'.SQL::toSysId($key);
	//			} else {
	//				$result .= ", ".SQL::toSysId($tableName).'.'.SQL::toSysId($key).
	//					' AS '.SQL::toSysId($tableName.'_'.$key);
	//			}
	//		}
	//
	//		if (!$asArray && $omitId) {
	//			// if the result is a string, and the id was omitted, remove the
	//			// initial comma and space
	//			$result = substr($result, 2);
	//		}
	//
	//		return $result;
	//	}

	/**
	 * Empty all fields the Model has, and reset id to null
	 */
	final public function clear() {
		foreach ($this->_fields as $field) {
			unset($field);
		}

		$this->_fields = array();
		$this->id = null;
		$this->_retrieved = false;
		$this->_cachedTableName = null;
	}

	public function __toString() {
		return 'Model:'.get_class($this).'['.($this->id != null ? $this->id : '#').']';
	}
}
