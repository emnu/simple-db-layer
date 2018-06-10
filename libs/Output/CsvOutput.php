<?php

class CsvOutput {
	protected $filename = null;

	protected $header = array();

	protected $empty = true;

	protected $fileHandler = null;

	public function __construct($filename, $append = false) {
		$mode = 'w';
		if($append) {
			$mode = 'a';
		}
		$this->filename = $filename;
		if(file_exists($filename) && filesize($filename) > 0) {
			$this->empty = false;
		}
		$this->fileHandler = fopen($this->filename, $mode);
	}

	public function __destruct() {
		fclose($this->fileHandler);
	}

	public function setHeader($header = array()) {
		$this->header = $header;
		if($this->empty) {
			fputcsv($this->fileHandler, $header);
		}
	}

	public function save($data) {
		$out = array();
		foreach ($this->header as $value) {
			$out[] = $data[$value];
		}

		fputcsv($this->fileHandler, $out);
	}
}