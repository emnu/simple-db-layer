<?php

if(function_exists('mysqli_connect')) {
class Mysql {

	protected $_conn = null;

	protected $_config = array(
		'type'     => 'mysql',
		'username' => 'root',
		'password' => '',
		'host' => 'localhost',
		'port' => 3306,
		'database' => ''
	);

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
			252 => 'text',
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
			'text' => 's',
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
		$this->_config = array_merge($this->_config, $config);
		$this->connect();
	}

	public function connect() {
		$this->_conn = new mysqli($this->_config['host'], $this->_config['username'], $this->_config['password'], $this->_config['database'], $this->_config['port']);

		if($this->_conn->connect_error) {
			throw new DBErrorException($this->_conn->connect_error);
		}
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

	private function _quotes($str) {
		preg_match_all('/[^a-z0-9_]?([a-z0-9_]+)\.([a-z0-9_]+)[^a-z0-9_]?/i', $str, $matches, PREG_SET_ORDER);

		if(empty($matches))
			return $str;

		$find = $replace = array();
		foreach ($matches as $value) {
			$find[] = $value[1].'.'.$value[2];
			$replace[] = '`'.$value[1].'`.`'.$value[2].'`';
		}
		return str_replace($find, $replace, $str);
	}

	private function _fields($fields, $prefix) {
		$tmp = array();
		foreach ($fields as $key => $value) {
			if(is_string($key)) {
				$tmp[$key] = $this->_quotes($value).' AS `'.$key.'`';
			}
			else {
				$tmp[$value] = '`'.$prefix.'`.`'.$value.'`';
			}
		}
		return $tmp;
	}

	public function getDateTime(){
		return date('Y-m-d H:i:s');
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
					$condStr[] = $this->_quotes($value);
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
				$result->query .= ' '.$value['type'].' JOIN `'.$value['table'].'` AS `'.$key.'` ON ('.$this->buildConditions($value['on'], $sql).')';
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

		if(isset($options['group']) && !empty($options['group'])) {
			$result->query .= ' GROUP BY ' . $this->_quotes($options['group']);
		}

		if(isset($options['order']) && !empty($options['order'])) {
			$result->query .= ' ORDER BY ' . $this->_quotes($options['order']);
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
				$result->query .= ' '.$value['type'].' JOIN `'.$value['table'].'` AS `'.$key.'` ON ('.$this->buildConditions($value['on'], $sql).')';
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

	public function query($modelVars, $sql, $binds = array()) {
		$result = new MysqlResultSet($modelVars);
		$types = '';
		foreach ($binds as $key => $value) {
			$types .= isset($this->_varType[gettype($value)])?$this->_varType[gettype($value)]:'s';
			$result->bindArr[] = $value;
		}
		$result->bindArr = array_merge(array($types), $result->bindArr);
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

	public $name = null;

	public function __construct(&$modelVars) {
		$this->primaryKey = $modelVars['primaryKey'];
		$this->name = $modelVars['name'];
	}

	private function refValues($arr) {
		if (strnatcmp(phpversion(),'5.3') >= 0) { //Reference is required for PHP 5.3+
			$refs = array();
			foreach($arr as $key => $value)
				$refs[$key] = &$arr[$key];
			return $refs;
		}

		return $arr;
	}

	public function execute($conn, $fields = array()) {
		$this->statement = $conn->prepare($this->query);
		if(!$this->statement) {
			throw new DBErrorException($conn->error);
		}

		if(!empty($this->bindArr)) {
			call_user_func_array(array($this->statement, 'bind_param'), $this->refValues($this->bindArr));			
		}

		if(CONFIG::$dryRun && preg_match("/((^update[\s]+)|(^insert[\s]+into[\s]+))/i", $this->query)) {
			out("execute query: ".$this->query);
			pr($this->bindArr);
			return true;
		}
		if($this->statement->execute() == false) {
			throw new DBErrorException($this->statement->error);
		}
		if(preg_match("/^select[\s]+/i", $this->query)) {
			$this->statement->store_result();
			$this->numRows = $this->statement->num_rows;
		}
		else {
			$this->numRows = $this->statement->affected_rows;
		}
		if(isset($this->primaryKey) && !empty($this->primaryKey) && preg_match("/^insert[\s]+into[\s]+/i", $this->query)) {
			$this->id = $this->statement->insert_id;
		}
		if(!empty($fields)) {
			foreach ($fields as $key => $value) {
				$this->fields[$key] = &$fields[$key];
			}
			call_user_func_array(array($this->statement, 'bind_result'), $this->fields);
		}

		Log::query($this->query, $this->name, $this->numRows);
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
			throw new DBErrorException($conn->error);
		}
		if(preg_match("/^select[\s]+/i", $this->query)) {
			$this->numRows = $this->statement->num_rows;
		}
		else {
			$this->numRows = $this->statement->affected_rows;
		}

		Log::query($this->query, $this->name, $this->numRows);
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
				$tmp = array();
				foreach ($this->fields as $key => $value) { // convert from 'by reference' to 'by value'
					$tmp[$key] = $value;
				}
				return $tmp;
			}
			else {
				$this->statement->close();
				return false;
			}
		}
	}
}
}
else {
class Mysql {

	protected $_conn = null;

	protected $_config = array(
		'type'     => 'mysql',
		'username' => 'root',
		'password' => '',
		'host' => 'localhost',
		'port' => 3306,
		'database' => ''
	);

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
			252 => 'text',
			253 => 'varchar',
			254 => 'char',
			246 => 'decimal'
		);

	protected $_varType = array(
			'boolean' => 'i',
			'integer' => 'i',
			'float' => 'd',
			'double' => 'd',
			'string' => 's',
		);

	public function __construct($config) {
		$this->_config = array_merge($this->_config, $config);
		$this->connect();
	}

	public function connect() {
		$this->_conn = mysql_connect($this->_config['host'].':'.$this->_config['port'], $this->_config['username'], $this->_config['password']);

		if($this->_conn == false) {
			throw new DBErrorException(mysql_error());
		}
		
		mysql_select_db($this->_config['database'], $this->_conn);
	}

	public function check() {
		if(!mysql_ping($this->_conn)) {
			$this->connect();
		}
	}

	public function getSchema($table) {
		$result = mysql_query('SELECT * FROM '.$table.' LIMIT 0');

		$columns = array();
		for ($i=0; $i < mysql_num_fields($result); $i++) {
			$columns[mysql_field_name($result, $i)] = array(
				'type' => mysql_field_type($result, $i),
				'size' => mysql_field_len($result, $i)
			);
		}
		return $columns;
	}

	private function _quotes($str) {
		preg_match_all('/[^a-z0-9_]?([a-z0-9_]+)\.([a-z0-9_]+)[^a-z0-9_]?/i', $str, $matches, PREG_SET_ORDER);

		if(empty($matches))
			return $str;

		$find = $replace = array();
		foreach ($matches as $value) {
			$find[] = $value[1].'.'.$value[2];
			$replace[] = '`'.$value[1].'`.`'.$value[2].'`';
		}
		return str_replace($find, $replace, $str);
	}

	private function _fields($fields, $prefix) {
		$tmp = array();
		foreach ($fields as $key => $value) {
			if(is_string($key)) {
				$value = $this->_quotes($value);
				$tmp[$key] = $value.' AS `'.$key.'`';
			}
			else {
				$tmp[$value] = '`'.$prefix.'`.`'.$value.'`';
			}
		}
		return $tmp;
	}

	public function getDateTime(){
		return date('Y-m-d H:i:s');
	}

	public function buildUpdateSQL($data, $modelVars) {
		$sql = array();
		foreach($data as $key => $value) {
			if(in_array($key, array_keys($modelVars['columns']))) {
				$vals = (gettype($value) == 'string')?"'".mysql_real_escape_string($value)."'":$value;
				$sql[] = '`'.$key.'` = '.$vals;
			}
		}
		return $sql;
	}

	public function buildInsertSQL($data, $modelVars) {
		$sql = array();
		foreach($data as $key => $value) {
			if(in_array($key, array_keys($modelVars['columns']))) {
				$sql['fields'][] = $key;
				$sql['values'][] = (gettype($value) == 'string')?"'".mysql_real_escape_string($value)."'":$value;
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
							$tmpvals[] = (gettype($v) == 'string')?"'".mysql_real_escape_string($v)."'":$v;
						}
						$condStr[] = $key . ' IN (' . implode(', ', $tmpvals) . ')';
					}
					elseif(is_null($value)) {
						$condStr[] = $key . ' IS NULL';
					}
					else {
						$vals = (gettype($value) == 'string')?"'".mysql_real_escape_string($value)."'":$value;
						$condStr[] = $key . ' = '.$vals;
					}
				}
				else {
					if(is_array($value)) {
						$tmpqmarks = array();
						foreach ($value as $v) {
							$tmpvals[] = (gettype($v) == 'string')?"'".mysql_real_escape_string($v)."'":$v;
						}
						$condStr[] = $key . ' IN (' . implode(', ', $tmpvals) . ')';
					}
					else {
						$vals = (gettype($value) == 'string')?"'".mysql_real_escape_string($value)."'":$value;
						$condStr[] = $key . ' ' . $vals;
					}
				}
			}
			else {
				if(is_array($value)) {
					$condStr[] = '('. implode(' AND ', $this->_buildConditions($value, $sql)) . ')';
				}
				else {
					$condStr[] = $this->_quotes($value);
				}
			}
		}

		return $condStr;
	}

	public function buildConditions($conditions, &$sql) {
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
				$result->query .= ' '.$value['type'].' JOIN `'.$value['table'].'` AS `'.$key.'` ON ('.$this->buildConditions($value['on'], $sql).')';
			}
		}

		if(!empty($conditions)) {
			$condStr = $this->buildConditions($conditions, $sql);
			$result->query .= ' WHERE ' . $condStr;
		}

		if(isset($options['group']) && !empty($options['group'])) {
			$result->query .= ' GROUP BY ' . $this->_quotes($options['group']);
		}

		if(isset($options['order']) && !empty($options['order'])) {
			$result->query .= ' ORDER BY ' . $this->_quotes($options['order']);
		}

		if(isset($options['page']) && !empty($options['page'])) {
			if(isset($options['limit']) && !empty($options['limit'])) {
				$modelVars['rowPerPage'] = $options['limit'];
			}
			$start = (($options['page'] - 1) * $modelVars['rowPerPage']);
			$result->query .= ' LIMIT '.$start.', '.$modelVars['rowPerPage'];
		}
		elseif(isset($options['limit']) && !empty($options['limit'])) {
			$result->query .= ' LIMIT '.$options['limit'];
		}

		$result->execute($this->_conn);
		return $result;
	}

	public function count($modelVars, $conditions = array(), $options = null) {
		$result = new MysqlResultSet($modelVars);

		$partition = '';
		if(isset($options['partition']) && !empty($options['partition'])) {
			$partition = ' PARTITION ('.$options['partition'].')';
		}

		$result->query = 'SELECT COUNT(*) AS cnt FROM `'.$modelVars['table'].'`'.$partition.' AS `'.$modelVars['name'].'`';

		if(isset($options['contain']) && !empty($options['contain'])) {
			foreach ($options['contain'] as $key => $value) {
				$result->query .= ' '.$value['type'].' JOIN `'.$value['table'].'` AS `'.$key.'` ON ('.$this->buildConditions($value['on'], $sql).')';
			}
		}

		if(!empty($conditions)) {
			$sql = array();
			$condStr = $this->buildConditions($conditions, $sql);
			$result->query .= ' WHERE ' . $condStr;
		}
		$result->execute($this->_conn);
		$count = $result->getArray();
		return $count['cnt'];
	}

	public function insert($modelVars, $data) {
		$result = new MysqlResultSet($modelVars);
		$result->query = 'INSERT INTO `'.$modelVars['table'].'`';

		if(!empty($data)) {
			$sql = $this->buildInsertSQL($data, $modelVars);
			$result->query .= ' (`'.implode('`, `', $sql['fields']).'`) VALUES ('.implode(', ', $sql['values']).')';
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
			$result->query .= ' SET '.implode(', ', $sql);
		}
		if(!empty($conditions)) {
			$condStr = $this->buildConditions($conditions, $sql);
			$result->query .= ' WHERE ' . $condStr;
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
		}
		$result->execute($this->_conn);
		return $result;
	}

	public function query($modelVars, $sql, $binds = array()) {
		$result = new MysqlResultSet($modelVars);
		$result->query = $sql;

		$result->execute($this->_conn);
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

	public $name = null;

	public function __construct(&$modelVars) {
		$this->primaryKey = $modelVars['primaryKey'];
		$this->name = $modelVars['name'];
	}

	public function execute($conn) {
		if(CONFIG::$dryRun && preg_match("/((^update[\s]+)|(^insert[\s]+into[\s]+))/i", $this->query)) {
			out("execute query: ".$this->query);
			return true;
		}
		$this->statement = mysql_query($this->query, $conn);
		if(!$this->statement) {
			throw new DBErrorException(mysql_error());
		}
		if(preg_match("/^select[\s]+/i", $this->query)) {
			$this->numRows = mysql_num_rows($this->statement);
		}
		else {
			$this->numRows = mysql_affected_rows($conn);
		}
		if(isset($this->primaryKey) && !empty($this->primaryKey) && preg_match("/^insert[\s]+into[\s]+/i", $this->query)) {
			$this->id = mysql_insert_id($conn);
		}

		Log::query($this->query, $this->name, $this->numRows);
	}

	public function getArray() {
		return mysql_fetch_assoc($this->statement);
	}
}
}
