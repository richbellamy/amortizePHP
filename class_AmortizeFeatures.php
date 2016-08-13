<?php

/*******************************************
	Copyright Rich Bellamy, RMB Webs, 2008
	Contact: rich@rmbwebs.com

	This file is part of Amortize.

	Amortize is free software: you can redistribute it and/or modify
	it under the terms of the GNU Lesser General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	Amortize is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU Lesser General Public License for more details.

	You should have received a copy of the GNU Lesser General Public License
	along with Amortize.  If not, see <http://www.gnu.org/licenses/>.
*******************************************/

require_once dirname(__FILE__).'/class_AmortizePreparation.php';
require_once dirname(__FILE__).'/class_AmortizeLink.php';

/// Backend for the AmortizeObject
class AmortizeFeatures extends AmortizePreparation {

	/// Object amtz_status.
	/// Possible statuses are "needs saving", etc.
	protected $amtz_status = array();

	/// Object amtz_attributes are the data that is stored in the object and is saved to the database.
	/// Every instance of a AmortizeFeatures has an array of amtz_attributes.  Each attribute corresponds
	/// to a column in the database table, and each instance of this class corresponds to a row in the table.
	/// Through member functions, amtz_attributes can be read and set to and from an object.
	protected $amtz_attributes = array();

	protected $needs_loading = FALSE;

	protected $table_defs = NULL;

	/// Calls initialize() and sets the $needs_loading flag in an ID is passed in
  public function __construct($id = NULL) {
		parent::__construct();
		$this->setTableDefs($this->table_defs);
    $this->initialize($id);
    if ($id != NULL) {
			$this->needs_loading = TRUE;
		}
  }

	/// Sets all the amtz_attributes to blank and the table key to null.
	/// used for initializing new blank objects.
	protected function initialize($id=NULL) {
		$defs = $this->getTableDefs();
		if (is_array($defs)) {
			$cols = $this->getTableColumnDefs($defs);
			foreach ($cols as $col => $coldef) {
				$this->amtz_attributes[$col] = $this->getInitial($coldef);
				$this->amtz_status[$col] = "clean";
			}
			$this->amtz_attributes[$this->findTableKey()] = $id;
		}
	}

	/** Loads an object from the database.
	 *  This function loads the amtz_attributes for itself from the database, where the table primary key = $id
	 *  Normally called from the constructor, it *could* also be used to change the row that an existing object
	 *  is working on, but just making a new object is probably preferable, unless you really know what you are doing.
	 */
	protected function load($id) {
		dbm_debug("load", "Loading a " . get_class($this) . " with ID = " . $id);
		$key = $this->findTableKey();
		$query = array($key => $id);
		$info = $this->sqlMagicGetOne($query);
		if ($info && is_array($info)) {
			$this->setAttribs($info, TRUE);
			foreach (array_keys($info) as $col) {
				$this->amtz_status[$col] = "clean";
			}
			return TRUE;
		} else {
			return FALSE;
		}
	}

	/**
	 * Perform a load only if necessary
	 */
	protected function loadIfNeeded(){
		// Perform delayed load
		if ($this->needs_loading) {
			$this->needs_loading = FALSE;
			$id = $this->getPrimary();
      if ($this->load($id) === FALSE) {
        // The load failed. . . a never-before-seen primary ID is being explicitly set by the constructor.
        // Mark it dirty so we are sure that it saves.
        dbm_debug("failedload", "load failed");
        $this->setAttribs(array($this->findTableKey() => $id), TRUE);
      }
      return TRUE;
    } else {
			return FALSE;
    }
	}

  /// Returns the array of amtz_attributes for the object.
  protected function getAttribs() {
		// Perform a delayed load if needed so that we have some info to return!
		$this->loadIfNeeded();
		// Build the return value
		$returnMe = $this->amtz_attributes;
		$key = $this->findTableKey();
		if ($returnMe[$key] == NULL) {
			// Unsaved Object, don't return the key attribute with the results
			unset($returnMe[$key]);
		}

    return $returnMe;
  }

