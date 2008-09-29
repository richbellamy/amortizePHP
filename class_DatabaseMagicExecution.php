<?php

/*******************************************
	Copyright Rich Bellamy, RMB Webs, 2008
	Contact: rich@rmbwebs.com

	This file is part of Database Magic.

	Database Magic is free software: you can redistribute it and/or modify
	it under the terms of the GNU Lesser General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	Database Magic is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU Lesser General Public License for more details.

	You should have received a copy of the GNU Lesser General Public License
	along with Database Magic.  If not, see <http://www.gnu.org/licenses/>.
*******************************************/

require_once dirname(__FILE__) . '/../databasemagicconfig.php';

function first_val($arr = array()) {
	if (is_array($arr) && count($arr) > 0) {
		$vals = array_values($arr);
		return $vals[0];
	} else {
		return null;
	}
}

function first_key($arr = array()) {
	if (is_array($arr) && count($arr) > 0) {
		$keys = array_keys($arr);
		return $keys[0];
	} else {
		return null;
	}
}

function dbm_debug($class, $message) {
	if (DBM_DEBUG) {
		echo "<pre class=\"$class\">\n";
		if (is_string($message)) {
			echo $message;
		} else {
			print_r($message);
		}
		echo "\n</pre>\n";
	}
}

if (DBM_DEBUG) { set_error_handler ("dbm_do_backtrace"); }

function dbm_do_backtrace ($one, $two) {
	echo "<pre>\nError {$one}, {$two}\n";
	debug_print_backtrace();
	echo "</pre>\n\n";
}

