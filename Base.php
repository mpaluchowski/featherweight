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
			'protocol_force' => null,
			'page_default' => 'home',
			'title_default' => '',
			'page_include_before' => [],
			'page_include_after' => [],
			'directory_pages' => './pages/',
			'directory_extensions' => './ext/',
			'languages_available' => null,
			'language_default' => 'en',
		];
	}

	public function run() {
		$this->loadExtensions();

		$page = $this->getPage();
		$this->set('this_page', $page['name']);
		$this->set('title', $page['title']);

		$prefix = '';
		$pageFile = $page['name'];

		if ($this->config['languages_available']) {
			$this->set('language', $this->getLanguage($page['language']));
			$prefix = $this->get('language') . '-';
		}

		$this->set('canonical_url', $this->getCanonicalUrl($page['path']));

		echo $this->sandbox($prefix, $pageFile);
	}

	public function config($file) {
		$config = include $file;
		foreach ($config as $key => $value) {
			$this->config[$key] = $value;
		}
	}

	public function set($key, $value) {
		$this->config[$key] = $value;
	}

	public function get($key) {
		return $this->config[$key];
	}

	private function loadExtensions() {
		if (!is_dir($this->get('directory_extensions')))
			throw new Exception("Cannot load extensions from '" . $this->get('directory_extensions') . "'. Doesn't appear to be a directory.");

		foreach (scandir($this->get('directory_extensions')) as $file) {
			if (preg_match('/[A-Za-z0-9_\-]\.php/', $file)) {
				include $this->get('directory_extensions') . '/' . $file;
				$fileParts = explode('.', $file);
				$this->set($fileParts[0], new $fileParts[0]($this));
			}
		}
	}

	private function sandbox($prefix, $pageFile) {
		extract($this->config);
		ob_start();

		foreach ($this->config['page_include_before'] as $inclusion) {
			require $this->config['directory_pages']
					. $prefix
					. $inclusion
					. '.php';
		}

		require $this->config['directory_pages']
				. $prefix
				. $pageFile
				. '.php';

		foreach ($this->config['page_include_after'] as $inclusion) {
			require $this->config['directory_pages']
					. $prefix
					. $inclusion
					. '.php';
		}

		return ob_get_clean();
	}

	private function getCanonicalUrl($pagePath) {
		return $this->get('protocol_force')
				? $this->get('protocol_force')
				: isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']
						? 'https'
						: 'http'
			. '://'
			. $_SERVER['HTTP_HOST']
			. '/'
			. $pagePath;
	}

	private function getPage() {
		$url = parse_url($_SERVER['REQUEST_URI']);
		$url['path'] = ltrim( $url['path'], '/' );

		if (!empty($url['path'])) {
			foreach ($this->config['pages_available'] as $language => $pages) {
				if (array_key_exists($url['path'], $pages)) {
					return [
						'path' => $url['path'],
						'name' => $this->config['pages_available'][$language][$url['path']]['name'],
						'title' => $this->config['pages_available'][$language][$url['path']]['title'],
						'language' => $language,
						];
				}
			}
		}

		return [
			'path' => '',
			'name' => $this->config['page_default'],
			'title' => $this->config['title_default'],
			'language' => null
			];
	}

	private function getLanguage($language) {
		if (null !== $language) {
			setcookie('lang', $language);
		} else if (isset($_GET['lang'])
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

		if (!$language)
			$language = $this->config['language_default'];

		return $language;
	}

}

return Base::instance();
