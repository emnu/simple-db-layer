<?php

class Mysql {

	protected $_conn = null;

	protected $_config = array();

	protected $_fieldType = array(
			1 => 'tinyint',
			2 => 'smallint',
			3 => 'int',
			4 => 'float',
			5 => 'double',
			7 => 'timestamp',
			8 => 'bigint',
			9 => 'mediumint',
			10 => 'date',
			11 => 'time',
			12 => 'datetime',
			13 => 'year',
			16 => 'bit',
			252 => 'blob',
			253 => 'varchar',
			254 => 'char',
			246 => 'decimal'
		);

	protected $_bindType = array(
			'tinyint' => 'i',
			'smallint' => 'i',
			'int' => 'i',
			'float' => 'd',
			'double' => 'd',
			'timestamp' => 's',
			'bigint' => 'i',
			'mediumint' => 'i',
			'date' => 's',
			'time' => 's',
			'datetime' => 's',
			'year' => 'i',
			'bit' => 'b',
			'blob' => 'b',
			'varchar' => 's',
			'char' => 's',
			'decimal' => 'd'
		);

	protected $_varType = array(
			'boolean' => 'i',
			'integer' => 'i',
			'float' => 'd',
			'double' => 'd',
			'string' => 's',
		);

	public function __construct($config) {
		$this->_config = $config;
		$this->connect();
	}

	public function connect() {
		$this->_conn = new mysqli($this->_config['host'], $this->_config['username'], $this->_config['password'], $this->_config['database']);
	}

	public function check() {
		if(!$this->_conn->ping()) {
			$this->connect();
		}
	}

	public function getSchema($table) {
		$result = $this->_conn->query('SELECT * FROM '.$table.' LIMIT 0', MYSQLI_STORE_RESULT);

		$columns = array();
		while ($fieldinfo=$result->fetch_field()) {
			$columns[$fieldinfo->name] = array(
				'type' => $this->_fieldType[$fieldinfo->type],
				'size' => $fieldinfo->length
			);
		}
		return $columns;
	}

	private function _fields($fields, $prefix) {
		$tmp = array();
		foreach ($fields as $key => $value) {
			if(is_string($key)) {
				$tmp[$key] = $value.' AS '.$key;
			}
			else {
				$tmp[$value] = '`'.$prefix.'`.`'.$value.'`';
			}
		}
		return $tmp;
	}

	public function buildUpdateSQL($data, $modelVars) {
		$sql = array();
		$sql['types'] = '';
		foreach($data as $key => $value) {
			if(in_array($key, array_keys($modelVars['columns']))) {
				$sql['fields'][] = '`'.$key.'` = ?';
				$sql['values'][] = $value;
				$sql['types'] .= $this->_bindType[$modelVars['columns'][$key]['type']];
			}
		}
		return $sql;
	}

	public function buildInsertSQL($data, $modelVars) {
		$sql = array();
		$sql['types'] = '';
		foreach($data as $key => $value) {
			if(in_array($key, array_keys($modelVars['columns']))) {
				$sql['fields'][] = $key;
				$sql['qmarks'][] = '?';
				$sql['values'][] = $value;
				$sql['types'] .= $this->_bindType[$modelVars['columns'][$key]['type']];
			}
		}
		return $sql;
	}

	private function _buildConditions($conditions, &$sql) {
		$condStr = array();

		foreach ($conditions as $key => $value) {
			if(is_string($key)) {
				if(preg_match("/^(or|and)$/i", trim($key))) {
					$condStr[] = '('. implode(' '.strtoupper(trim($key)).' ', $this->_buildConditions($value, $sql)) . ')';
				}
				elseif(!preg_match("/(\b(is|not|like|null)\b|[<>=!]+)/i", trim($key))) {
					if(is_array($value)) {
						$tmpqmarks = array();
						foreach ($value as $v) {
							$sql['values'][] = $v;
							$sql['types'] .= isset($this->_varType[gettype($v)])?$this->_varType[gettype($v)]:'s';
							$tmpqmarks[] = '?';
							$count++;
						}
						$condStr[] = $key . ' IN (' . implode(', ', $tmpqmarks) . ')';
					}
					elseif(is_null($value)) {
						$condStr[] = $key . ' IS NULL';
					}
					else {
						$sql['values'][] = $value;
						$sql['types'] .= isset($this->_varType[gettype($value)])?$this->_varType[gettype($value)]:'s';
						$condStr[] = $key . ' = ?';
					}
				}
				else {
					if(is_array($value)) {
						$tmpqmarks = array();
						foreach ($value as $v) {
							$sql['values'][] = $v;
							$sql['types'] .= isset($this->_varType[gettype($v)])?$this->_varType[gettype($v)]:'s';
							$tmpqmarks[] = '?';
							$count++;
						}
						$condStr[] = $key . ' IN (' . implode(', ', $tmpqmarks) . ')';
					}
					else {
						$sql['values'][] = $value;
						$sql['types'] .= isset($this->_varType[gettype($value)])?$this->_varType[gettype($value)]:'s';
						$condStr[] = $key . ' ?';
					}
				}
			}
			else {
				if(is_array($value)) {
					$condStr[] = '('. implode(' AND ', $this->_buildConditions($value, $sql)) . ')';
				}
				else {
					$condStr[] = $value;
				}
			}
		}

		return $condStr;
	}

