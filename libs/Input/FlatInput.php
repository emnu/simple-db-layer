<?php

class FlatInput {
	protected $filename = null;

	protected $header = array();

	protected $column = array();

	protected $footer = array();

	protected $empty = true;

	protected $fileHandler = null;

	public function __construct($filename) {
		$mode = 'r';
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
	}

	public function setColumn($column = array()) {
		$this->column = $column;
	}

	public function setFooter($footer = array()) {
		$this->footer = $footer;
	}

	public function readHeader() {
		return $this->read($this->header);
	}

	public function readColumn() {
		return $this->read($this->column);
	}

	public function readFooter() {
		return $this->read($this->footer);
	}

	public function read($column) {
		$tmp = trim(fgets($this->fileHandler));

		if(empty($tmp)) {
			return $tmp;
		}

		$start = 0;
		$data = array();
		foreach ($column as $key => $length) {
			$data[$key] = substr($tmp, $start, $length);
			$start += $length;
		}
		return $data;
	}
}