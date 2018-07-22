<?php

class FlatOutput {
	protected $filename = null;

	protected $header = array();

	protected $column = array();

	protected $footer = array();

	protected $empty = true;

	protected $str_pad = array(
			'left' => STR_PAD_LEFT,
			'right' => STR_PAD_RIGHT,
			'both' => STR_PAD_BOTH
		);

	protected $fileHandler = null;

	public function __construct($filename, $append = false) {
		$mode = 'w';
		if($append) {
			$mode = 'a';
		}
		$this->filename = $filename;
		if(file_exists($filename) && filesize($filename) > 0 && $append ) {
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

	public function saveHeader($data, $padding = 'right') {
		$this->save($data, $this->header, $padding);
	}

	public function saveColumn($data, $padding = 'right') {
		$this->save($data, $this->column, $padding);
	}

	public function saveFooter($data, $padding = 'right') {
		$this->save($data, $this->footer, $padding);
	}

	public function save($data, $column, $padding = 'right') {
		$out = '';
		foreach ($column as $key => $length) {
			$out .= substr(str_pad($data[$key], $length, ' ', $this->str_pad[$padding]), 0, $length);
		}

		if(!$this->empty) {
			$out = "\n".$out;
		}

		fwrite($this->fileHandler, $out);
		$this->empty = false;
	}
}