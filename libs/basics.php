<?php

function pr($data) {
	if(CONFIG::$debug) {
		echo "<pre>";
		print_r($data);
		echo "</pre>";
	}
}

function out($str, $append = true) {
	if($append) {
		echo $str . "\n";
	}
	else {
		if(isset($prevLen)) {
			echo str_pad('', $prevLen) . "\r";
		}
		echo $str . "\r";
	}
	$prevLen = strlen($str);
}

function import($lib, $file) {
	$filename = LIB_PATH . $lib . DIRECTORY_SEPARATOR . $file . '.php';
	if(is_file($filename)) {
		include_once($filename);
	}
}