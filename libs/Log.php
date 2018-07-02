<?php

class Log {
	static protected $queryLog = array();

	static function query($sql, $model, $affected) {
		if(CONFIG::$debug > 1) {
			self::$queryLog[] = array(
				'sql' => $sql,
				'model' => $model,
				'affected' => $affected
			);
		}
	}

	static function showQueries() {
		if(CONFIG::$debug > 1) {
			$out = '<table><tr><td>No.</td><td>SQL</td><td>Model</td><td>Affected Rows</td></tr>';
			foreach (self::$queryLog as $key => $value) {
				$out .= '<tr><td>'.($key+1).'</td><td>'.$value['sql'].'</td><td>'.$value['model'].'</td><td>'.$value['affected'].'</td></tr>';
			}
			$out .= '</table>';

			return $out;
		}

		return false;
	}
}