<?php

class Cache {
	protected $filename = null;

	protected $caches = array();

	public function __construct($filename, $append = false) {
		$this->filename = $filename;
		if(!is_dir(dirname($this->filename))) {
			if(!mkdir(dirname($this->filename), '0777', true)) {
				die('failed to create file');
			}
		}
		if(!is_file($this->filename)) {
			file_put_contents($this->filename, '');
		}
		$this->caches = json_decode(file_get_contents($this->filename), true);
	}

	public function __destruct() {
		
	}

	public function get($key = null) {
		if(empty($key)) {
			return $this->caches;
		}

		$keys = explode('.', $key);

		$tmp = $this->caches;
		foreach ($keys as $k) {
			if(!isset($tmp[$k])) {
				return false;
			}
			$tmp = $tmp[$k];
		}
		return $tmp;
	}

	public function set($key, $value, $save = true) {
		$keys = explode('.', $key);
		$tmp = &$this->caches;

		foreach ($keys as $k) {
			$tmp = &$tmp[$k];
		}
		$tmp = $value;

		if($save) {
			$this->put();
		}
	}

	public function put($data = array()) {
		$put = $this->caches;
		if(!empty($data) && is_array($data)) {
			$put = $data;
		}
		file_put_contents($this->filename, json_encode($put));
	}
}