define('E_SQL_CANNOT_CONNECT', "
<h2>Cannot connect to SQL Server</h2>
There is an error in your DatabaseMagic configuration.
");



/// A class for doing SQL operations automatically on a particular table
/**
 * This class is meant to be extended and a table_defs set in the extended class.  SQL queries can be passed to it with the makeQueryHappen
 * method.  This class will attempt to do everything necessary to make the query happen, including creating the table if it doesn't exist
 * and adding columns to the table if the table is missing a column.
 * The purpose of this object is to provide a vehicle for developers to develop an SQL application without having to maintain their database
 * even when they change their code.  When table_defs are altered in code, the database will be altered as need be.
 */
class DatabaseMagicExecution {

  /// An array that determines how the data for this object will be stored in the database, alternatively, a string of an existing table name
  /**
   * Possible formats for the array are:
   * array('tablename' => array('collumn1name' => array('type', NULL, key, default, extras), column2name => array(...), ...))
   * array('tablename' => array('column1name' => 'type', column2name => 'type', ...)
   */
  private $table_defs = null;

	private $sql_pass  = SQL_PASS;
	private $sql_user  = SQL_USER;
	private $sql_host  = SQL_HOST;
	private $sql_dbase = SQL_DBASE;
	private $sql_prfx  = SQL_TABLE_PREFIX;


	/// Sets the table definitions for this object
	protected function setTableDefs($defs) {
		$this->table_defs = $defs;
	}

	/// Returns the table definitions for this object
	function getTableDefs() {
		return $this->table_defs;
	}

	/**
	* returns the name of the primary key for a particular table definition
	*/
	protected function findKey($def) {
		$def = (is_array($def)) ? $def : array();
		foreach ($def as $field => $details) {
			if ($details[2] == "PRI")
				return $field;
		}
		return null;
	}

	/**
	* takes a table definition and returns the primary key for that table
	*/
	protected function findTableKey($defs = null) {
		$defs = (is_null($defs)) ? $this->table_defs : $defs;
		return $this->findKey(first_val($defs));
	}

	/**
	* Returns the creation definition for a table column
	*/
	protected function getCreationDefinition($field, $details) {
		if (!is_array($details)) { $details = array($details); }

		$type    = isset($details[0]) ? $details[0] : "tinytext";
		$null    = isset($details[1]) ? $details[1] : "YES";
		$key     = isset($details[2]) ? $details[2] : "";
		$default = isset($details[3]) ? $details[3] : "";
		$extra   = isset($details[4]) ? $details[4] : "";

		if ($null == "NO") { $nullOut = "NOT NULL"; }
		else               { $nullOut = "";         }
		if ($default == "") { $defaultOut = "";                           }
		else                { $defaultOut = "DEFAULT '" . $default . "'"; }

		$return = "`{$field}` {$type} {$nullOut} {$defaultOut} {$extra}";
		return $return;
	}

	/**
	* getTableCreateQuery()
	* returns the query string that can be used to create a table based on it's definition
	*/
	protected function getTableCreateQuery() {
		$tableNames = array_keys($this->table_defs);
		$tableName = $tableNames[0];

		if (! isset($this->table_defs[$tableName])) {
			return NULL;
		}

		$table_def = $this->table_defs[$tableName];

		$columns = array();
		$pri = array();

		foreach ($table_def as $field => $details) {
			$columns[] = $this->getCreationDefinition($field, $details);
			if ($details[2] == "PRI") {
				$pri[] = "`{$field}`";
			}
		}

		if (count($pri) > 0) { $columns[] = "PRIMARY KEY (".implode(",", $pri).")"; }

		return
			"CREATE TABLE `{$this->sql_prfx}{$tableName}` (\n  " .
			implode(",\n  ", $columns)."\n  " .
			") ENGINE=MyISAM DEFAULT CHARSET=latin1\n";
	}

	/**
	* function getSQLConnection()
	* Returns a valid SQL connection identifier based on the $SQLInfo setting above
	*/
	protected function getSQLConnection() {
		$sql   = mysql_connect($this->sql_host, $this->sql_user, $this->sql_pass) OR die(SQL_CANNOT_CONNECT);
						mysql_select_db($this->sql_dbase, $sql)             OR die(SQL_CANNOT_CONNECT);
		// Prep connection for strict error handling.
		mysql_query("set sql_mode=strict_all_Tables", $sql);
		return $sql;
	}

	/**
	* function getActualTableDefs()
	* Uses the "DESCRIBE" SQL keyword to get the actual definition of a table as it is in the MYSQL database
	*/
	protected function getActualTableDefs($tableName) {
		$sqlConnection = $this->getSQLConnection();
		$query = "DESCRIBE ".$this->sql_prfx.$tableName;
		if (! $results = mysql_query($query, $sqlConnection) ) {
			return FALSE;
		}
		$definition = array();
		while ($row = mysql_fetch_assoc($results)) {
			$definition[$row['Field']] = array ($row['Type'],
																					$row['Null'],
																					$row['Key'],
																					$row['Default'],
																					$row['Extra']);
		}
		return $definition;
	}

	/**
	* returns true if the table exists in the current database, false otherwise.
	*/
	protected function table_exists($tableName) {
		$sql = $this->getSQLConnection();
		$result = mysql_query("SHOW TABLES", $sql);
		while ($row = mysql_fetch_row($result)) {
			if ($row[0] == $this->sql_prfx.$tableName)
				return TRUE;
		}
		return FALSE;
	}

	protected function createTable($customDefs) {
		$tableNames = array_keys($customDefs);
		$tableName = $tableNames[0];
		$query = $this->getTableCreateQuery($customDefs);
		if ($query == NULL) return FALSE;
		dbm_debug("info", "Creating table $tableName");
		dbm_debug("system query", $query);
		$sql = $this->getSQLConnection();
		$result = mysql_query($query, $sql) OR die($query . "\n\n" . mysql_error());
		if ($result) {
			dbm_debug("info", "Success creating table $tableName");
			return TRUE;
		} else {
			dbm_debug("info", "Failed creating table $tableName");
			return FALSE;
		}
	}

	/**
	* updateTable()
	* Bring the table up to the current definition
	*/
	protected function updateTable($customDefs) {
		$tableNames = array_keys($customDefs);
		$tableName = $tableNames[0];

		if (! isset($customDefs[$tableName])) {
			return FALSE;
		}

		$wanteddef = $customDefs[$tableName];
		$actualdef = $this->getActualTableDefs($tableName);

		$sqlConnection = $this->getSQLConnection();

		// Set the primary keys
		$wantedKey = $this->findKey($wanteddef);
		$actualKey = $this->findKey($actualdef);
		if ($wantedKey != $actualKey) {
			if ($actualKey) {
				$query  = "ALTER TABLE ".$this->sql_prfx.$tableName."\n";
				$query .= "  DROP PRIMARY KEY";
				dbm_debug("server query", $query);
				if (! mysql_query($query, $sqlConnection) ) return FALSE;
			}
			if ($wantedKey) {
				$query  = "ALTER TABLE ".$this->sql_prfx.$tableName."\n";
				$query .= "  ADD PRIMARY KEY (".$wantedKey.")";
				dbm_debug("server query", $query);
				if (! mysql_query($query, $sqlConnection) ) return FALSE;
			}
		}

		// Run through the wanted definition for what needs changing
		$location = "FIRST";
		foreach($wanteddef as $name => $options) {
			$creationDef = $this->getCreationDefinition($name, $options);
			// Find a column that needs creating
			if (! isset($actualdef[$name]) ) {
				$query  = "ALTER TABLE ".$this->sql_prfx.$tableName."\n";
				$query .= "  ADD COLUMN " . $creationDef . " " . $location;
				dbm_debug("server query", $query);
				if (! mysql_query($query, $sqlConnection) ) return FALSE;
			}
			// Find a column that needs modifying
			else if ($wanteddef[$name] != $actualdef[$name]) {
				$query  = "ALTER TABLE ".$this->sql_prfx.$tableName."\n";
				$query .= "  MODIFY COLUMN " . $creationDef . " " . $location;
				dbm_debug("server query", $query);
				if (! mysql_query($query, $sqlConnection) ) return FALSE;

			}
			// Change location so it will be set properly for the next iteration
			$location = "AFTER ".$name;
		}

		// SCARY
		// Run through the actual definition for what needs dropping
		foreach($actualdef as $name => $options) {
			// Find a column that needs deleting
			if (!isset($wanteddef[$name]) && DBM_AUTODROP ) {
				$query  = "ALTER TABLE ".$this->sql_prfx.$tableName."\n";
				$query .= "  DROP COLUMN " . $name;
				dbm_debug("server query", $query);
				if (! mysql_query($query, $sqlConnection) ) return FALSE;
			}
		}

		return TRUE;
	}

	protected function makeQueryHappen($customDefs, $query) {
		$tableNames = array_keys($customDefs);
		$tableName = $tableNames[0];
		dbm_debug("regular query", $query);
		$sql = $this->getSQLConnection();
		$result = mysql_query($query, $sql);
		if (! $result) {
			// We have a problem here
			if (! $this->table_exists($tableName)) {
				dbm_debug("error", "Query Failed . . . table $tableName doesn't exist.");
				$this->createTable($customDefs);
			} else {
				if ($customDefs[$tableName] != $this->getActualTableDefs($tableName)) {
					dbm_debug("error", "Query Failed . . . table $tableName needs updating.");
					$this->updateTable($customDefs);
				}
			}
			dbm_debug("regular query", $query);
			$result = mysql_query($query, $sql);
			if (! $result) {
				// We tried :(
				dbm_debug("error", "Query Retry Failed . . . table $tableName could not be fixed.");
				return FALSE;
			}
		}
		// If we got to here, that means we have got a valid result!
		$queryArray = split(' ', $query);
		$command = strtoupper($queryArray[0]);
		switch ($command) {
		case 'SELECT':
			$returnVal = array();
			while ($row = mysql_fetch_assoc($result)) {
				$returnVal[] = $row;
			}
			return $returnVal;
			break;
		case 'INSERT':
		case 'REPLACE':
			return mysql_insert_id($sql);
			break;
		}
	}

}


?>