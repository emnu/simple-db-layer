<?php
error_reporting(E_ALL);
if(!(php_sapi_name() == 'cli')) {
	// die('run on console only'); # enable this to set run only on terminal only
}

define('APP_PATH', dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR);
define('LIB_PATH', dirname(__FILE__).DIRECTORY_SEPARATOR);

include_once(LIB_PATH . 'ErrorHandler.php');
set_error_handler("ErrorHandler");

include_once(LIB_PATH . 'Task.php');
Task::setConfig();

include_once(APP_PATH . 'config.php');

include_once(LIB_PATH . 'basics.php');

include_once(LIB_PATH . 'Cache.php');

include_once(LIB_PATH . 'Model.php');

if(in_array('--dry-run', $argv)) {
	CONFIG::$dryRun = true;
}

class ModelObj {
	static protected $modelDir = 'models';

	static protected $models = array();

	public function __get($name) {
		return self::buildModel($name);
	}

	static function buildModel($name) {
		if(isset(self::$models[$name])) {
			return self::$models[$name];
		}

		$modelFile = APP_PATH . self::$modelDir . DIRECTORY_SEPARATOR . $name.'.php';
		if(!is_file($modelFile)) {
			return null;
		}
		include_once($modelFile);
		self::$models[$name] = new $name();
		return self::$models[$name];
	}
}
$Model = new ModelObj();