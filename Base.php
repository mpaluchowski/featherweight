<?php

class Base {

	public static function instance() {
		static $inst = null;
		if ($inst === null)
			$inst = new Base();
		return $inst;
	}

	private function __construct() {}
	private function __clone() {}

}

return Base::instance();
