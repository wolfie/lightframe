<?php
/**
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License v2.0
 * @author Henrik Paul
 */

require_once 'field.php'; // autoload doesn't handle this properly
require_once 'sql.php'; // neither this

/*
 * User models aren't brought in automatically yet. It could be done within
 * autoloading, it could be done by having the user explicitly require the 
 * modelfile (rather not) or by some other way.
 * 
 * During development, models need to be explicitly required from, say, views.php
 */

/*
 * TODO: integrate i18n directly to models as a default.
 * 
 * This would be achieved by having each text/varchar field as a 'dynamic' foreign key, a
 * default language defined (which is accessed if no specific language is defined)
 * and a list of supported languages. No built-in list of languages is provided,
 * a language can be called "foo" if the user so wishes. Each translation
 * produces a left join on its own.
 * 
 * languages defined: {en, fi, se}
 * thing table: name int+pk, created date
 * thing_names table: id int+pk, language chr+pk, value text
 * a thing: name=1, created=2008-02-15
 * names: id=1, language='en', value='Car'
 * names: id=1, language='fi', value='Auto'
 * names: id=1, language='se', value='Bil'
 * 
 * SELECT * FROM thing 
 *     LEFT JOIN thing_names ON thing.name = thing_names.id
 *     WHERE language='$lang';
 * 
 */

/*
 * LightFrame abstracts the database in two distinct parts: single results
 * and multiple results. This is a natural separation, about half of the time
 * you deal with single entries, and the other half you deal with a list of 
 * entries. In cases like entry deletion, it's good to be assured that you
 * are in fact deleting only one entry, and not several by mistake.
 * 
 * When interfacing with a specific model, you are guaranteed to get a maximum
 * of one result.
 * 
 * When interfacing with Entries, you may get multiple results. Still, one
 * or zero results are possible, but treated still as a collection.
 */



/**
 * A superclass for models.
 * 
 * The model by itself does nothing. A model object is extended into some user-defined
 * model for a view. That model is inserted with members that represent data types
 * to be used.
 * 
 * This model object's point is to have the accessors built in - the user's
 * version only has some data types and nothing else.
 *
 */
abstract class Model implements Countable, Iterator {
	private $fields = array();      // Properties defined by the user for the model
	private $sqlTableName = '';     // The SQL table name. Use _getSQLTableName() instead of accessing this directly!
	public $_order = '';            // The default order for this method (will probably transform to some sort of collection of defaults/meta-actions)
	public $_file = null;           // the user is forced to give __FILE__ here.
	public $id;
	
	/**
	 * Modify fields
	 * 
	 * If the field doesn't exist yet, create a new field with the given object.
	 * If it exists, modify the value of that field.
	 *
	 * @param string $name
	 * @param array $field
	 */
	final function __set($name, $value) {
		if (!isset($this->fields[$name])) {
			$this->fields[$name] = $value;
		}
		else {
			$this->fields[$name]->set($value);
		}
	}
	
	/**
	 * Retrieve fields from the model
	 * 
	 * If the query hasn't been evaluated, get the field itself. If it has been
	 * evaluated, get the value in the latest result
	 *
	 * @param string $name
	 * @return object field
	 */
	final function __get($name) {
		// $name is a field inside this model
		if (isset($this->fields[$name])) {
			return $this->fields[$name];
		}
		
		// $name refers to a foreign key inside this model
		elseif (strpos($name, '->') !== false) {
			list($name, $rest) = explode('->', $name, 2);
			if (isset($this->fields[$name])) {
				if (!($this->fields[$name] instanceof ForeignKeyField)) {
					throw new FieldNotFoundException("$name is not a referencing field, in model '".get_class($this)."', application '{$GLOBALS['app']}'");
				}
				return $this->fields[$name]->$rest;
			}
		}

		// $name doesn't exist in this model
		throw new FieldNotFoundException("'$name' is not a field, in model '".get_class($this)."', application '{$GLOBALS['app']}'");
		die();
	}
	
