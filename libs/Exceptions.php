<?php

function ExceptionHandler(Exception $e) {

	if(method_exists($e, 'getMessageTemplate')) {
		$message = sprintf("[%s] %s\n%s",
			get_class($e),
			sprintf($e->getMessageTemplate(), $e->getMessage()),
			$e->getTraceAsString()
		);
	}
	else {
		$message = sprintf("[%s] %s\n%s",
			get_class($e),
			$e->getMessage(),
			$e->getTraceAsString()
		);
	}

	if(CONFIG::$debug > 0) {
		echo $message;
	}
	else {
		echo 'Internal server error';
	}
}

class BaseException extends Exception {
	protected $_messageTemplate = 'Unknown exception: %s';

	public function getMessageTemplate() {
		return $this->_messageTemplate;
	}
}

class DBNotFoundException extends BaseException {

	protected $_messageTemplate = 'Database config %s could not be found.';

	public function __construct($message, $code = 404) {
		parent::__construct($message, $code);
	}
}

class WriteException extends BaseException {
	protected $_messageTemplate = 'Failed to write file %s.';

	public function __construct($message, $code = 403) {
		parent::__construct($message, $code);
	}
}

class DBErrorException extends BaseException {
	protected $_messageTemplate = '%s.';

	public function __construct($message, $code = 500) {
		parent::__construct($message, $code);
	}
}

class ModelException extends BaseException {
	protected $_messageTemplate = 'Model error: %s.';

	public function __construct($message, $code = 500) {
		parent::__construct($message, $code);
	}
}
set_exception_handler('ExceptionHandler');
