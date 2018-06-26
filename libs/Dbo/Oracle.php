<?php

class Oracle {

	protected $_conn = null;

	protected $_config = array(
		'type'     => 'oracle',
		'username' => 'root',
		'password' => '',
		'host' => 'localhost',
		'port' => 1521,
		'database' => ''
	);

	public function __construct($config) {
		$this->_config = array_merge($this->_config, $config);
		$this->connect();
	}

	public function connect() {
		$this->_conn = oci_connect($this->_config['username'], $this->_config['password'], $this->_config['host'].':'.$this->_config['port'].'/'.$this->_config['database']);
	}

	public function check() {
		if(!$this->_conn) {
			$e = oci_error();
			error_log(date('Y-m-d H:i:s') . "oci_error: " . $e['message'] . "\n", 3, APP_PATH."logs".DIRECTORY_SEPARATOR."error.log");
			$this->connect();
		}
	}

	public function getSchema($table) {
		$stid = oci_parse($this->_conn, 'SELECT * FROM '.$table);
		oci_execute($stid, OCI_DESCRIBE_ONLY);

		$columns = array();
		for($i = 1; $i <= oci_num_fields($stid); $i++) {
			$columns[oci_field_name($stid, $i)] = array(
				'type' => oci_field_type($stid, $i),
				'size' => oci_field_size($stid, $i)
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
			$replace[] = $value[1].'."'.$value[2].'"';
		}
		return str_replace($find, $replace, $str);
	}

	private function _fields($fields, $prefix) {
		$tmp = array();
		foreach ($fields as $key => $value) {
			if(is_string($key)) {
				$tmp[$key] = $this->_quotes($value).' AS '.$key;
			}
			else {
				$tmp[$value] = $prefix.'."'.$value.'"';
			}
		}
		return $tmp;
	}

	public function buildInsertSQL($data, $modelVars,  &$bindNames) {
		$sql = array();
		$count = 1;
		foreach($data as $key => $value) {
			if(in_array($key, array_keys($modelVars['columns']))) {
				if(preg_match('/.(next|curr)val\b/i', $value)) {
					$sql[$key] = $value;
				}
				else {
					$tmpBind = ':bv_' . $count;
					$bindNames[$tmpBind] = $value;
					$sql[$key] = $tmpBind;
					$count++;
				}
			}
		}

		// insert primary key
		if((!in_array($modelVars['primaryKey'], array_keys($sql)) || (isset($sql[$modelVars['primaryKey']]) && empty($sql[$modelVars['primaryKey']]))) && !empty($modelVars['sequence'])) {
			$sql[$modelVars['primaryKey']] = $modelVars['sequence'];
		}
		return $sql;
	}

	public function buildUpdateSQL($data, $modelVars, &$bindNames) {
		$sql = array();
		$count = 1;
		foreach($data as $key => $value) {
			if(in_array($key, array_keys($modelVars['columns']))) {
				if(preg_match('/.(next|curr)val\b/i', $value)) {
					$sql[] = $key.' = '.$value;
				}
				else {
					$tmpBind = ':bv_' . $count;
					$bindNames[$tmpBind] = $value;
					$sql[] = $key.' = '.$tmpBind;
					$count++;
				}
			}
		}
		return $sql;
	}

	private function _buildConditions($conditions, &$bindNames, &$count) {
		$condStr = array();

		foreach ($conditions as $key => $value) {
			if(is_string($key)) {
				if(preg_match("/^(or|and)$/i", trim($key))) {
					$condStr[] = '('. implode(' '.strtoupper(trim($key)).' ', $this->_buildConditions($value, $bindNames, $count)) . ')';
				}
				elseif(!preg_match("/(\b(is|not|like|null)\b|[<>=!]+)/i", trim($key))) {
					if(is_array($value)) {
						$tmpBindNames = array();
						foreach ($value as $v) {
							$tmpBind = ':bc_'.$count;
							$bindNames[$tmpBind] = $v;
							$tmpBindNames[] = $tmpBind;
							$count++;
						}
						$condStr[] = $key . ' IN (' . implode(', ', $tmpBindNames) . ')';
					}
					elseif(is_null($value)) {
						$condStr[] = $key . ' IS NULL';
					}
					else {
						$tmpBind = ':bc_' . $count;
						$bindNames[$tmpBind] = $value;
						$condStr[] = $key . ' = ' . $tmpBind;
						$count++;
					}
				}
				else {
					if(is_array($value)) {
						$tmpBindNames = array();
						foreach ($value as $v) {
							$tmpBind = ':bc_'.$count;
							$bindNames[$tmpBind] = $v;
							$tmpBindNames[] = $tmpBind;
							$count++;
						}
						$condStr[] = $key . ' IN (' . implode(', ', $tmpBindNames) . ')';
					}
					else {
						$tmpBind = ':bc_' . $count;
						$bindNames[$tmpBind] = $value;
						$condStr[] = $key . ' ' . $tmpBind;
						$count++;
					}
				}
			}
			else {
				if(is_array($value)) {
					$condStr[] = '('. implode(' AND ', $this->_buildConditions($value, $bindNames, $count)) . ')';
				}
				else {
					$condStr[] = $this->_quotes($value);
				}
			}
		}

		return $condStr;
	}

	public function buildConditions($conditions, &$bindNames) {
		$count = count($bindNames) + 1;
		return implode(' AND ', $this->_buildConditions($conditions, $bindNames, $count));
	}

	public function find($modelVars, $conditions = array(), $options = null) {
		$result = new OracleResultSet($modelVars);

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

		$result->query = 'SELECT '.implode(', ', $fields).' FROM '.$modelVars['table'].$partition.' '.$modelVars['name'];

		if(isset($options['contain']) && !empty($options['contain'])) {
			foreach ($options['contain'] as $key => $value) {
				$result->query .= ' '.$value['type'].' JOIN '.$value['table'].' '.$key.' ON ('.$this->buildConditions($value['on'], $result->bindArr).')';
			}
		}

		if(!empty($conditions)) {
			$condStr = $this->buildConditions($conditions, $result->bindArr);
			$result->query .= ' WHERE ' . $condStr;
		}

		if(isset($options['order']) && !empty($options['order'])) {
			$result->query .= ' ORDER BY ' . $options['order'];
		}

		if(isset($options['page']) && !empty($options['page'])) {
			if(isset($options['limit']) && !empty($options['limit'])) {
				$modelVars['rowPerPage'] = $options['limit'];
			}
			$rnStart = (($options['page'] - 1) * $modelVars['rowPerPage']) + 1;
			$rnEnd = $rnStart + $modelVars['rowPerPage'] - 1;
			$result->bindArr[':rn_0'] = $rnStart;
			$result->bindArr[':rn_1'] = $rnEnd;
			$result->query = 'SELECT * FROM (SELECT A.*, ROWNUM rn FROM (' . $result->query . ') A WHERE ROWNUM <= :rn_1) WHERE rn >= :rn_0';
		}
		elseif(isset($options['limit']) && !empty($options['limit'])) {
			$result->bindArr[':rn_0'] = $options['limit'];
			$result->query = 'SELECT * FROM (' . $result->query . ') WHERE ROWNUM <= :rn_0';
		}

		$result->execute($this->_conn);
		return $result;
	}

	public function count($modelVars, $conditions = array(), $options = null) {
		$result = new OracleResultSet($modelVars);

		$partition = '';
		if(isset($options['partition']) && !empty($options['partition'])) {
			$partition = ' PARTITION ('.$options['partition'].')';
		}

		$result->query = 'SELECT COUNT(*) as CNT FROM '.$modelVars['table'].$partition.' '.$modelVars['name'] ;

		if(isset($options['contain']) && !empty($options['contain'])) {
			foreach ($options['contain'] as $key => $value) {
				$result->query .= ' '.$value['type'].' JOIN '.$value['table'].' '.$key.' ON ('.$this->buildConditions($value['on'], $result->bindArr).')';
			}
		}

		if(!empty($conditions)) {
			$condStr = $this->buildConditions($conditions, $result->bindArr);
			$result->query .= ' WHERE ' . $condStr;
		}
		$result->execute($this->_conn);
		$count = $result->getArray();
		return $count['CNT'];
	}

	public function insert($modelVars, $data) {
		$result = new OracleResultSet($modelVars);
		$result->query = 'INSERT INTO '.$modelVars['table'];

		if(!empty($data)) {
			$sql = $this->buildInsertSQL($data, $modelVars, $result->bindArr);
			$result->query .= ' ('.implode(', ', array_keys($sql)).') VALUES ('.implode(', ', $sql).')';
		}
		$result->execute($this->_conn);
		return $result;
	}

	public function update($modelVars, $data, $conditions, $options = null) {
		$result = new OracleResultSet($modelVars);

		$partition = '';
		if(isset($options['partition']) && !empty($options['partition'])) {
			$partition = ' PARTITION ('.$options['partition'].')';
		}

		$result->query = 'UPDATE '.$modelVars['table'].$partition;

		if(!empty($data)) {
			$sql = $this->buildUpdateSQL($data, $modelVars, $result->bindArr);
			$result->query .= ' SET '.implode(', ', $sql);
		}
		if(!empty($conditions)) {
			$condStr = $this->buildConditions($conditions, $result->bindArr);
			$result->query .= ' WHERE ' . $condStr;
		}
		$result->execute($this->_conn);
		return $result;
	}

	public function delete($modelVars, $conditions, $options = null) {
		$result = new OracleResultSet($modelVars);
		$result->query = 'DELETE FROM '.$modelVars['table'];

		if(!empty($conditions)) {
			$condStr = $this->buildConditions($conditions, $result->bindArr);
			$result->query .= ' WHERE ' . $condStr;
		}
		$result->execute($this->_conn);
		return $result;
	}

	public function query($modelVars, $sql) {
		$result = new OracleResultSet($modelVars);
		$result->query = $sql;

		$result->execute($this->_conn);
		return $result;
	}
}

class OracleResultSet {
	public $id = null;

