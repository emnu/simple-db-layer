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

	protected $rowCorr = false;

	protected $creator = 'PHPExcel Helper';

	protected $modifiedBy = 'PHPExcel Helper';

	protected $title = 'Excel Document';

	protected $subject = '';

	protected $descripton = '';

	protected $keywords = '';

	protected $category = '';

	protected $type = 'Excel2007';

	protected $defaultsKey = array(
		'creator', 'modifiedBy', 'modifiedBy',
		'title', 'subject', 'descripton',
		'keywords', 'category', 'type'
	);

	public function __construct($filename, $header = 'A1', $options = array()) {
		if(!class_exists('ZipArchive')) {
			PHPExcel_Settings::setZipClass(PHPExcel_Settings::PCLZIP);
		}

		$this->headerStart = $header;

		if(is_object($filename)) {
			$this->filename = $filename->getFilename();

			$this->header = $filename->getHeader();

			$this->xlsHandler = &$filename->getHandler();

			$this->rowCorr = true;

			$this->currRow = &$filename->getCurrRow();

			$this->setOptions($filename->getProperties());
		}
		else {
			$this->filename = $filename;

			$this->setOptions($options);

			$this->xlsHandler = new PHPExcel();

			$this->xlsHandler->getProperties()->setCreator($this->creator)
										 ->setLastModifiedBy($this->modifiedBy)
										 ->setTitle($this->title)
										 ->setSubject($this->subject)
										 ->setDescription($this->descripton)
										 ->setKeywords($this->keywords)
										 ->setCategory($this->category);
		}

	}

	public function setOptions($options) {
		foreach ($options as $key => $value) {
			if(in_array($key, $this->defaultsKey)) {
				if(is_array($this->{$key})) {
					$this->{$key} = array_merge($this->{$key}, $value);
				}
				else {
					$this->{$key} = $value;
				}
			}
		}
	}

	public function setHeader($header = array()) {
		preg_match_all('/^([a-z]+)([0-9]+)$/i', $this->headerStart, $fromMatch, PREG_SET_ORDER);

		$colKey = strtoupper($fromMatch[0][1]);
		$colRow = $fromMatch[0][2];
		$this->xlsHandler->getActiveSheet()->getStyle($colRow)->getFont()->setBold(true);
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

	public function save($data, $currRow = null) {
		if(empty($currRow))
			$currRow = $this->currRow;
		if($this->rowCorr)
			$currRow--;

		foreach ($this->header as $key => $value) {
			if(!isset($data[$value]))
				continue;

			if(isset($this->colType[$key])) {
				$this->xlsHandler->setActiveSheetIndex(0)->setCellValueExplicit($key.$currRow, $data[$value], $this->colType[$key]);
			}
			else {
				$this->xlsHandler->setActiveSheetIndex(0)->setCellValue($key.$currRow, $data[$value]);
			}
		}
		if(!$this->rowCorr)
			$this->currRow++;
	}

	public function out() {
		$objWriter = PHPExcel_IOFactory::createWriter($this->xlsHandler, $this->type);
		$objWriter->save($this->filename);
	}
}
