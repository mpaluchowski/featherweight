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
			'languages_available' => null,
			'language_default' => 'en',
		];
	}

	public function run() {
		$pageFile = $this->getPage();

		if ($this->config['languages_available']){
			$pageFile = $this->getLanguage() . '-' . $pageFile;
		}

		include $this->config['directory_pages']
				. $pageFile
				. '.php';
	}

	public function config($file) {
		$config = include $file;
		foreach ($config as $key => $value) {
			$this->config[$key] = $value;
		}
	}

	private function getPage() {
		$url = parse_url($_SERVER['REQUEST_URI']);
		$url['path'] = ltrim( $url['path'], '/' );

		if (!empty($url['path'])) {
			foreach ($this->config['pages_available'] as $language => $pages) {
				if (array_key_exists($url['path'], $pages)) {
					setcookie('lang', $language);
					$thisPage = $this->config['pages_available'][$language][$url['path']]['name'];
					break;
				}
			}
		}

		return empty( $url['path'] ) || !isset( $thisPage )
				? $this->config['page_default']
				: $thisPage;
	}

	private function getLanguage() {
		if (isset($_GET['lang'])
				&& in_array($_GET['lang'], $this->config['languages_available'], true)) {
			setcookie('lang', $_GET['lang']);
			$language = $_GET['lang'];
		} else  if (isset($_COOKIE['lang'])
				&& in_array($_COOKIE['lang'], $this->config['languages_available'], true)) {
			/* Adhere to preference */
			$language = $_COOKIE['lang'];
		} else {
			/* Content negotiation */
			preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $acceptedLangsParse);

			if (count($acceptedLangsParse[1])) {
				// create a list like "en" => 0.8
				$acceptedLangs = array_combine(
					preg_replace('/(\w{2})(-\w{2})?/i', '$1', $acceptedLangsParse[1]),
					$acceptedLangsParse[4]
				);

				// set default to 1 for any without q factor
				foreach ($acceptedLangs as $key => $val) {
					if ($val === '') $acceptedLangs[$key] = 1;
				}

				// sort list based on value
				arsort($acceptedLangs, SORT_NUMERIC);

				foreach($acceptedLangs as $key => $val)
				if (in_array($key, $this->config['languages_available'], true)) {
					$language = $key;
					break;
				}
			}
		}

		if (!isset($language))
			$language = $this->config['language_default'];

		return $language;
	}

}

return Base::instance();
