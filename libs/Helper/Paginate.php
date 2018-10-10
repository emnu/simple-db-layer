<?php

class Paginate extends ModelObj {

	private $model = null;

	public $total = 0;

	public $pages = 1;

	public $current = 1;

	public $params = array(); // page, field, order

	public $order = 'asc';

	public $field = null;

	public $count = 0;

	private $paramList = array(
							'page'=>'page',
							'field'=>'field',
							'order'=>'order'
						);

	private $links = 5;

	private $start = null;

	private $tmpStart = null;

	private $end  = null;

	private $from = 1;

	private $to = 1;

	public function addPrefix($name) {
		foreach ($this->paramList as $key => $value) {
			$this->paramList[$key] = $name.$value;
		}
	}

	public function find($name, $conditions, $options = array()) {
		$this->model = parent::buildModel($name);

		$this->params = $_GET;

		$this->total = $this->model->count($conditions, $options);

		$this->pages = intval(($this->total / $this->model->rowPerPage));
		if(($this->total % $this->model->rowPerPage) > 0)
			$this->pages++;


		if(isset($this->params[$this->paramList['order']])) {
			$this->order = $this->params[$this->paramList['order']];
			if(!in_array($this->params[$this->paramList['order']], array('asc', 'desc'))) {
				$this->order = 'asc';
			}
		}

		if(isset($this->params[$this->paramList['field']]) && in_array($this->params[$this->paramList['field']], array_keys($this->model->columns))) {
			$this->field = $this->params[$this->paramList['field']];
			if(isset($this->params[$this->paramList['order']]) && in_array($this->params[$this->paramList['order']], array('asc', 'desc'))) {
				$this->order = $this->params[$this->paramList['order']];
			}
			else {
				$this->order = 'asc';
			}
		}

		if(isset($this->params[$this->paramList['page']])) {
			$this->current = $this->params[$this->paramList['page']];
		}

		$this->count = ($this->current - 1) * $this->model->rowPerPage;
		$this->from = $this->count + 1;
		$this->to = $this->from + $this->model->rowPerPage - 1;
		if($this->to > $this->total)
			$this->to = $this->total;

		if(!empty($this->field)) {
			$options['order'] = $this->field . ' ' . strtoupper($this->order);
		}

		$this->tmpStart = $this->start = (($this->current - $this->links) > 0)?$this->current - $this->links:1;
		$this->end = (($this->current + $this->links) < $this->pages)?$this->current + $this->links:$this->pages;

		$options['page'] = $this->current;

		return $this->model->find($conditions, $options);
	}

	public function count() {
		$this->count++;

		return $this->count;
	}

	public function summary($str) {
		$str = str_replace(array(':current', ':page', ':total', ':from', ':to'), array($this->current, $this->pages, $this->total, $this->from, $this->to), $str);

		return $str;
	}

	public function sort($field) {
		$params = $this->params;
		$params[$this->paramList['field']] = $field;

		if($this->field == $field) {
			if($this->order == 'asc') {
				$params[$this->paramList['order']] = 'desc';
			}
			else {
				$params[$this->paramList['order']] = 'asc';
			}
		}
		else {
			$params[$this->paramList['order']] = 'asc';
		}

		return $this->generateGet($params);
	}

	public function first() {
		$params = $this->params;
		$params[$this->paramList['page']] = 1;
		
		return $this->generateGet($params);
	}

	public function prev() {
		$params = $this->params;
		if(isset($params[$this->paramList['page']])) {
			$params[$this->paramList['page']]--;
		}
		else {
			$params[$this->paramList['page']] = $this->current-1;
		}
		
		if($params[$this->paramList['page']] < 1) {
			$params[$this->paramList['page']] = 1;
		}

		return $this->generateGet($params);
	}

	public function numbers() {
		$params = $this->params;

		$params[$this->paramList['page']] = $this->tmpStart;

		if($this->tmpStart > $this->end) {
			$this->tmpStart = $this->start;
			return false;
		}
		$this->tmpStart++;

		return array($params[$this->paramList['page']], $this->generateGet($params));
	}

	public function next() {
		$params = $this->params;
		if(isset($params[$this->paramList['page']])) {
			$params[$this->paramList['page']]++;
		}
		else {
			$params[$this->paramList['page']] = $this->current+1;
		}

		if($params[$this->paramList['page']] > $this->pages) {
			$params[$this->paramList['page']] = $this->pages;
		}

		return $this->generateGet($params);
	}

	public function last() {
		$params = $this->params;
		$params[$this->paramList['page']] = $this->pages;
		
		return $this->generateGet($params);
	}

	public function generateGet($params = array()) {
		if(empty($params)) {
			$params = $this->params;
		}

		$tmp = array();
		foreach ($params as $key => $value) {
			$tmp[] = $key . '=' . urlencode($value);
		}

		return '?' . implode('&', $tmp);
	}
}