	/**
	 * Initialize a new instance of a model (i.e. prepare for an INSERT)
	 *
	 * You can either give an enumerated array, where the values are given in an
	 * as-defined order, or they can given in a named order, where the values
	 * can be defined in a random order. These cannot be combined.
	 * 
	 * @param array $array
	 */
	final function create($array, $fromSQL=false) {
		reset($this->fields);
		
		foreach ($array as $key => $value) {
			if (!is_string($key)) {
				list($key,) = each($this->fields);
			}
				
			if ($key === 'id') {
				$this->id = (int)$value;
				continue;
			}
			
			if ($fromSQL) {
				/*
				 * TODO: populate data through foreign keys
				 */
				if (strpos($key,'->') !== false) {
					continue;
				}
				
				$value = $this->fields[$key]->fromSQL($value);
			}
			$this->fields[$key]->set($value);
		}
	}

	
	/**
	 * Retrieve model contents from the database
	 *
	 * @param int $id
	 * @return object self
	 */
	final function get($id) {
		if ((int)$id !== $id) {
			throw new Exception('$id must be an integer (\''.$id.'\' given)');
		}
		
		$sql = new SQL();
		$query = 'SELECT * FROM '.($this->_getSQLTableName()).' WHERE id = '.$id.' LIMIT 1';
		$result = $sql->query($query);

		if (count($result) === 0) {
			throw new EmptyResultException(get_class($this));
		}

		$result = $result[0];
		$this->create($result, true);

		return $this;
	}
	
	/**
	 * Commit the contained data into the SQL
	 */
	final function save() {
		$values = array(); // the values in each field
		$keys = array(); // the field's name in the SQL-table
		$sql = new SQL();
		
		// gather data as SQL queries
		foreach ($this->fields as $key => $field) {
			$keys[] = SQL::toSysId($key);
			$values[] = $field->sql();
		}
		
		// check wether it's an INSERT or an UPDATE
		if ($this->id) {
			// check if the id already exists, to avoid SQL errors.
			$exists = $sql->query('SELECT count(*) AS count FROM '.$this->_getSQLTableName().' WHERE id = '.$this->id);
			if ($exists['count'] > 1) {
				throw new Exception ('This really shouldn\'t happen!');
			}
			$exists = $exists['count'] === "1";
		}
		
		// it's an update
		if ($this->id && !$exists) {
			$set = array();
			for ($i=0; $i<count($keys); $i++) {
				$set[] = $keys[$i].'='.$values[$i];
			}
			$set = implode(',', $set);
			$query = 'UPDATE '.$this->_getSQLTableName().' SET '.$set.' WHERE id = '.$this->id;
		}
		
		// it's an insert
		else {
			$query = 'INSERT INTO '.$this->_getSQLTableName().' ('.implode(',',$keys).') VALUES ('.implode(',',$values).')';
		}

		// commit the query
		$sql->query($query);
		
		// store the id for the inserted SQL row
		if (!$this->id) {
			$this->id = $sql->getLastID($this->_getSQLTableName());
		}
	}
	
	/**
	 * Delete the current record (from the database if id is given)
	 */
	final function delete() {
		if (!$this->id) {
			throw new Exception("Trying to delete an instance of ".get_class($this)." but no ID is specified");
		}
		$sql = new SQL();
		$sql->query("DELETE FROM ".$this->_getSQLTableName()." WHERE id = {$this->id}");
		
		// faux-selfdestruct
		$this->id = null;
		foreach ($this->fields as $key => $field) {
			$field->set();
		}
	}
	
	/**
	 * In case the object is echoed
	 * 
	 * Can be overridden
	 * 
	 * @return string Model's name
	 */
	function __toString() {
		return '[Model: '.get_class($this).']';
	}
	
	/**
	 * The SQL required to create the table needed for the model
	 * 
	 * @return string SQL query
	 */
	final function _getSQLCreateTable() {
		$tableName = $this->_getSQLTableName();
		foreach ($this->fields as $fieldName => $field) {
			$temp['type'] = $field->_getSQLInfo();
			$temp['name'] = $fieldName;
			$fields[] = $temp;
		}
		return SQL::createTableSQL($tableName, $fields);
	}
	