	/// Sets attribute (row) data for this object.
	/// $clobberID is a bool that must be true to allow you to overwrite a primary key
	protected function setAttribs($info, $clobberID = FALSE) {
		// Do the delayed load now so that it doesn't happen later and overwrite these values!
		$this->loadIfNeeded();

		// Do the attribute setting
		dbm_debug("setattribs data", $info);
		$defs = $this->getTableDefs();
		$columns = $defs[$this->getTableName($defs)];
		$key = $this->getPrimaryKey();
		if ((!$clobberID) && isset($info[$key])) {
			dbm_debug("clobber", "clobber protected!");
			unset($info[$key]);
		}

		$returnVal = FALSE;
		foreach ($columns as $column => $def) {
			$def = (is_array($def)) ? $def[0] : $def;
			if (isset($info[$column])) {
				if (is_array($info[$column])) { // Filter HTML type arrays to support setAttribs($_POST);
					$info[$column] = $this->valuesFromSet($info[$column], $def);
				}
				$this->amtz_attributes[$column] = $info[$column];
				$returnVal = TRUE;
				$this->amtz_status[$column] = "dirty";
			}
		}

		return $returnVal;
	}

	/// Saves the object data to the database.
	/// This function records the amtz_attributes of the object into a row in the database.
	function save($force = FALSE) {
		$defs = $this->getTableDefs();
		$columns = $this->getTableColumns($defs);
		$allclean = array();
		$savedata = array();
		$key = $this->findTableKey();
		$a = $this->getAttribs();
		if (!isset($a[$key]) || ($a[$key] == NULL)) {
			// This object has never been saved, force save regardless of amtz_status
			// It's very probable that this object is being linked to or is linking another object and needs an ID
			$force = TRUE;
			// Exclude the ID in the sql query.  This will trigger an auto_increment ID to be generated
			$excludeID = TRUE;
			dbm_debug("info deep", "This ".get_class($this)." is new, and we are saving all amtz_attributes regardless of amtz_status");
		} else {
			// Object has been saved before, OR a new ID was specified by the constructor parameters.
			// either way, we need to include the ID in the SQL statement so that the proper row gets set,
			// or the proper ID is used in the new row
			$excludeID = FALSE;
		}

		$magicput_needs_rewrite = TRUE;

		foreach ($columns as $col) {
			if (($this->amtz_status[$col] != "clean") || $force || $magicput_needs_rewrite){
				if (isset($a[$col])) {
					$savedata[$col] = $a[$col];
				}
			}
		}

		if ( count($savedata) >= 1 ) {
			if (!$excludeID) {
				$savedata[$key] = $a[$key];
			}
			$id = $this->sqlMagicPut($savedata);

			if ($id) {
				// Successful auto_increment Save
				$this->amtz_attributes[$key] = $id;
				// Set all statuses to clean.
				$this->amtz_status = array_fill_keys(array_keys($this->amtz_status), "clean");
				return TRUE;
			} else if ($id !== FALSE) {
				// We are not working with an auto_increment ID
				// Set all statuses to clean.
				$this->amtz_status = array_fill_keys(array_keys($this->amtz_status), "clean");
				return TRUE;
			} else {
				// ID === false, there was an error
				xdebug_print_function_stack();
				die("Save Failed!\n".mysql_error());
				return FALSE;
			}

		}

	}


