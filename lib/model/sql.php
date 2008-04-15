<?php
/**
 * A class to abstract different RDBMS:es under one API.
 *
 * I'm going with the NIH-syndrome here, to be as free of requirements as possible.
 * There would've been several nice PEAR/PECL add-ons, but they can't be relied on.
 * RDBMS support must be provided by PHP.
 * 
 * The class is going to be designed SQL-y (as opposed to Model-y) to be as future
 * proof as possible. The responsibility of being SQL compliant is passed to
 * the Fields. If a field needs some special SQL support, it is naturally
 * added as needed.
 * 
 * If a method does what you want to do SQL-wise, always prefer the method instead
 * of doing it the raw way. This class is designed to know best.
 * 
 * This is an almost generic SQL abstraction. LightFrame-specific optimizations
 * are made, although it could be made generic with some tweaking.
 * 
 * Persistent connections?
 * 
 */
class SQL {
	/**
	 * SQL connection resource
	 *
	 * @var resource
	 */
	private $connection;
	/*
	 * $id isn't supported right now, as postgresql doesn't have native (simple) support for it
	 * and I can't be bothered with doing it the hard way.
	 */
//	private $id;
	/**
	 * The result of a previous query
	 *
	 * @var array
	 */
	private $result;
	/**
	 * The previous query
	 *
	 * @var string
	 */
	private $query;
	/**
	 * The amount of affected rows from the previous query
	 *
	 * @var int
	 */
	private $rows;
	/**
	 * database version
	 *
	 * @var string
	 */
	private $version;
	
	/**
	 * Open database connection
	 * 
	 */
	function __construct() {
		switch (LF_SQL_RDBMS) {
			
			case 'mysql':
				if (!function_exists('mysql_connect')) {
					trigger_error('MySQL isn\'t supported by the server\'s PHP');
				}
				$this->connection = mysql_connect(LF_SQL_HOST, LF_SQL_USER, LF_SQL_PASS);
				mysql_select_db(LF_SQL_DBNAME);
				$this->version = mysql_get_server_info();
				break;
				
			case 'pgsql':
				if (!function_exists('pg_connect')) {
					trigger_error('PostgreSQL isn\'t supported by the server\'s PHP');
				}
				$db = preg_match('!(?P<host>.*)(:(?<port>[0-9]+))?!', LF_SQL_HOST, $matches);
				$connect =  "host={$db['host']} ";
				$connect .= isset($db['port']) ? "port={$db['port']} " : '';
				$connect .= 'user='.LF_SQL_USER.' ';
				$connect .= 'pass='.LF_SQL_PASS.' ';
				$connect .= 'dbname='.LF_SQL_DBNAME;
				$this->connection = pg_connect($connect);
				$this->version = pg_version();
				$this->version = $this->version['server_version'];
				break;
			
			case 'sqlite':
				if (!function_exists('sqlite_popen')) {
					trigger_error('SQLite isn\'t supported by the server\'s PHP');
				}
				
				$this->connection = sqlite_popen(LF_SQL_HOST, 0666, $error);
				if (!$this->connection) {
					trigger_error($error);
				}
				break;
				
			default: 
				trigger_error('Database type \''.LF_SQL_RDBMS.'\' is not supported by LightFrame');
		}
		
		if ($this->connection === false) {
			throw new DBConnectionException('Could not connect to database');
		}
	}
	
