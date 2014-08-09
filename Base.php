<?php

class Base {

	private
		$config;

	public static function instance() {
		static $inst = null;
		if ($inst === null)
			$inst = new Base();
		return $inst;
	}

	private function __clone() {}

	private function __construct() {
		$this->config = [
			'page_default' => 'home',
			'directory_pages' => '/pages/',
		];
	}

	public function run() {
		include $this->config['directory_pages']
				. $this->config['page_default']
				. '.php';
	}

	public function config($file) {
		$config = include $file;
		foreach ($config as $key => $value) {
			$this->config[$key] = $value;
		}
	}

}

return Base::instance();
