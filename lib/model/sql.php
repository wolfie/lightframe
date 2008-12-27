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
	public function __construct() {
		$e = null;


		switch (LF_SQL_RDBMS) {

			case 'mysql':
				if (!function_exists('mysql_connect')) {
					trigger_error('MySQL isn\'t supported by the server\'s PHP');
				}
				$this->connection = mysql_pconnect(LF_SQL_HOST, LF_SQL_USER, LF_SQL_PASS);
				if (!mysql_select_db(LF_SQL_DBNAME)) {
					trigger_error(mysql_error());
				}

				$this->version = mysql_get_server_info($this->connection);
				break;

			case 'pgsql':

				if (!function_exists('pg_connect')) {
					trigger_error('PostgreSQL isn\'t supported by the server\'s PHP');
				}

				if (preg_match('!(?P<host>.*)(:(?<port>[0-9]+))?!', LF_SQL_HOST, $matches) !== 1) {
					trigger_error('LF_SQL_HOST setting is misformatted (expected \'host\' or \'host:port\').');
				}

				$connectString =  'host='.$matches['host'].' ';
				$connectString .= isset($matches['port']) ? 'port='.$matches['port'].' ' : '';
				$connectString .= 'user='.LF_SQL_USER.' ';
				$connectString .= 'password='.LF_SQL_PASS.' ';
				$connectString .= 'dbname='.LF_SQL_DBNAME;

				$this->connection = pg_connect($connectString);
				$this->version = pg_version();
				$this->version = isset($this->version['server']) ? $this->version['server'] : 'unknown';

				break;

			case 'sqlite':
				if (!class_exists('PDO', false)) {
					trigger_error('SQLite support requires the PDO extension');
				}

				if (!in_array('sqlite',PDO::getAvailableDrivers())) {
					trigger_error('SQLite is not supported by the server\'s PDO configuration');
				}

				$dsn = 'sqlite:/'.LF_SQL_HOST;

				try {
					$this->connection = new PDO($dsn);
				}
				catch (PDOException $e) {
					trigger_error($e->getMessage().' - '.$dsn.' - SQLite requires write access to both the database file and the directory containing it.');
				}
				break;

			default:
				trigger_error('Database type '.LF_SQL_RDBMS.' is not supported');
		}

		if ($this->connection === false) {
			throw new DBConnectionException('Could not connect to database');
		}
	}

	public function __destruct() {
		switch (LF_SQL_RDBMS) {
			case 'mysql':
				// is the connection still open?
				if (is_resource($this->connection)) {
					mysql_close($this->connection);
				}
				unset($this->connection);
				break;
			case 'pgsql':
				// is the connection still open?
				if (is_resource($this->connection)) {
					pg_close($this->connection);
				}
				unset($this->connection);
				break;
			case 'sqlite':
				unset($this->connection);
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

				$tempResult = mysql_query($query, $this->connection);

				if ($tempResult === true) {
					// the query was a successful insert/update/drop/...
					$this->rows = mysql_affected_rows($this->connection);
				}
				elseif ($tempResult !== false) {
					// the query was successful
					while ($row = mysql_fetch_assoc($tempResult)) {
						$this->result[] = $row;
					}
					$this->rows = mysql_num_rows($tempResult);
					
					mysql_free_result($tempResult);
				}
				else {
					throw new QueryErrorException(mysql_error().' - '.$query);
				}
				break;

			case 'pgsql':
				$tempResult = pg_query($query);

				if ($tempResult === false) {
					throw new QueryErrorException(pg_result_error($tempResult).' - '.$query);
				}

				$this->result = array();
				while ($row = pg_fetch_assoc($tempResult)) {
					$this->result[] = $row;
				}

				if ($this->result) {
					$this->rows = pg_num_rows($tempResult);
				}
				break;

			case 'sqlite':
				$tempResult = $this->connection->query($query);
				if ($tempResult === false) {
					$error = $this->connection->errorInfo();
					throw new QueryErrorException($error[1].': '.$error[2].' -- '.$query);
				}
				elseif ($tempResult->errorCode() !== '00000') {
					$error = $tempResult->errorInfo();
					throw new QueryErrorException($error[1].': '.$error[2].' -- '.$query);
				}

				$this->result = $tempResult->fetchAll(PDO::FETCH_ASSOC);
				$this->rows = $tempResult->rowCount();
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
		$SQL = 'CREATE TABLE IF NOT EXISTS '.SQL::toSysId($tableName).'(';

		// Create sequence for primary key for engines not using autoincrementing
		switch (LF_SQL_RDBMS) {
			case 'pgsql': $SQL .= 'CREATE SEQUENCE '.$tableName.'_id_seq;';
		}

		/*
		 * Indexing (with INDEX-keyword) isn't used right now, as they expand the
		 * database size. LightFrame is (currently) designed for small and medium-sized
		 * projects, thus the lack of speed is hardly noticeable from the lag from PHP
		 */
		switch (LF_SQL_RDBMS) {
			case 'mysql': $SQL .= 'id integer NOT NULL AUTO_INCREMENT,'; break;
			case 'pgsql': $SQL .= 'id serial,'; break;
			case 'sqlite': $SQL .= 'id INTEGER PRIMARY KEY AUTOINCREMENT,'; break;
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
		$SQL .= implode(',', $tempArray);

		// define primary keys and foreign keys
		if (LF_SQL_RDBMS !== 'sqlite') {
			$SQL .= ', PRIMARY KEY (id)';
		}

		$SQL .= ')';

		// MySQL likes innodb when dealing with foreign keys
		switch (LF_SQL_RDBMS) {
			case 'mysql': $SQL .= 'ENGINE=INNODB';
		}

		$SQL .= ';';

		return $SQL;
	}

	/**
   * Get the SQL query to get all user tables in a database
	 *
	 * @return string
	 */
	static function getTablesSQL() {
		switch (LF_SQL_RDBMS) {
			case 'sqlite': return 'SELECT name FROM sqlite_master WHERE type=\'table\' AND name NOT LIKE \'sqlite_%\' ORDER BY name;';
			case 'mysql': // "SHOW TABLES;" should work, but it's untested!
			case 'pgsql': // "SELECT datname FROM pg_database;" should work, but it's untested!
				trigger_error('See '.__FILE__.' at '.__LINE__);
			default: trigger_error('DB type not supported');

		}
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
			case 'mysql': return mysql_insert_id($this->connection); break;
			case 'sqlite': return (int)$this->connection->lastInsertId(); break;
			case 'pgsql':
				$result = $this->query('SELECT last_value FROM "'.$tableName.'_id_seq"');
				$result = $result[0];

				if (!isset($result['last_value']) || !is_numeric($result['last_value'])) {
					trigger_error('PostgreSQL didn\'t return correct information on sequence "'.$tableName.'_id_seq"');
				} else {
					return (int)$result['last_value'];
				}
				
				break;
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
				return '\''.mysql_real_escape_string($string).'\'';
				break;

			case 'pgsql':
				return '\''.pg_escape_string($string).'\'';
				break;

			case 'sqlite':
				return '\''.sqlite_escape_string($string).'\'';
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
			case 'mysql': return '`'.$string.'`'; break;
			case 'pgsql': return '"'.$string.'"'; break;
			case 'sqlite': return '"'.$string.'"'; break;
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
			case 'mysql': return ' LIMIT '.$skip.', '.$limit; break;
			default: return ' LIMIT '.$limit.' OFFSET '.$skip; // works for pgsql+sqlite
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
		die('($errno) $errstr at $errfile:$errline'.PHP_EOL);
	}
	set_error_handler('_simpleErrorHandler');
}