	/**
	 * Get the name used in SQL tables, unescaped
	 *
	 * @return string
	 */
	final function _getSQLTableName() {
		if (!$this->sqlTableName) {
			/*
			 * while this works even if the current models.php is included from another
			 * app's views.php, this will break if there are more than one models.php
			 * included. I guess it's a lesser evil of the two.
			 *//*
			foreach (get_included_files() as $file) {
				if (preg_match('!'.addslashes(LF_APPS_PATH).'(.*)/models.php!', $file, $matches)) {
//					$this->sqlTableName = SQL::toSysId(strtolower($matches[1].'_'.get_class($this)));
					$this->sqlTableName = strtolower($matches[1].'_'.get_class($this));
					break;
				}
			*/
			if ($this->_file === null) {
				trigger_error('User model "'.get_class($this).'" needs to define "$this->_file = __FILE__"');
			}
			else {
				$this->sqlTableName = basename(dirname($this->_file)).'_'.get_class($this);
			}
		}

		return $this->sqlTableName;
	}
	
	/**
	 * Give the fields to an Entries object
	 *
	 * @return array
	 */
	final function _getFields() {
		return $this->fields;
	}
	
	/**
	 * Return the names of the fields in a model as an enumerated array
	 *
	 * @return array
	 */
	final function asArray() {
		$return = array();
		foreach ($this->fields as $key => $val) {
			$return[] = $key;
		}
		return $return;
	}
	
	/**
	 * Return the amount of fields defined in the model
	 *
	 * @return int
	 */
	final function count() {
		return count($this->fields);
	}
	
	/**
	 * Rewind the pointer of $this->fields array
	 *
	 */
	final function rewind() {
		reset($this->fields);
	}
	
	/**
	 * Return the current value of the field
	 *
	 * @return Field
	 */
	final function current() {
		return current($this->fields);
	}
	
	/**
	 * Move the pointer of $this->fields forward and return the result
	 *
	 * @return Field
	 */
	final function next() {
		return next($this->fields);
	}
	
	/**
	 * Return the current key of $this->fields (field name)
	 *
	 * @return string
	 */	
	final function key() {
		return key($this->fields);
	}
	
	/**
	 * Does the current pointer point to a Field?
	 *
	 * @return bool
	 */
	final function valid() {
		return (current($this->fields) instanceof Field);
	}
}


/*
 * The entries contain the models themselves. When the query is executed, the
 * fields are inserted with the results from the SQL query. Foreign keys are
 * additional models, where the foreign key id is preinserted in the model.
 */

class Entries implements Countable, Iterator{
	private $filters = array();     // SQL filters (WHERE clause elements)
	private $exclusions = array();  // SQL exclusions (WHERE NOT clause elements)
	private $limit = 0;             // Limit to how many results
	private $skip = 0;              // Skip how many first results
	private $resultArray = array(); // The result of the last SQL query as an array
	private $resultModel = array(); // Cached results by __get();
	private $isEvaluated = false;   // Has the latest SQL query been processed?
	private $model;                 // A reference model for field information
	private $order;                 // How to order the queries by default
	private $join = array();        // Which tables to join with
	private $fields = array();      // Which fields are included, and their translations
	
	/**
	 * Initialize an all-encompassing Entry
	 *
	 * @param string $modelName callback string to a Model
	 */
	final function __construct($modelName) {
		if (!class_exists($modelName, false)) {
			trigger_error("Can't get entries for '$modelName' - no such model defined");
		}
		
		$this->model = new $modelName();
		$this->_addFieldsFrom($this->model);
		$this->orderBy($this->model->_order);
	}
	
	/**
	 * In case the entries object is echoed
	 *
	 * @return string
	 */
	final function __toString() {
		return '[Entries: '.get_class($this->model).']';
	}
	
