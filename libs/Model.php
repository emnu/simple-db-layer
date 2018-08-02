<?php

include_once('ConnectionManager.php');

include_once('Behaviour.php');

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
	 - model, 
	 - type, 
	 - fields, 
	 - on
	**/
	public $foreignModel = array();

	public $behaviours = array();

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
		foreach ($this->attached as $behaviour => $configs) {
			$tmp = $this->getBehaviour($behaviour, $configs);
			if(method_exists($tmp, $func)) {
				return call_user_func_array(array($tmp, $func), $params);
			}
		}
		
		$method = '__cache_'.$func;
		if(!method_exists($this, $method)) {
			throw new ModelException('method '.$method.' does not exist');
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

	public function getBehaviour($behaviour, $configs) {
		if(is_string($configs)) {
			$behaviourName = $configs;
			$behaviourConfigs = array();
		}
		else{
			$behaviourName = $behaviour;
			$behaviourConfigs = $configs;
		}

		if(isset($this->behaviours[$behaviourName])) {
			return $this->behaviours[$behaviourName];
		}

		$behaviourLib = APP_PATH . 'models' . DIRECTORY_SEPARATOR . 'behaviours' . DIRECTORY_SEPARATOR . $behaviourName . '.php';
		if(is_file($behaviourLib)) {
			include_once($behaviourLib);
			$this->behaviours[$behaviourName] = new $behaviourName($this->name, $behaviourConfigs);
			return $this->behaviours[$behaviourName];
		}
		throw new ModelException('Behaviour '.$behaviourName.' not found');
	}

	public function join($contains) {// model, type, fields, on
		$joins = array();
		foreach ($contains as $key => $value) {
			$tmpValue = $value;
			$tmpKey = $key;
			if(isset($this->foreignModel[$key])) {
				$tmpValue = $this->foreignModel[$key];
			}
			if(is_string($value) && isset($this->foreignModel[$value])) {
				$tmpValue = $this->foreignModel[$value];
				$tmpKey = $value;
			}
			if(is_array($value) && !isset($value['model'])) {
				$tmpValue['fields'] = $value;
			}

			$tmpModel = ModelObj::buildModel($tmpValue['model']);
			$tmpValue['table'] = $tmpModel->table;

			if(isset($value['fields'])) {
				$tmpValue['fields'] = $value['fields'];
			}
			if(!isset($tmpValue['fields'])) {
				$tmpValue['fields'] = array_keys($tmpModel->columns);
			}

			if(!isset($tmpValue['type']) || (isset($tmpValue['type']) && !in_array($tmpValue['type'], array('LEFT', 'RIGHT', 'INNER')))) {
				$tmpValue['type'] = 'LEFT';
			}
			else {
				$tmpValue['type'] = strtoupper($tmpValue['type']);
			}

			$joins[$tmpKey] = $tmpValue;
		}

		return $joins;
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

	public function insert($data, $callback = true) {
		if(!empty($this->attached) && $callback) {
			foreach ($this->attached as $behaviour => $configs) {
				$this->getBehaviour($behaviour, $configs)->beforeSave($data);
			}
		}

		$db = ConnectionManager::loadDb($this->connName);
		$result = $db->insert(get_object_vars($this), $data);

		if(!$result) {
			return $result;
		}

		if(!empty($this->attached) && $callback) {
			foreach ($this->attached as $behaviour => $configs) {
				$this->getBehaviour($behaviour, $configs)->afterSave($data);
			}
		}

		return $result;
	}

	public function update($data, $conditions, $options = null, $callback = true) {
		if(!empty($this->attached) && $callback) {
			foreach ($this->attached as $behaviour => $configs) {
				$this->getBehaviour($behaviour, $configs)->beforeSave($data, $conditions);
			}
		}

		$db = ConnectionManager::loadDb($this->connName);
		$result = $db->update(get_object_vars($this), $data, $conditions, $options);

		if(!$result) {
			return $result;
		}

		if(!empty($this->attached) && $callback) {
			foreach ($this->attached as $behaviour => $configs) {
				$this->getBehaviour($behaviour, $configs)->afterSave($data, $conditions);
			}
		}

		return $result;
	}

	public function delete($conditions, $options = null, $callback = true) {
		if(!empty($this->attached) && $callback) {
			foreach ($this->attached as $behaviour => $configs) {
				$this->getBehaviour($behaviour, $configs)->beforeDelete();
			}
		}

		$db = ConnectionManager::loadDb($this->connName);
		$result = $db->delete(get_object_vars($this), $conditions, $options);

		if(!$result) {
			return $result;
		}

		if(!empty($this->attached) && $callback) {
			foreach ($this->attached as $behaviour => $configs) {
				$this->getBehaviour($behaviour, $configs)->afterDelete();
			}
		}

		return $result;
	}

	public function query($sql) {
		$db = ConnectionManager::loadDb($this->connName);
		return $db->query(get_object_vars($this), $sql);
	}
}