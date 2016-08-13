<?php
/**
 * Created by JetBrains PhpStorm.
 * User: rich
 * Date: 5/14/13
 * Time: 3:49 AM
 * To change this template use File | Settings | File Templates.
 */

	class SetTest extends Amortize {
		protected $table_columns = array(
			'foo' => "tinytext"
		);

		protected function preset_callback_foo($value) {
			return strtoupper($value);
		}
	}

	class GetTest extends Amortize {
		protected $table_columns = array(
			'bar' => "tinytext"
		);

		protected function preget_callback_bar($value) {
			return strtoupper($value);
		}
	}

	$obj = new SetTest;
	$obj->dumpview(TRUE);
	$obj->foo = "this is all lowercase";
	dbm_debug('info', "Here is what's inside");
	$obj->dumpview(TRUE);
	dbm_debug('info', "Here is what's coming out: {$obj->foo}");

	$obj = new GetTest;
	$obj->dumpview(TRUE);
	$obj->bar = "this is all lowercase";
	dbm_debug('info', "Here is what's inside");
	$obj->dumpview(TRUE);
	dbm_debug('info', "Here is what's coming out: {$obj->bar}");