	/**
	 * Access a particular result
	 * 
	 * This uses $this->resultModel array in lazy instantiation of the queried value.
	 * A trade-off between more memory and less time on consecutive accesses.
	 * 
	 * It could be debated whether it is typical to do several calls to a single
	 * value during one execution pass. If it's seen as unnecessary, the array
	 * can be disposed of and populate a temporary variable with results from
	 * the result.
	 * 
	 * This is used like $entries->$i or $entries->{2} (where $i is an integer)
	 *
	 * @param int $name
	 * @return object Model
	 */
	final function __get($id) {
		$this->get();
		
		if (is_numeric($id) && (int)$id < count($this->resultArray)) {
			if (!isset($this->resultModel[$id])) {
				$this->resultModel[$id] = clone $this->model;
				$this->resultModel[$id]->create($this->resultArray[$id], true);
			}
			return $this->resultModel[$id];
//			return $this->result[(int)$id];
		}
		else {
			throw new Exception('Invalid index \''.$id.'\' for Entries: '.get_class($this->model));
		}
	}
	
	/**
	 * Add fields from a given model to the result fieldset
	 * 
	 * When joining several tables together, we need to know which values to take
	 * from where, and ultimately (not in this method) put in the right referencing
	 * fields (referenced models). See Entries::__get(), Entries::_evaluate() and 
	 * SQL::cols()
	 *
	 * @param Model $model
	 * @param mixed $reference
	 */
	final private function _addFieldsFrom($model, $reference=null) {
		$tablename = $model->_getSQLTableName();
		$fields = $model->_getFields();
		
		$this->fields[$tablename.'.id'] = SQL::toSysId((($reference !== null) ? $reference.'->id' : 'id'));
		foreach ($fields as $field => $null) {
			$asField = SQL::toSysId((($reference !== null) ? $reference.'->'.$field : $field));
			$this->fields[$tablename.'.'.SQL::toSysId($field)] = $asField;
		}
	}
	
	/**
	 * Parse a freeform string and break it down into logical components
	 *
	 * @param string $string
	 * @return array of logical components
	 */
	final private function _parseSelections($string) {
		$this->isEvaluated = false;
		
		// preg-compatible parsing info - order matters
		$operator['<='] = '\<=|=\<|(is )??less( than)?? (or )??equals( to)??';
		$operator['>='] = '\>\=|=\>|(is )??greater( than)?? (or )??equals( to)??';
		$operator['<'] = '\<|(is )??less( than)??';
		$operator['>'] = '\>|(is )??greater( than)??';
		$operator['NOT BETWEEN'] = '\!\[\]|(is )??not (in )??between';
		$operator['BETWEEN'] = '\[\]|(is )??(in )??between';
		$operator['<>'] = '\!\=|\<\>|is not|equals not( to)??|not equals( to)??|doesn\'t equal( to)??|does not equal( to)??';
		$operator['='] = '=|is|equals( to)??';
		
		$pregop = implode('|', $operator);
		$parts = explode(' and ', strtolower($string));
		$elements = array();
		foreach ($operator as $key => $op) {
			$replaceOp[] = "!$op!U";
			$replaceToOp[] = $key;
		}

		foreach ($parts as $part) {
			preg_match('!^(?P<field>[^ ]+) (?P<op>'.$pregop.') (?P<value>.+)$!Ui', $part, $matches);
			
			if (!isset($matches['op']) || !isset($matches['field']) || !isset($matches['value'])) {
				throw new Exception("\"$part\" seems to be malformatted, in view '{$GLOBALS['view']}'");
			}

			$op = preg_replace ($replaceOp, $replaceToOp, $matches['op'], 1);
			$field = $matches['field'];
			$value = $matches['value'];
			
			// the operator is a BETWEEN operator, handle the value differently
			if ($op === 'BETWEEN' || $op === 'NOT BETWEEN') {
				if (strpos($value, '..') === false) {
					trigger_error('between-operator requires a value with a \'..\'-separator');
				}
				list($val1, $val2) = explode('..', $value, 2);
				$value = 
				  $this->model->$field->fromToFilter($val1)
					." AND "
					.$this->model->$field->fromToFilter($val2);
			}
			
			// the operator requires no special handling of the value
			else {
				$value = $this->model->$field->fromToFilter($value);
			}
			
			// if it's a date field, we need to 
			if (strpos($field, ':') !== false) {
				list($field, $dateOp) = explode(':',$field,2);
				
				if (!($this->model->$field instanceof TimeField)) {
					trigger_error('\''.$field.'\' is not a time related field');
				}
				
				echo "<pre>date unfinished\n";var_dump($field, $dateOp);die();
			}
			
			// if it's a foreign key, we need to add a join table to it, and also
			// change the field's table name.
			elseif (strpos($field, '->') !== false) {
				$parts = explode('->', $field);
				$field = SQL::toSysId(array_pop($parts));
				$foreignKey = SQL::toSysId($parts[0]);
				
				$model = implode('->',$parts);
				// $model can contain 'arrows' and it's completely valid, thanks to Model::__get()
				$tablename = $this->model->$model->_getSQLTableName();
				
				// this /could/ be done in SQL just like SQL::where() etc...
				$this->join[$tablename] = " LEFT JOIN $tablename ON $tablename.id = ".$this->model->_getSQLTableName().".$foreignKey";
				
				$this->_addFieldsFrom($this->model->$model, $model);
			}
			
			// it's not a foreign key
			else {
				$tablename = $this->model->_getSQLTableName();
				$field = SQL::toSysId($field);
			}
			
			$elements[] = "$tablename.$field $op $value";
		}
		return $elements;
	}
	