	public function buildConditions($conditions, &$sql) {
		if(!isset($sql['types'])) {
			$sql['types'] = '';
		}
		return implode(' AND ', $this->_buildConditions($conditions, $sql));
	}

	public function find($modelVars, $conditions = array(), $options = null) {
		$result = new MysqlResultSet($modelVars);

		$fields = array_keys($modelVars['columns']);
		if(isset($options['fields']) && !empty($options['fields'])) {
			$fields = $options['fields'];
		}
		$fields = $this->_fields($fields, $modelVars['name']);

		if(isset($options['contain']) && !empty($options['contain'])) {
			foreach ($options['contain'] as $key => $value) {
				$fields = array_merge($this->_fields($value['fields'], $key), $fields);
			}
		}

		$partition = '';
		if(isset($options['partition']) && !empty($options['partition'])) {
			$partition = ' PARTITION ('.$options['partition'].')';
		}

		$result->query = 'SELECT '.implode(', ', $fields).' FROM `'.$modelVars['table'].'`'.$partition.' AS `'.$modelVars['name'].'`';

		$sql = array();
		if(isset($options['contain']) && !empty($options['contain'])) {
			foreach ($options['contain'] as $key => $value) {
				$result->query .= ' LEFT JOIN `'.$value['table'].'` AS `'.$key.'` ON ('.$this->buildConditions($value['on'], $sql).')';
			}
			if(isset($sql['values']) && !empty($sql['values'])) {
				$result->bindArr = array_merge(array($sql['types']), $sql['values']);
			}
		}

		if(!empty($conditions)) {
			$condStr = $this->buildConditions($conditions, $sql);
			$result->query .= ' WHERE ' . $condStr;
			$result->bindArr = array_merge(array($sql['types']), $sql['values']);
		}

		if(isset($options['order']) && !empty($options['order'])) {
			$result->query .= ' ORDER BY ' . $options['order'];
		}

		if(isset($options['page']) && !empty($options['page'])) {
			if(isset($options['limit']) && !empty($options['limit'])) {
				$modelVars['rowPerPage'] = $options['limit'];
			}
			$start = (($options['page'] - 1) * $modelVars['rowPerPage']);
			if(!isset($sql['types'])) {
				$sql['types'] = '';
			}
			$sql['types'] .= 'ii';
			$sql['values'][] = $start;
			$sql['values'][] = $modelVars['rowPerPage'];
			$result->query .= ' LIMIT ?, ?';
			$result->bindArr = array_merge(array($sql['types']), $sql['values']);
		}
		elseif(isset($options['limit']) && !empty($options['limit'])) {
			if(!isset($sql['types'])) {
				$sql['types'] = '';
			}
			$sql['types'] .= 'i';
			$sql['values'][] = $options['limit'];
			$result->query .= ' LIMIT ?';
			$result->bindArr = array_merge(array($sql['types']), $sql['values']);
		}

		$result->execute($this->_conn, array_fill_keys(array_keys($fields), null));
		return $result;
	}

	public function count($modelVars, $conditions = array(), $options = null) {
		$result = new MysqlResultSet($modelVars);

		$partition = '';
		if(isset($options['partition']) && !empty($options['partition'])) {
			$partition = ' PARTITION ('.$options['partition'].')';
		}

		$result->query = 'SELECT COUNT(*) as CNT FROM `'.$modelVars['table'].'`'.$partition.' AS `'.$modelVars['name'].'`';

		if(isset($options['contain']) && !empty($options['contain'])) {
			foreach ($options['contain'] as $key => $value) {
				$result->query .= ' LEFT JOIN `'.$value['table'].'` AS `'.$key.'` ON ('.$this->buildConditions($value['on'], $sql).')';
			}
			if(isset($sql['values']) && !empty($sql['values'])) {
				$result->bindArr = array_merge(array($sql['types']), $sql['values']);
			}
		}

		if(!empty($conditions)) {
			$sql = array();
			$condStr = $this->buildConditions($conditions, $sql);
			$result->query .= ' WHERE ' . $condStr;
			$result->bindArr = array_merge(array($sql['types']), $sql['values']);
		}
		$result->execute($this->_conn, array('cnt'=>null));
		$count = $result->getArray();
		return $count['cnt'];
	}

