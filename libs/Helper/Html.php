<?php

class Html extends ModelObj {

	protected $css = '';

	protected $js = '';

	protected $content;

	protected $inline = true;

	protected $layout = 'default';

	public function fetch($portion) {
		return $this->$portion;
	}

	public function script($file, $options = array()) {
		$this->inline = true;
		if(isset($options['inline']) && $options['inline'] == false) {
			$this->inline = false;
			ob_start();
		}

		if(is_array($file)) {
			foreach ($file as $f) {
				echo '<script type="text/javascript" src="'.$f.'"></script>'.PHP_EOL;
			}
		}
		else {
			echo '<script type="text/javascript" src="'.$file.'"></script>'.PHP_EOL;
		}

		if(!$this->inline) {
			$this->js .= ob_get_clean();
		}
	}

	public function style($file, $options = array()) {
		$this->inline = true;
		if(isset($options['inline']) && $options['inline'] == false) {
			$this->inline = false;
			ob_start();
		}

		if(is_array($file)) {
			foreach ($file as $f) {
				echo '<link href="'.$f.'" media="screen" rel="stylesheet" type="text/css" />'.PHP_EOL;
			}
		}
		else {
			echo '<link href="'.$file.'" media="screen" rel="stylesheet" type="text/css" />'.PHP_EOL;
		}

		if(!$this->inline) {
			$this->css .= ob_get_clean();
		}
	}

	public function scriptBlock($options = array())	{
		$this->inline = true;
		if(isset($options['inline']) && !$options['inline']) {
			$this->inline = false;
			ob_start();
		}

		echo '<script type="text/javascript">'.PHP_EOL;
	}

	public function scriptBlockEnd() {
		echo '</script>'.PHP_EOL;
		if($this->inline) {

		}
		else {
			$this->js .= ob_get_clean();
		}
	}

	public function contentStart(){
		ob_start();
	}

	public function contentEnd() {
		$this->content = ob_get_clean();
	}

	public function setLayout($layout) {
		$this->layout = $layout;
	}

	public function getLayout() {
		return $this->layout;
	}

	public function body($file) {
		ob_start();
		include($file);
		$this->content = ob_get_clean();
	}
}