		/**
	 * The 'magic' SQL evaluation method.
	 * 
	 * The method can always be called - it checks if the search criteria have
	 * changed since last time.
	 *
	 */
	final private function _evaluate() {
		if ($this->isEvaluated) {
			return; // do nothing, it's already evaluated
		}
		
		$sql = new SQL();
		
		$query = 'SELECT '.SQL::cols($this->fields);
		$query .= ' FROM '.$this->model->_getSQLTableName();
		$query .= implode('',$this->join);
		$query .= SQL::where($this->filters, $this->exclusions);
		$query .= $this->order;
		$query .= SQL::limit($this->skip, $this->limit);
		
//		die($query);
		$this->isEvaluated = true;
		$this->resultArray = $sql->query($query);
	}
	
	
	/*
	 * Refining and narrowing down the results
	 */
	
	/**
	 * Add logical-OR filters to the SQL query with 'freeform' string
	 * 
	 * @param string $string
	 * @return object self
	 */
	final function with($string) {
		$this->isEvaluated = false;
		$this->resultArray = array();
		$this->resultModel = array();
		$this->filters[] = $this->_parseSelections($string);
		return $this;
	}
	
	/**
	 * Add logical OR NOT-filters to the SQL query with 'freeform' string
	 *
	 * @param string $string
	 * @return object self
	 */
	final function without($string) {
		$this->isEvaluated = false;
		$this->resultArray = array();
		$this->resultModel = array();
		$this->exclusions[] = $this->_parseSelections($string);
		return $this;
	}
	
	/**
	 * Limit the retrieved entries
	 *
	 * @param integer $skip
	 * @param integer[optional] $limit
	 * @return object $this
	 */
	final function limit($skip, $limit=null) {
		if ($skip < 0 || $limit < 0) {
			throw new Exception('limit() can\'t accept negative numbers');
		}
		
		if ($limit === null) {
			$limit = $skip;
			$skip = 0;
		}
		
		$this->limit = $limit;
		$this->skip = $skip;

		
		return $this;		
	}
	
	/**
	 * Reset the dataset
	 *
	 * @return object self
	 */
	final function all() {
		$this->filters = array();
		$this->exclusions = array();
		$this->limit = 0;
		$this->skip = 0;
		$this->result = array();
		$this->isEvaluated = false;
		return $this;
	}

