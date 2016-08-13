<?php

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

// A function to dump information without accessing private members
function safe_dump($subject, $padding="") {
	switch (gettype($subject)) {
		case 'boolean':
			echo ($subject) ? "True" : "False";
			echo "\n";
		break;
		case 'integer':
		case 'double':
		case 'string':
			echo "{$subject}\n";
		break;
		case 'array':
			echo "Array(\n";
			$newPadding = "{$padding}\t";
			foreach($subject as $in => $value) {
				echo "{$newPadding}[{$in}] => ";
				safe_dump($value, $newPadding);
			}
			echo "{$padding})\n";
		break;
		case 'object':
			echo get_class($subject)." Object\n";
		break;
		case 'resource':
		case 'NULL':
		case 'unknown type':
		default:
			echo "\n";
		break;
	}
}

function dbm_debug($class, $message) {
	if (DBM_DEBUG) {
		if (is_string($message)) {
			echo "<div class=\"$class\">";
				echo $message;
			echo "\n</div>\n";
		} else {
			echo "<pre class=\"$class\">\n";
// 				var_dump($message);
// 				print_r($message);
//				var_export($message);
				safe_dump($message);
			echo "\n</pre>\n";
		}
	}
}


if (DBM_DEBUG) { set_error_handler ("dbm_do_backtrace"); }

function dbm_do_backtrace ($one, $two) {
	echo "<pre>\nError {$one}, {$two}\n";
	debug_print_backtrace();
	echo "</pre>\n\n";
}

$_SERVER['amtz_query_time'] = 0;
$_SERVER['amtz_queries']    = array();

function amtz_query($query, $connection=null) {
	$startTime   = microtime(true);
	$result      = mysqli_query($connection, $query);
	$endTime     = microtime(true);
	$elapsedTime = $endTime - $startTime;
	$_SERVER['amtz_queries'][] = array(
		'startTime'   => $startTime,
		'endTime'     => $endTime,
		'elapsedTime' => $elapsedTime,
		'query'       => $query
	);
	$_SERVER['amtz_query_time'] += $elapsedTime;
	return $result;
}

define('E_SQL_CANNOT_CONNECT', "
<h2>Cannot connect to SQL Server</h2>
There is an error in your Amortize configuration.
");

?>
