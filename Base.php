<?php

class Base {

	/** Holds the app configuration */
	private
		$config;

	/**
	 * Get an instance of the framework.
	 *
	 * @return Singleton instance of Base.
	 */
	public static function instance() {
		static $inst = null;
		if ($inst === null)
			$inst = new Base();
		return $inst;
	}

	/** Not allowed. Base is a singleton. */
	private function __clone() {}

	/** Not allowed. Base is a singleton. */
	private function __construct() {
		$this->config = [
			'protocol_force' => null,
			'pages_available' => [],
			'page_default' => 'home',
			'page_base' => '/',
			'page_include_before' => [],
			'page_include_after' => [],
			'directory_pages' => './pages/',
			'directory_extensions' => './ext/',
			'languages_available' => null,
			'language_default' => 'en',
		];
	}

	/**
	 * Process the page request. Usually the only method required in the file
	 * that receives the request, after setting up the configuration.
	 *
	 * Will load extensions, custom configurations, find out the right language
	 * and eventually output the page contents.
	 */
	public function run() {
		$this->loadExtensions();

		$page = $this->getPage();
		$this->set('this_page', $page['name']);

		$prefix = '';
		$pageFile = $page['name'];

		if ($this->config['languages_available']) {
			$this->set('language', $this->getLanguage($page['language']));
			$prefix = $this->get('language') . '-';
		}

		$this->set('url_canonical', $this->getCanonicalUrl($page['path']));
		$this->set('url_root', $this->getRootUrl(true));
		$this->set('url_page', $page['path']);

		echo $this->sandbox($prefix, $pageFile);
	}

	/**
	 * Load a custom configuration file for the framework. It's expecting the
	 * file inclusion to return an array of key -> value configuration settings.
	 * Every key-value pair will be loaded directly into the framework
	 * config store, overwriting defaults when keys match.
	 *
	 * @param file Configuration file for inclusion, should return an array.
	 */
	public function config($file) {
		$config = include $file;

		if (!is_array($config))
			throw new Exception("The configuration file $file must return an array");

		foreach ($config as $key => $value) {
			$this->config[$key] = $value;
		}
	}

	/**
	 * Set a single configuration variable.
	 *
	 * @param key Key to store variable under.
	 * @param value Value of the variable to store.
	 */
	public function set($key, $value) {
		$this->config[$key] = $value;
	}

	/**
	 * Fetch a single configuration variable. Will fail if supplied key is
	 * missing from the store.
	 *
	 * @param key Key to fetch the value for.
	 */
	public function get($key) {
		return $this->config[$key];
	}

	/**
	 * Load extensions, if available.
	 *
	 * If there's a valid extensions directory configured, it'll load all of
	 * the ones it finds in the directory, initializing their objects and
	 * storing the instances under variables with the same name as the classes.
	 */
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

	/**
	 * Loads the views and passes them this instances configuration as variables.
	 * If there are any files configured to include before or after the main
	 * page (eg. header, footer), these will be loaded here.
	 *
	 * @param prefix Prefix to use for every file name included. Usually the
	 * language.
	 * @param pageFile File of the main page to include.
	 */
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

	/**
	 * Produce the canonical URL for a page, from its protocol, hostname,
	 * base directory to page name.
	 *
	 * @parama pagePath Path to the page to include in the canonical URL, after
	 * hostname and base directory.
	 */
	private function getCanonicalUrl($pagePath) {
		return $this->getRootUrl(true)
			. $pagePath;
	}

	/**
	 * Produe the root URL, optionally appending the base directory. Takes care
	 * of checking whether current page is in HTTPS or not, unless app config
	 * enforces a certain protocol.
	 *
	 * @param includePageBase Whether to append the base directory.
	 */
	private function getRootUrl($includePageBase = false) {
		return $this->get('protocol_force')
				? $this->get('protocol_force')
				: isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']
						? 'https'
						: 'http'
			. '://'
			. $_SERVER['HTTP_HOST']
			. ($includePageBase ? $this->get('page_base') : '/');
	}

	/**
	 * Parse the URL to extract the page to load. Retrieves the localized page
	 * name from the URL and finds out the corresponding view and language
	 * based on cofiguration. If the URL doesn't include a page, it returns the
	 * default.
	 *
	 * @return Array with the found page's URL path, view name and language.
	 */
	private function getPage() {
		$url = parse_url($_SERVER['REQUEST_URI']);
		$url['path'] = ltrim( $url['path'], $this->get('page_base') );

		if (!empty($url['path'])) {
			foreach ($this->config['pages_available'] as $language => $pages) {
				if (array_key_exists($url['path'], $pages)) {
					return [
						'path' => $url['path'],
						'name' => $this->config['pages_available'][$language][$url['path']],
						'language' => $language,
						];
				}
			}
		}

		return [
			'path' => '',
			'name' => $this->config['page_default'],
			'language' => null
			];
	}

	/**
	 * Decide which language to load a view in. Will check in succession a few
	 * ways of determining the language, from URL-based, through cookies with
	 * past preferences to content negotiation with the browser. Will only load
	 * a language if configured as supported by the app, otherwise loads the
	 * configured default.
	 *
	 * @param language Explicit language to load, if any. Will be stored in
	 * cookie for future reloads.
	 * @return Language that should be loaded, either found out from one of the
	 * supported places or the default.
	 */
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
