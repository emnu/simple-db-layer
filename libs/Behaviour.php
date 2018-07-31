<?php

class Behaviour {

	protected $modelName = null;

	protected $configs = array();

	public function __construct($modelName, $configs) {
		$this->modelName = $modelName;

		$this->configs = $configs;
	}

	public function beforeSave($data, $conditions = array()) {
		return true;
	}

	public function afterSave($data, $conditions = array()) {
		return true;
	}

	public function beforeDelete() {
		return true;
	}

	public function afterDelete() {
		return true;
	}
}