	/**
	 * Send a query to SQL and return an associative array of the result
	 *
	 * @param string $query
	 * @return array
	 */
	function query($query) {
		$this->result = array();
		$this->query = $query;
		$this->rows = null;
		$tempResult = null;

		switch (LF_SQL_RDBMS) {
			
			case 'mysql':
				$tempResult = mysql_query($query);
				
				if ($tempResult === true) {
					// the query was a successful insert/update/drop/...
					$this->rows = mysql_affected_rows();
				}
				elseif ($tempResult !== false) {
					// the query was successful
					while ($row = mysql_fetch_assoc($tempResult)) {
						$this->result[] = $row;
					}
					$this->rows = mysql_num_rows($tempResult);
				}
				else {
					throw new QueryErrorException(mysql_error()." - ".$query);
				}
				break;
			
			case 'pgsql':
				$tempResult = pg_query($query);
				while ($this->result[] = $this->result = pg_fetch_assoc($tempResult)) {
					// empty
				}
				
				if (pg_errno()) {
					throw new QueryErrorException(pg_error().' - '.$query);
				}
				
				if ($this->result) {
					$this->rows = pg_num_rows($tempResult);
				}
				break;
				
			case 'sqlite':
				$tempResult = sqlite_array_query($this->connection, $query, SQLITE_ASSOC, $error);
				if ($tempResult === false) {
					throw new QueryErrorException($error.' - '.$query);
				}
				
				$this->result = $tempResult;
				break;
				
			default:
				trigger_error('RDBMS support not complete');
		}
		
		return $this->result;
	}
	
	/**
	 * Combine data from a model and return the matching SQL-query to create the table
	 * 
	 * Default primary key is "id integer".
	 *
	 * @param string $name table name
	 * @param array $cols additional columns ("colname:type:typearguments")
	 * @return string The SQL query for creating a model
	 */
	static function createTableSQL($tableName, $cols) {
		$SQL = "CREATE TABLE IF NOT EXISTS ".SQL::toSysId($tableName)."(";
		
		// Create sequence for primary key for engines not using autoincrementing
		switch (LF_SQL_RDBMS) {
			case 'pgsql': $SQL .= "CREATE SEQUENCE {$tableName}_id_seq;";
		}
		
		/*
		 * Indexing (with INDEX-keyword) isn't used right now, as they expand the
		 * database size. LightFrame is (currently) designed for small and medium-sized
		 * projects, thus the lack of speed is hardly noticeable from the lag from PHP
		 */
		switch (LF_SQL_RDBMS) {
			case 'mysql': $SQL .= "id integer NOT NULL AUTO_INCREMENT,"; break;
			case 'pgsql': $SQL .= "id serial,"; break;
			case 'sqlite': $SQL .= "id integer NOT NULL AUTOINCREMENT,"; break;
			default: trigger_error('RMDBS error');
		}
		
		// Create the column definition string
		$tempArray = array();
		foreach ($cols as $column) {
			$temp = SQL::toSysId($column['name']).' '.$column['type'];
			if (isset($column['arg'])) {
				$temp .= '('.$column['arg'].')';
			}
			$tempArray[] = $temp;
		}
		$SQL .= implode(",", $tempArray);
		
		// define primary keys and foreign keys
		$SQL .= ", PRIMARY KEY (id)";
		
		$SQL .= ')';
		
		// MySQL likes innodb when dealing with foreign keys
		switch (LF_SQL_RDBMS) {
			case 'mysql': $SQL .= 'ENGINE=INNODB';
		}
		
		$SQL .= ';';

		return $SQL;
	}

	/**
	 * Get the last result of a SQL query
	 *
	 * @return array
	 */
	function getLastResult() {
		return $this->result;
	}

	/**
	 * How many rows were affected with the last query
	 *
	 * @return int
	 */
	function getLastRows() {
		return $this->rows;
	}
	
	/**
	 * Get the last SQL query
	 *
	 * @return string
	 */
	function getLastQuery() {
		return $this->query;
	}
	
	/**
	 * Get the id value of the row just saved
	 * 
	 * @param string $tableName SQL table name of the model
	 * @return int
	 */
	function getLastID($tableName) {
		switch (LF_SQL_RDBMS) {
			case 'mysql': return mysql_insert_id(); break;
			case 'sqlite': return sqlite_last_insert_rowid(); break;
			case 'pgsql':
				// currval() seems to do a full sequence scan, circumcode it, compatible for pgsql < 8.2
				// http://radix.twistedmatrix.com/2007/11/dont-use-postgress-currval.html
				$id = $this->query('SELECT id FROM '.$tableName.' WHERE id = (SELECT currval(\''.$tableName.'\'))');
				return (int)$id['id'];
			default:
				trigger_error('getLastID() is not yet implemented for '.LF_SQL_RDBMS);
		}
	}
	
