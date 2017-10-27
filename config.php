<?php
class DATABASE_CONFIG {

	public $default = array(
		'type'     => 'oracle',
		'username' => 'root',
		'password' => '',
		'database' => 'yourip:port/databasename'
	);

	public $db001 = array( // KL Live
		'type'     => 'mysql',
		'username' => 'root',
		'password' => '',
		'host'     => 'yourhost',
		'database' => 'databasename'
	);
}

class CONFIG {
	static public $debug = true;

	static public $perPage = 500;

	static public $yourConfig = array();

	static public $recordSize = 100000;
}