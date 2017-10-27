<?php

class ConnectionManager {
	public static $config = null;

	protected static $_dataSources = array();

	protected static function _init() {
		include_once(APP_PATH . 'config.php');
		if (class_exists('DATABASE_CONFIG') && is_null(self::$config)) {
			self::$config = new DATABASE_CONFIG();
		}
	}

	public static function loadDb($name) {
		if(is_null(self::$config)) {
			self::_init();
		}

		if (!empty(self::$_dataSources[$name])) {
			self::$_dataSources[$name]->check();
			return self::$_dataSources[$name];
		}

		if(isset(self::$config->{$name})) {
			$config = self::$config->{$name};
			$class = ucfirst($config['type']);
			include_once(LIB_PATH . 'Dbo/'.$class.'.php');
			self::$_dataSources[$name] = new $class($config);
			return self::$_dataSources[$name];
		}
		else {
			die('DB config not found: '.$name);
		}
	}
}