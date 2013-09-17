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

		static function generate($className, $ID, $data=null) {
			// A place to store the instances
			static $repository = array();

			// Make sure we have a sane request.
			if (!class_exists($className)) {
				trigger_error("Unable to load class: $className", E_USER_WARNING);
				return false;
			}

			// Generate the storage hash
			$hash = "{$className}_{$ID}"; // Do we really need sha1? This will be quicker.

			// Check if we already have generated the object
			if (!isset($repository[$hash])) {
				// Stash a new instance
				$repository[$hash] = new $className($ID);
				// A new instance with an ID and data? Must be a DB Load.
				$repository[$hash]->setAttribs($data, TRUE);
			}

			return $repository[$hash];
		}

	}
?>
