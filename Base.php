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

}

return Base::instance();