	/*
	 * end refining and narrowing
	 */
	
	
	/**
	 * Make sure the results are ordered
	 *
	 * If no argument is passed, the ordering is reset.
	 * 
	 * @param string[optional] $string
	 * @return object self
	 */
	final function orderBy($string='') {
		$parts = array();
		
		if ($string === '') {
			$this->order = '';
			return $this;
		}
		
		// match "field -field field->fk"
		preg_match_all('! ?(-?[^ ]+)!', $string, $matches);
		foreach ($matches[1] as $match) {
			$col = '';
			
			if ($match{0} === '-') {
				$desc = true;
				$match = substr($match, 1);
			}
			else {
				$desc = false;
			}
			
			$this->model->$match; // just call the fields individually, and an exception will be raised if the field isn't matched

			$temp = explode('->', $match);
			$field = array_pop($temp);
			
			// it's a foreign field
			if (count($temp) > 0) {
				$model = implode('->', $temp);
				$col = $this->model->$model->_getSQLTableName();
			}
			
			// it's a native field
			else {
				$col = $this->model->_getSQLTableName();
			}
			
			$col = $col.'.'.SQL::toSysId($field);
			
			if (!isset($this->fields[$col])) {
				var_dump($col, $this->fields);die();
				trigger_error('\''.$match.'\' can not be sorted by, as is not a part of the resulting dataset');
			}
			
			$parts[] = $col.($desc ? ' DESC' : ' ASC');
		}
		
		$this->order = ' ORDER BY '.implode(', ', $parts);
		
		return $this;
	}
	
	/**
	 * SELECT the given dataset
	 *
	 * @return array result
	 */
	final function get() {
		$this->_evaluate();
		return $this;
	}
	
	/**
	 * Get a new (blank) instance of the current Model
	 *
	 * @return Model
	 */
	final function getModel() {
		$model = get_class($this->model);
		return new $model();
	}
	
	/**
	 * DELETE the given dataset
	 */
	final function delete() {
		$table = $this->model->_getSQLTableName();
		
		$query = "DELETE $table.* FROM $table"
			.implode('', $this->join)
			.SQL::where($this->filters, $this->exclusions)
			.SQL::limit($this->skip, $this->limit);

		$SQL = new SQL();
		$SQL->query($query);
	}
	
	/**
	 * Get the SQL result as an array
	 *
	 * @return array
	 */
	final function asArray() {
		$this->get();
		return $this->resultArray;
	}
	
	/*
	 * Iterator stuff for foreach():ing the results
	 */
	
	/**
	 * Initialize the data, lazy SQL call.
	 * 
	 * This is used only if the user foreach:es data from the model. The data
	 * is fetched form the database if data is already not evaluated and/or the
	 * search criteria haven't changed since a possible previous call to foreach.
	 * Otherwise, the existing data-array is just reset()'ed.
	 *
	 */
	final function rewind() {
		$this->get();
		return reset($this->resultArray);
	}
	
	/**
	 * Get the current row
	 *
	 * @return Model
	 */
	final function current() {
		$this->get();
		return $this->__get(key($this->resultArray));
	}
	
	/**
	 * Advance to the next row and get it
	 *
	 * @return Model
	 */
	final function next() {
		$this->get();
		next($this->resultArray);
		if ($this->valid()) {
			return $this->current();
		}
	}
	
	/**
	 * Get the row number
	 *
	 * @return int
	 */
	final function key() {
		$this->get();
		return key($this->resultArray);
	}
	
	/**
	 * Is the pointer pointing to a valid row?
	 *
	 * @return bool
	 */
	final function valid() {
		$this->get();
		return (is_array($this->resultArray) && current($this->resultArray) !== false);
	}
	
	/*
	 * end iterator stuff
	 */
	
	/*
	 * Interface Countable
	 */
	
	/**
	 * Return the amount of rows in the results
	 * 
	 * If the data isn't evaluated, do a SQL query on the size, otherwise count the
	 * data in memory.
	 *
	 * @return int
	 */
	final function count() {
		if ($this->isEvaluated) {
			return count($this->resultArray);
		}
		else {
			$query = "SELECT COUNT(*) c FROM ".$this->model->_getSQLTableName()
			  .implode('',$this->join)
				.SQL::where($this->filters, $this->exclusions)
				.SQL::limit($this->skip, $this->limit);

			$SQL = new SQL();
			$count = $SQL->query($query);
			if ($count === false) { // returned boolean false, we want an integer
				return 0;
			}
			else {
				return (int) $count[0]['c'];
			}
		}
	}
	
	/*
	 * end Countable
	 */	
}
?>