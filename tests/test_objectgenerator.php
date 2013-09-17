<?php

	class Book extends AmortizeInterface {
		protected $table_name = 'myBooks';
		protected $table_columns = array(
			'isbn'    => "varchar(20)",
			'author'  => "tinytext",
			'title'   => "tinytext",
			'pubyear' => "year"
		);
		protected $autoprimary = TRUE;
	}

// dbm_debug styled types: heading, info, data


	dbm_debug('heading', 'Creating 100 Book Objects the old way');
	$starttime = microtime(TRUE);
	for ($i=0 ; $i<1000 ; $i++) {
		// Create Object in old way
		$book = new Book(1);
		// Force a data load
		$title = $book->title;
	}
	$currentTime = microtime(TRUE) - $starttime;
	dbm_debug('info', "1000 copies of {$title} created the old way with data accessed in {$currentTime} seconds");


	dbm_debug('heading', 'Creating 1000 Book Objects the new way');
	$starttime = microtime(TRUE);
	for ($i = 0; $i < 1000; $i++) {
		// Create Object in new way
		$book = Amortize::generate("Book", 1);
		// Force a data load
		$title       = $book->title;
	}
	$currentTime = microtime(TRUE) - $starttime;
	dbm_debug('info', "1000 copies of {$title} created the new way with data accessed in {$currentTime} seconds");
