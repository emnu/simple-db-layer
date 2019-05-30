<?php
class DATABASE_CONFIG {

	public $default = array(
		'type'     => 'oracle',
		'username' => 'root',
		'password' => '',
		'host'     => 'localhost',
		'database' => 'databasename'
	);

	public $db001 = array(
		'type'     => 'mysql',
		'username' => 'root',
		'password' => '',
		'host'     => 'yourhost',
		'database' => 'databasename'
	);
}

class CONFIG {
	static public $dryRun = false;

	static public $debug = true;

	static public $perPage = 500;

	static public $yourConfig = array();

	static public $recordSize = 100000;
}
