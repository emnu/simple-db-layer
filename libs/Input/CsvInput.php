<?php

class CsvInput {
	protected $filename = null;

	protected $header = array();

	protected $empty = true;

	protected $fileHandler = null;

	public function __construct($filename, $header = true) {
		$mode = 'r';
		$this->filename = $filename;
		if(file_exists($filename) && filesize($filename) > 0) {
			$this->empty = false;
		}
		$this->fileHandler = fopen($this->filename, $mode);

		$this->__setHeader($header);
	}

	public function __destruct() {
		fclose($this->fileHandler);
	}

	private function __setHeader($header) {
		if(!$this->empty && $header) {
			$this->header = fgetcsv($this->fileHandler);
		}
	}

	public function getHeader() {
		return $this->header;
	}

	public function read() {
		$tmp = fgetcsv($this->fileHandler);
		if(empty($this->header)) {
			return $tmp;
		}

		if($tmp) {
			return array_combine($this->header, $tmp);
		}

		return $tmp;
	}
}