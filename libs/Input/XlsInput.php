<?php
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once APP_PATH . 'vendors/PHPExcel/Classes/PHPExcel/IOFactory.php';

class XlsInput {
	protected $filename = null;

	protected $header = array();

	protected $currRow = 1;

	protected $xlsHandler = null;

	protected $properties = array();

	public function __construct($filename, $header = array('A1', 'A1')) {
		if(!class_exists('ZipArchive')) {
			PHPExcel_Settings::setZipClass(PHPExcel_Settings::PCLZIP);
		}
		
		$cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_in_memory_gzip;
		PHPExcel_Settings::setCacheStorageMethod($cacheMethod);

		$this->filename = $filename;
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

	public function &getHandler() {
		return $this->xlsHandler;
	}

	public function &getCurrRow() {
		return $this->currRow;
	}

	public function getFilename() {
		return $this->filename;
	}

	public function getHeader() {
		return $this->header;
	}

	public function getProperties() {
		$this->properties['creator'] = $this->xlsHandler->getProperties()->getCreator();
		$this->properties['modifiedBy'] = $this->xlsHandler->getProperties()->getLastModifiedBy();
		$this->properties['title'] = $this->xlsHandler->getProperties()->getTitle();
		$this->properties['subject'] = $this->xlsHandler->getProperties()->getSubject();
		$this->properties['descripton'] = $this->xlsHandler->getProperties()->getDescription();
		$this->properties['keywords'] = $this->xlsHandler->getProperties()->getKeywords();
		$this->properties['category'] = $this->xlsHandler->getProperties()->getCategory();

		return $this->properties;
	}

	public function read() {
		$data = array();
		$eof = true;
		foreach ($this->header as $key => $value) {
			$tmp = $this->xlsHandler->getActiveSheet()->getCell($key.$this->currRow);
			if(PHPExcel_Shared_Date::isDateTime($tmp)) {
				$data[$value] = date('Y-m-d H:i:s', PHPExcel_Shared_Date::ExcelToPHP($tmp->getValue()));
			}
			else {
				$data[$value] = $tmp->getValue();
			}
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