	protected function getLinkedObjects($class, $params=NULL, $relation=NULL, $backLinks=FALSE, $wherelike=NULL, $ordering=NULL) {
		$class = (is_object($class)) ? get_class($class) : $class;
		$prototype = new $class;

		$id = $this->getPrimary();

		if (!$backLinks) {
			$linkObject = new AmortizeLink($this, $prototype);
			$data = $linkObject->getLinksFromID($id, $params, $relation, $wherelike, $ordering);
		} else {
			$linkObject = new AmortizeLink($prototype, $this);
			$data = $linkObject->getBackLinksFromID($id, $params, $relation, $wherelike, $ordering);
		}

		$data = (is_array($data)) ? $data : array();

		$results = array();
		foreach ($data as $fields) {
			$temp = Amortize::generate($class, $fields);
			$results[] = $temp;
		}

		return $results;

	}

	/// Tells you the column name that holds the primary
	public function getPrimaryKey() {
    return $this->findTableKey();
	}

	/** Returns the value of this object's primary key.
	 * Primary key is the unique id for each object, used in the constructor and the load function
	 * for example:
	 *   $obj = new AmortizeObject($key);
	 *   $key2 = $obj->getPrimary();
	 *   $key2 == $key
	 */
	public function getPrimary() {
    $key = $this->getPrimaryKey();
    return $this->amtz_attributes[$key];
	}

	/**
	 * Removes this object's row from the table
	 */
	protected function removeMyRow() {
		$keyVal = $this->getPrimary();
		// Check if the object has even been saved
		if (!is_null($keyVal)) {
			// The key value is non-null.  Therefore it stands to reason that the object exists in the database.  Removal is authorized
			//Build the where clause
			$key = $this->getPrimaryKey();
			$where = array($key => $keyVal);
			// Do the remove
			return $this->sqlMagicYank($where, FALSE);
		} else {
			// The key was null, this object probably isn't in the database
			// Don't attempt a remove.
		}
	}

	/**
	 * Removes any number of rows from the table, protects against accidentally removing all.
	 * If your $where parameter does not make sense or is null, this function protects you from accidentally blanking the table
	 * @param $where an array of matched amtz_attributes to delete on
	 */
	protected function removeSomeRows($where=NULL) {
		return $this->sqlMagicYank($where, FALSE);
	}

	/**
	 * Removes any number of rows from the table, removes all rows if $where is null or invalid.
	 * If you truly only want to remove some of the rows, removeSomeRows() is a better choice.
	 * If your $where parameter does not make sense or is null, this function will blank your table.
	 * @param $where An optional where array or where string. If left blank, the table will be blanked.
	 */
	protected function removeAllRows($where=NULL) {
		return $this->sqlMagicYank($where, TRUE);
	}

	/**
	 * Retrieve an array of all the known IDs for all saved instances of this class
	 * If you plan on foreach = new Blah(each), I suggest using getAllLikeMe instead, your database will thank you
	 * @deprecated This function is not used at any point in this library, and isn't really usefull.  Further, it won't scale well for multi-column primary keys.
	 */
	public function getAllPrimaries($limit=NULL, $offset=NULL, $params=NULL) {
		$list = $this->getAllIDs($limit, $offset, $params);
		return $list;
	}

	/// Retrieve an array of pre-loaded objects
	public function getAllLikeMe($limit=NULL, $offset=NULL, $params=NULL) {
		$list = $this->getAllSomething("*", $limit, $offset, $params);
		$key = $this->findTableKey();
		$returnMe = array();
		
		$proto = get_class($this);
		
		if (is_array($list)) {
			foreach ($list as $data) {
				$id = $data[$key];
				$temp = Amortize::generate($proto, $data);
				$returnMe[$id] = $temp;
			}
		}
		return $returnMe;
	}

  /// Returns the name of the table that this object saves and loads under.
  /// Pretty easy function really.
  public function getMyTableName() {
		return $this->getTableName();
  }


	/// Dumps the contents of attribs via print_r()
	/// Useful for debugging, but that's about it
	public function dumpview($pre=FALSE) {
		if ($pre) echo "<pre style=\"color: blue\">\n";
		echo "Attributes for this ".get_class($this).":\n";;
		print_r($this->amtz_attributes);
		if ($pre) echo "</pre>\n";
	}

}

?>