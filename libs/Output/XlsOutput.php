<?php
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once APP_PATH . 'vendors/PHPExcel/Classes/PHPExcel.php';

class XlsOutput {
	protected $filename = null;

	protected $header = array();

	protected $colType = array();

	protected $xlsHandler = null;

	protected $headerStart = 'A1';

	protected $currRow = 1;

	public function __construct($filename, $header = 'A1') {
		$this->filename = $filename;

		$this->headerStart = $header;

		$this->xlsHandler = new PHPExcel();

		$this->xlsHandler->getProperties()->setCreator("Maarten Balliauw")
									 ->setLastModifiedBy("Maarten Balliauw")
									 ->setTitle("Office 2007 XLSX Test Document")
									 ->setSubject("Office 2007 XLSX Test Document")
									 ->setDescription("Test document for Office 2007 XLSX, generated using PHP classes.")
									 ->setKeywords("office 2007 openxml php")
									 ->setCategory("Test result file");
	}

	public function setHeader($header = array()) {
		preg_match_all('/^([a-z]+)([0-9]+)$/i', $this->headerStart, $fromMatch, PREG_SET_ORDER);

		$colKey = strtoupper($fromMatch[0][1]);
		$colRow = $fromMatch[0][2];
		foreach ($header as $key => $value) {
			if(is_string($key)) {
				$this->header[$colKey] = $key;
				$this->xlsHandler->setActiveSheetIndex(0)->setCellValue($colKey.$colRow, $key);
				$this->colType[$colKey] = $value;
			}
			else{
				$this->header[$colKey] = $value;
				$this->xlsHandler->setActiveSheetIndex(0)->setCellValue($colKey.$colRow, $value);
			}
			$colKey++;
		}
		// pr($this->header);
		$this->currRow = $colRow + 1;
	}

	public function save($data) {
		foreach ($this->header as $key => $value) {
			if(isset($this->colType[$key])) {
				$this->xlsHandler->setActiveSheetIndex(0)->setCellValueExplicit($key.$this->currRow, $data[$value], $this->colType[$key]);
			}
			else {
				$this->xlsHandler->setActiveSheetIndex(0)->setCellValue($key.$this->currRow, $data[$value]);
			}
		}
		$this->currRow++;
	}

	public function out() {
		$objWriter = PHPExcel_IOFactory::createWriter($this->xlsHandler, 'Excel5');
		$objWriter->save($this->filename);
	}
}