	public $query = null;

	protected $statement = null;

	public $primaryKey = null;

	public $numRows = 0;

	public $bindArr = array();

	public function __construct(&$modelVars) {
		$this->primaryKey = $modelVars['primaryKey'];
	}

	public function execute($conn) {
		if(isset($this->primaryKey) && !empty($this->primaryKey) && preg_match("/^insert[\s]+into[\s]+/i", $this->query)) {
			$this->query .= ' RETURNING '.$this->primaryKey.' INTO :INSERT_ID';
			$this->statement = oci_parse($conn, $this->query);
			oci_bind_by_name($this->statement, ':INSERT_ID', $this->id, -1, SQLT_INT);			
		}
		else {
			$this->statement = oci_parse($conn, $this->query);
		}

		if(!empty($this->bindArr)) {
			foreach ($this->bindArr as $key => $value) {
				oci_bind_by_name($this->statement, $key, $this->bindArr[$key]);
			}
		}

		if(CONFIG::$dryRun && preg_match("/((^update[\s]+)|(^insert[\s]+into[\s]+))/i", $this->query)) {
			out("execute query: ".$this->query);
			pr($this->bindArr);
			return true;
		}
		if(oci_execute($this->statement) == false) {
			$e = oci_error($conn);
			die($e['message']."\n".$this->query);
		}
		$this->numRows = oci_num_rows($this->statement);
	}

	public function getArray() {
		$array = oci_fetch_array($this->statement, OCI_ASSOC+OCI_RETURN_NULLS);
		if(!is_array($array)) {
			oci_free_statement($this->statement);
		}
		return $array;
	}
}
