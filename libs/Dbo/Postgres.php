<?php

class Postgres {

	protected $_conn = null;

	protected $_config = array(
		'type'     => 'postgres',
		'username' => 'root',
		'password' => '',
		'host'     => 'localhost',
		'port'     => 5432,
		'database' => ''
	);

	public function __construct($config) {
		$this->_config = array_merge($this->_config, $config);
		$this->connect();
	}

	public function connect() {
		$this->_conn = pg_connect("host=".$this->_config['host']." port=".$this->_config['port']." dbname=".$this->_config['database']." user=".$this->_config['username']." password=".$this->_config['password']);
	}

	public function check() {
		if(!pg_ping($this->_conn)) {
			$this->connect();
		}
	}

	public function getSchema($table) {
		$stid = pg_query($this->_conn, 'SELECT * FROM "'.$table.'"');

		$columns = array();
		for($i = 0; $i < pg_num_fields($stid); $i++) {
			$columns[pg_field_name($stid, $i)] = array(
				'type' => pg_field_type($stid, $i),
				'size' => pg_field_size($stid, $i)
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
				$tmpBind = '$' . $count;
				$bindNames[$tmpBind] = $value;
				$sql[$key] = $tmpBind;
				$count++;
			}
		}
		return $sql;
	}

	public function buildUpdateSQL($data, $modelVars, &$bindNames) {
		$sql = array();
		$count = 1;
		foreach($data as $key => $value) {
			if(in_array($key, array_keys($modelVars['columns']))) {
				$tmpBind = '$' . $count;
				$bindNames[$tmpBind] = $value;
				$sql[] = $key.' = '.$tmpBind;
				$count++;
			}
		}
		return $sql;
	}