	public function insert($modelVars, $data) {
		$result = new MysqlResultSet($modelVars);
		$result->query = 'INSERT INTO `'.$modelVars['table'].'`';

		if(!empty($data)) {
			$sql = $this->buildInsertSQL($data, $modelVars);
			$result->query .= ' (`'.implode('`, `', $sql['fields']).'`) VALUES ('.implode(', ', $sql['qmarks']).')';
			$result->bindArr = array_merge(array($sql['types']), $sql['values']);
		}
		$result->execute($this->_conn);
		return $result;
	}

	public function update($modelVars, $data, $conditions, $options = null) {
		$result = new MysqlResultSet($modelVars);

		$partition = '';
		if(isset($options['partition']) && !empty($options['partition'])) {
			$partition = ' PARTITION ('.$options['partition'].')';
		}

		$result->query = 'UPDATE `'.$modelVars['table'].'`'.$partition;

		$sql = array();
		if(!empty($data)) {
			$sql = $this->buildUpdateSQL($data, $modelVars);
			$result->query .= ' SET '.implode(', ', $sql['fields']);
			$result->bindArr = array_merge(array($sql['types']), $sql['values']);
		}
		if(!empty($conditions)) {
			$condStr = $this->buildConditions($conditions, $sql);
			$result->query .= ' WHERE ' . $condStr;
			$result->bindArr = array_merge(array($sql['types']), $sql['values']);
		}
		$result->execute($this->_conn);
		return $result;
	}

	public function delete($modelVars, $conditions, $options = null) {
		$result = new MysqlResultSet($modelVars);
		$result->query = 'DELETE FROM '.$modelVars['table'];

		if(!empty($conditions)) {
			$sql = array();
			$condStr = $this->buildConditions($conditions, $sql);
			$result->query .= ' WHERE ' . $condStr;
			$result->bindArr = array_merge(array($sql['types']), $sql['values']);
		}
		$result->execute($this->_conn);
		return $result;
	}

	public function query($modelVars, $sql) {
		$result = new MysqlResultSet($modelVars);
		$result->query = $sql;

		$result->query($this->_conn);
		return $result;
	}
}

class MysqlResultSet {
	public $id = null;

	public $query = null;

	protected $statement = null;

	protected $assoc = false;

	public $primaryKey = null;

	public $numRows = 0;

	public $bindArr = array();

	public $fields = array();

	public function __construct(&$modelVars) {
		$this->primaryKey = $modelVars['primaryKey'];
	}

	public function execute($conn, $fields = array()) {
		$this->statement = $conn->prepare($this->query);

		if(!empty($this->bindArr)) {
			call_user_func_array(array($this->statement, 'bind_param'), $this->bindArr);
		}

		if(CONFIG::$dryRun && preg_match("/((^update[\s]+)|(^insert[\s]+into[\s]+))/i", $this->query)) {
			out("execute query: ".$this->query);
			pr($this->bindArr);
			return true;
		}
		if($this->statement->execute() == false) {
			die($this->statement->error."\n".$this->query);
		}
		if(preg_match("/^select[\s]+/i", $this->query)) {
			$this->statement->store_result();
		}
		if(isset($this->primaryKey) && !empty($this->primaryKey) && preg_match("/^insert[\s]+into[\s]+/i", $this->query)) {
			$this->id = $this->statement->insert_id;
		}
		$this->numRows = $this->statement->num_rows;
		if(!empty($fields)) {
			foreach ($fields as $key => $value) {
				$this->fields[$key] = &$fields[$key];
			}
			call_user_func_array(array($this->statement, 'bind_result'), $this->fields);
		}
	}

	public function query($conn) {
		$this->assoc = true;
		if(CONFIG::$dryRun && preg_match("/((^update[\s]+)|(^insert[\s]+into[\s]+))/i", $this->query)) {
			out("execute query: ".$this->query);
			pr($this->bindArr);
			return true;
		}

		if($this->statement = $conn->query($this->query)) {
			if(isset($this->primaryKey) && !empty($this->primaryKey) && preg_match("/^insert[\s]+into[\s]+/i", $this->query)) {
				$this->id = $conn->insert_id;
			}
		}
		else {
			die($conn->error);
		}
		$this->numRows = $this->statement->num_rows;
	}

	public function getArray() {
		if($this->assoc) {
			$array = $this->statement->fetch_assoc();
			if(!is_array($array)) {
				$this->statement->free();
			}
			return $array;
		}
		else {
			if($this->statement->fetch()) {
				return $this->fields;
			}
			else {
				$this->statement->close();
				return false;
			}
		}
	}
}
