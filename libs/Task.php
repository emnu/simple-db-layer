<?php

class Task {

	static protected $processId = null;

	static protected $prefix = '';

	static protected $taskFile = null;

	static protected $scriptFile = null;

	static protected $startTime = null;

	static protected $endTime = null;

	static function setConfig() {
		self::$processId = uniqid();

		self::$startTime = time();

		self::$taskFile = APP_PATH . "logs".DIRECTORY_SEPARATOR . "task.log";

		self::$scriptFile = APP_PATH . basename($_SERVER["SCRIPT_FILENAME"]);
	}

	static function setPrefix($name) {
		self::$prefix = $name;
	}

	static function getProcessId() {
		if(!empty(self::$prefix)) {
			return self::$prefix . "_" . self::$processId;
		}
		return self::$processId;
	}

	static function getTime() {
		$total = self::$endTime - self::$startTime;

		$total = ($total - (($day = intval($total / 86400)) * 86400)); // day
		$total = ($total - (($hour = intval($total / 3600)) * 3600)); // hour
		$second = ($total - (($minute = intval($total / 60)) * 60)); // minute & second

		$strTime = (empty($day)?"":$day . " day(s) ");
		$strTime .= (empty($hour)?"":$hour . " hour(s) ");
		$strTime .= (empty($minute)?"":$minute . " minute(s) ");
		$strTime .= $second . " second(s) ";

		return $strTime;
	}

	static function start() {
		self::$startTime = time();
		$message = "[" . date("Y-m-d H:i:s (T)", self::$startTime) . "] " . "[" . self::getProcessId() . "]: " . self::$scriptFile . " started\n";
		error_log( $message, 3, self::$taskFile);
	}


	static function progress($extraMsg = '') {
		if(empty(self::$startTime)) {
			self::$startTime = time();
		}
		self::$endTime = time();
		$message = "[" . date("Y-m-d H:i:s (T)", self::$endTime) . "] " . "[" . self::getProcessId() . "]: " . self::$scriptFile . " progressing. " . (empty($extraMsg)?"":($extraMsg . ". ")) . "Time taken " . self::getTime() . "\n";
		error_log( $message, 3, self::$taskFile);
	}

	static function end($extraMsg = '') {
		if(empty(self::$startTime)) {
			self::$startTime = time();
		}
		self::$endTime = time();
		$message = "[" . date("Y-m-d H:i:s (T)", self::$endTime) . "] " . "[" . self::getProcessId() . "]: " . self::$scriptFile . " ended. " . (empty($extraMsg)?"":($extraMsg . ". ")) . "Time taken " . self::getTime() . "\n";
		error_log( $message, 3, self::$taskFile);
	}
}
Task::setConfig();