	/**
	 * Escape strings correctly on a RDBMS basis
	 * 
	 * Appends also the single quotes
	 *
	 * @param string $string
	 * @return string
	 */
	function escape($string) {
		switch (LF_SQL_RDBMS) {
			case 'mysql':
				return "'".mysql_real_escape_string($string)."'";
				break;
				
			case 'pgsql':
				return "'".pg_escape_string($string)."'";
				break;
			
			case 'sqlite':
				return "'".sqlite_escape_string($string)."'";
				break;

			default:
				trigger_error('RDBMS support not complete');
		}
	}
	
	/**
	 * Converts a string to a system identifier
	 * 
	 * For example, if a column is named 'customer-id', it needs to be escaped somehow.
	 * MySQL uses backticks (`) but SQL standards say double quotes (").
	 *
	 * @param string $string
	 * @return string
	 */
	static function toSysId($string) {
		switch (LF_SQL_RDBMS) {
			case 'mysql': return "`$string`"; break;
			case 'pgsql': return "\"$string\""; break;
			case 'sqlite': return "\"$string\""; break;
			default: trigger_error('RDBMS support not complete');
		}
	}
	
	/**
	 * Format the SQL query into something pretty
	 * 
	 * Not yet implemented, returns the input directly.
	 *
	 * @param string $string
	 * @return string
	 */
	static function format($string) {
		return $string;
	}

	/**
	 * Convert Model::skip and Model::limit into a possible LIMIT-clause
	 *
	 * @param int $skip
	 * @param int $limit
	 * @return string
	 */
	static function limit($skip, $limit) {
		if (!$skip && !$limit) return '';
		
		switch (LF_SQL_RDBMS) {
			case 'mysql': return " LIMIT $skip, $limit"; break;
			default: return " LIMIT $limit OFFSET $skip"; // works for pgsql+sqlite
		}
	}
	
	/**
	 * Convert Model::filters and Model::exclusions into a possible WHERE-clause
	 * 
	 * Both arguments are 2-dimensional arrays, where the second dimensions are
	 * imploded together with "AND" and later
	 * on those pieces are imploded with
	 * "OR", resulting in a single WHERE-clause.
	 *
	 * @param array[][] $where
	 * @param array[][] $wherenot
	 * @return string
	 */
	static function where($where, $wherenot) {
		if (!$where && !$wherenot) return '';

		foreach ($where as $id => $operation) {
			$where[$id] = '('.implode(' AND ',$operation).')';
		}
		$where = $where ? ' ('.implode(' OR ', $where).')' : '';
		
		foreach ($wherenot as $id => $operation) {
			$wherenot[$id] = '('.implode(' AND ',$operation).')';
		}
		$wherenot = $wherenot ? ' NOT ('.implode(' OR ', $wherenot).')' : '';
		
		return ' WHERE'.$where.(($where && $wherenot) ? ' AND':'').$wherenot;
	}
	
	/**
	 * convert array[col_a] = col_b into "col_a_1 as col_b_1, col_a_2 as col_b_2,..."
	 *
	 * @param array $array
	 * @return string
	 */
	static function cols($array) {
		$as = array();
		foreach ($array as $col1 => $col2) {
			$as[] = $col1.' AS '.$col2;
		}
		return implode (',', $as);
	}
}

// in case the sql is not used from LightFrame.php 
if (!function_exists('_errorHandler')) {
	function _simpleErrorHandler($errno, $errstr, $errfile, $errline) {
		die("SQL error: ($errno) $errstr");
	}
	set_error_handler('_simpleErrorHandler');
}
?>