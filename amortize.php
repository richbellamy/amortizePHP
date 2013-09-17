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

	include_once dirname(__FILE__) . '/class_AmortizeInterface.php';
	class Amortize extends AmortizeInterface {

		static function generate($className, $data=null) {
			// A place to store the instances
			static $repository = array(); // Initiated Objects Storage
			static $prototypes = array(); // Uninitiated Object Storage (used for cloning to avoid high constructor overhead)
			static $keys       = array(); // Key storage to limit having to run getPrimaryKey all the damn time

			// Make sure we have a sane request.
			if (!class_exists($className)) {
				trigger_error("Unable to load class: $className", E_USER_WARNING);
				return false;
			}

			// Create a prototype if we don't have one
			if (!isset($prototypes[$className])) {
				$prototypes[$className] = new $className;
			}
			// Grab the prototype
			$prototype = $prototypes[$className];
			/** @var $prototype Amortize */

			// Create a key entry if we don't have one
			if (!isset($keys[$className])) {
				$keys[$className] = $prototype->getPrimaryKey();
			}
			// Get the key
			$key = $keys[$className];

			// Determine type of data passed
			if (gettype($data) == "array") {
				$ID = isset($data[$key]) ? $data[$key] : NULL;
			} else {
				$ID = $data;
				$data = false;
			}

			// If no valid ID was found, Exit here with a non-cached new item.
			if (is_null($ID)) {
				return clone $prototype;
			}

			// Setup a hash
			$hash = "{$className}_{$ID}";

			// Check if we already have generated the object
			if (!isset($repository[$hash])) {
				if ($data) { // A new instance with an ID and data? Must be a DB Load.
					$newObject = clone $prototype; /** @var $newObject Amortize */
					$newObject->setAttribs($data, TRUE);
				} else {
					$newObject = new $className($ID);
				}
				$repository[$hash] = $newObject;
			}

			return $repository[$hash];
		}

	}
?>
