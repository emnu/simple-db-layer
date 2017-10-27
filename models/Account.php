<?php

class Account extends Model {

	public $conn = null;

	public $connName = 'db001';

	public $table = 'ACCOUNT';

	public $sequence = 'ACCOUNT_SEQ.NEXTVAL';

	public $primaryKey = 'ACC_ID';

	public $uses = array();

	public function __construct() {
		parent::__construct();
	}

	public function yourFuntion($param1, $param2) {

	}
}