	private function _buildConditions($conditions, $replace, &$bindNames, &$count) {
		$condStr = array();

		foreach ($conditions as $key => $value) {
			if(is_string($key)) {
				if(preg_match("/^(or|and)$/i", trim($key))) {
					$condStr[] = '('. implode(' '.strtoupper(trim($key)).' ', $this->_buildConditions($value, $replace, $bindNames, $count)) . ')';
				}
				elseif(!preg_match("/(\b(is|not|like|null)\b|[<>=!]+)/i", trim($key))) {
					$key = str_replace(array_keys($replace), $replace, $key);
					if(is_array($value)) {
						$tmpBindNames = array();
						foreach ($value as $v) {
							$tmpBind = '$'.$count;
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
						$tmpBind = '$' . $count;
						$bindNames[$tmpBind] = $value;
						$condStr[] = $key . ' = ' . $tmpBind;
						$count++;
					}
				}
				else {
					$key = str_replace(array_keys($replace), $replace, $key);
					if(is_array($value)) {
						$tmpBindNames = array();
						foreach ($value as $v) {
							$tmpBind = '$'.$count;
							$bindNames[$tmpBind] = $v;
							$tmpBindNames[] = $tmpBind;
							$count++;
						}
						$condStr[] = $key . ' IN (' . implode(', ', $tmpBindNames) . ')';
					}
					else {
						$tmpBind = '$' . $count;
						$bindNames[$tmpBind] = $value;
						$condStr[] = $key . ' ' . $tmpBind;
						$count++;
					}
				}
			}
			else {
				if(is_array($value)) {
					$condStr[] = '('. implode(' AND ', $this->_buildConditions($value, $replace, $bindNames, $count)) . ')';
				}
				else {
					$value = str_replace(array_keys($replace), $replace, $value);
					$condStr[] = $this->_quotes($value);
				}
			}
		}

		return $condStr;
	}

	public function buildConditions($conditions, $replace, &$bindNames) {
		$count = count($bindNames) + 1;
		return implode(' AND ', $this->_buildConditions($conditions, $replace, $bindNames, $count));
	}

	private function _addQuote($val) {
		return '"'.$val.'"';
	}

	private function _quotedColumn($columns) {
		return array_combine(array_keys($columns), array_map(array($this, '_addQuote'), array_keys($columns)));
	}

	public function find($modelVars, $conditions = array(), $options = null) {
		$result = new PostgresResultSet($modelVars);

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

		$result->query = 'SELECT '.implode(', ', $fields).' FROM "'.$modelVars['table'].'" '.$modelVars['name'];

		if((isset($options['contain']) && !empty($options['contain'])) || !empty($conditions)) {
			$replace = $this->_quotedColumn($modelVars['columns']);
		}

		if(isset($options['contain']) && !empty($options['contain'])) {
			foreach ($options['contain'] as $key => $value) {
				$result->query .= ' '.$value['type'].' JOIN "'.$value['table'].'" '.$key.' ON ('.$this->buildConditions($value['on'], $replace, $result->bindArr).')';
			}
		}

		if(!empty($conditions)) {
			$condStr = $this->buildConditions($conditions, $replace, $result->bindArr);
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
		$result = new PostgresResultSet($modelVars);
		$result->query = 'SELECT COUNT(*) as CNT FROM "'.$modelVars['table'].'" '.$modelVars['name'] ;

		if((isset($options['contain']) && !empty($options['contain'])) || !empty($conditions)) {
			$replace = $this->_quotedColumn($modelVars['columns']);
		}

		if(isset($options['contain']) && !empty($options['contain'])) {
			foreach ($options['contain'] as $key => $value) {
				$result->query .= ' '.$value['type'].' JOIN "'.$value['table'].'" '.$key.' ON ('.$this->buildConditions($value['on'], $replace, $result->bindArr).')';
			}
		}

		if(!empty($conditions)) {
			$condStr = $this->buildConditions($conditions, $replace, $result->bindArr);
			$result->query .= ' WHERE ' . $condStr;
		}
		$result->execute($this->_conn);
		$count = $result->getArray();
		return $count['CNT'];
	}

	public function insert($modelVars, $data) {
		$result = new PostgresResultSet($modelVars);
		$result->query = 'INSERT INTO "'.$modelVars['table'].'"';

		if(!empty($data)) {
			$sql = $this->buildInsertSQL($data, $modelVars, $result->bindArr);
			$result->query .= ' ('.implode(', ', array_keys($sql)).') VALUES ('.implode(', ', $sql).')';
		}
		$result->execute($this->_conn);
		return $result;
	}

	public function update($modelVars, $data, $conditions) {
		$result = new PostgresResultSet($modelVars);
		$result->query = 'UPDATE "'.$modelVars['table'].'"';

		if(!empty($data)) {
			$sql = $this->buildUpdateSQL($data, $modelVars, $result->bindArr);
			$result->query .= ' SET '.implode(', ', $sql);
		}
		if(!empty($conditions)) {
			$replace = $this->_quotedColumn($modelVars['columns']);
			$condStr = $this->buildConditions($conditions, $replace, $result->bindArr);
			$result->query .= ' WHERE ' . $condStr;
		}
		$result->execute($this->_conn);
		return $result;
	}

	public function delete($modelVars, $conditions, $options = null) {
		$result = new PostgresResultSet($modelVars);
		$result->query = 'DELETE FROM '.$modelVars['table'];

		if(!empty($conditions)) {
			$condStr = $this->buildConditions($conditions, $result->bindArr);
			$result->query .= ' WHERE ' . $condStr;
		}
		$result->execute($this->_conn);
		return $result;
	}

	public function query($modelVars, $sql) {
		$result = new PostgresResultSet($modelVars);
		$result->query = $sql;

		$result->execute($this->_conn);
		return $result;
	}
}

class StatementHolder {
	public static $preparedStmt = array();
}

class PostgresResultSet {
	public $id = null;

	public $query = null;

	protected $statement = null;

	public $primaryKey = null;

	public $numRows = 0;

	public $bindArr = array();

	public $name = null;

	public function __construct(&$modelVars) {
		$this->primaryKey = $modelVars['primaryKey'];
		$this->name = $modelVars['name'];
	}

	public function execute($conn) {
		if(isset($this->primaryKey) && !empty($this->primaryKey) && preg_match("/^insert[\s]+into[\s]+/i", $this->query)) {
			$this->query .= ' RETURNING insert_id';
		}
		$stmtname = crc32($this->query);
		if(!in_array($stmtname, StatementHolder::$preparedStmt)) {
			StatementHolder::$preparedStmt[] = $stmtname;
			$this->statement = pg_prepare($conn, $stmtname, $this->query);
		}

		if(CONFIG::$dryRun && preg_match("/((^update[\s]+)|(^insert[\s]+into[\s]+))/i", $this->query)) {
			out("execute query: ".$this->query);
			pr($this->bindArr);
			return true;
		}
		$this->statement = pg_execute($conn, $stmtname, $this->bindArr);
		if($this->statement == false) {
			die(pg_last_error($conn)."\n".$this->query);
		}

		if(isset($this->primaryKey) && !empty($this->primaryKey) && preg_match("/^insert[\s]+into[\s]+/i", $this->query)) {
			$insertRow = pg_fetch_row($this->statement);
			$this->id = $insertRow[0];
		}

		$this->numRows = pg_affected_rows($this->statement);

		Log::query($this->query, $this->name, $this->numRows);
	}

	public function getArray() {
		$array = pg_fetch_assoc($this->statement);
		if(!is_array($array)) {
			pg_free_result($this->statement);
		}
		return $array;
	}
}
