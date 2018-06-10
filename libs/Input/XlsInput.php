<?php
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once APP_PATH . 'vendors/PHPExcel/Classes/PHPExcel/IOFactory.php';

class XlsInput {
	protected $filename = null;

	protected $header = array();

	protected $currRow = 1;

	protected $xlsHandler = null;

	public function __construct($filename, $header = array('A1', 'A1')) {
		if(!class_exists('ZipArchive')) {
			PHPExcel_Settings::setZipClass(PHPExcel_Settings::PCLZIP);
		}

		$this->xlsHandler = PHPExcel_IOFactory::load($filename);

		if($this->xlsHandler) {
			$this->__setHeader($header);
		}
	}

	private function __setHeader($header) {
		list($from, $to) = $header;

		preg_match_all('/^([a-z]+)([0-9]+)$/i', $from, $fromMatch, PREG_SET_ORDER);
		preg_match_all('/^([a-z]+)([0-9]+)$/i', $to, $toMatch, PREG_SET_ORDER);
		if($fromMatch[0][2] != $toMatch[0][2]) {
			die('row does not match');
		}

		$colKey = strtoupper($fromMatch[0][1]);
		while($from != $to) {
			$this->header[$colKey] = $this->xlsHandler->getActiveSheet()->getCell($from)->getValue();
			$from = (++$colKey) . $fromMatch[0][2];
		}
		$this->header[$colKey] = $this->xlsHandler->getActiveSheet()->getCell($from)->getValue();

		$this->currRow = $fromMatch[0][2] + 1;
	}

	public function read() {
		$data = array();
		$eof = true;
		foreach ($this->header as $key => $value) {
			$data[$value] = $this->xlsHandler->getActiveSheet()->getCell($key.$this->currRow)->getValue();
			if(!empty($data[$value])) {
				$eof = false;
			}
		}

		if($eof) {
			return false;
		}
		$this->currRow++;
		return $data;
	}
}