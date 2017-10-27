<?php

class Person extends Model {

	public $conn = null;

	public $connName = 'default';

	public $table = 'PERSON';

	public $sequence = 'PERSON_SEQ.NEXTVAL';

	public $primaryKey = 'PERSON_ID';

	public $uses = array('Account');

	public function __construct() {
		parent::__construct();
	}

	public function FunctionName($value='') {
		return $this->Account->yourFuntion($param1, $param2);
	}
}