<?php

include_once('ConnectionManager.php');

class Model {

	public $name = null;

	public $table = null;

	public $columns = array();

	public $primaryKey = null;

	public $connName = 'default';

	public $rowPerPage = 20;

	public $caches = array();

	public $uses = array();

	/**
	key :-
	 - model
	 - key
	 - conditions
	**/
	public $foreignModel = array();

	public function __construct() {
		$this->name = get_class($this);
		$this->getSchema();

		// $uses
		// $this->generateUsesModel();
	}

	public function __get($name) {
		if(in_array($name, $this->uses)) {
			return ModelObj::buildModel($name);
		}
		else {
			return null;
		}
	}

	public function __call($func, $params) {
		$method = '__cache_'.$func;
		if(!method_exists($this, $method)) {
			die('method does not exist');
		}

		$key = sha1(json_encode($params));
		if(isset($this->caches[$func][$this->connName][$key])) {
			return $this->caches[$func][$this->connName][$key];
		}

		$this->caches[$func][$this->connName][$key] = call_user_func_array(array($this, $method), $params);
		return $this->caches[$func][$this->connName][$key];
	}

	public function connect($connName) {
		$this->connName = $connName;
	}

	public function getSchema() {
		$db = ConnectionManager::loadDb($this->connName);
		$this->columns = $db->getSchema($this->table);
	}

	public function generateUsesModel() {
		if(!empty($this->uses)) {
			$Model = new ModelObj();
			foreach ($this->uses as $modelName) {
				$this->{$modelName} = $Model->{$modelName};
			}
		}
	}

	public function clearCache() {
		$this->caches = array();

		foreach ($this->uses as $modelName) {
			$this->{$modelName}->clearCache();
		}
	}

	public function join($contain) {
		$join = array();
		foreach ($contain as $model => $fields) {
			if(is_array($fields)) {
				if(!in_array($model, array_keys($this->foreignModel)))
					continue;
				$tmpModel = ModelObj::buildModel($model);
				$join[$tmpModel->name]['fields'] = $fields;
			}
			else {
				if(!in_array($fields, array_keys($this->foreignModel)))
					continue;
				$tmpModel = ModelObj::buildModel($fields);
				$join[$tmpModel->name]['fields'] = array_keys($tmpModel->columns);
			}
			$on = array(
				$tmpModel->name.'.'.$tmpModel->primaryKey.' = '.$this->name.'.'.$this->foreignModel[$tmpModel->name]['key']
			);
			$join[$tmpModel->name]['table'] = $tmpModel->table;
			$join[$tmpModel->name]['on'] = array_unique(array_merge($on, $this->foreignModel[$tmpModel->name]['conditions']));
		}
		return $join;
	}

	public function find($conditions = array(), $options = null) {
		if(isset($options['contain'])) {
			$options['contain'] = $this->join($options['contain']);
		}
		$db = ConnectionManager::loadDb($this->connName);
		return $db->find(get_object_vars($this), $conditions, $options);
	}

	public function count($conditions = array(), $options = null) {
		if(isset($options['contain'])) {
			$options['contain'] = $this->join($options['contain']);
		}
		$db = ConnectionManager::loadDb($this->connName);
		return $db->count(get_object_vars($this), $conditions, $options);
	}

	public function insert($data) {
		$db = ConnectionManager::loadDb($this->connName);
		return $db->insert(get_object_vars($this), $data);
	}

	public function update($data, $conditions, $options = null) {
		$db = ConnectionManager::loadDb($this->connName);
		return $db->update(get_object_vars($this), $data, $conditions, $options);
	}

	public function delete($conditions, $options = null) {
		$db = ConnectionManager::loadDb($this->connName);
		return $db->delete(get_object_vars($this), $conditions, $options);	
	}

	public function query($sql) {
		$db = ConnectionManager::loadDb($this->connName);
		return $db->query(get_object_vars($this), $sql);
	}
}