<?php

namespace Dashifen\TMFChallenge;

use Dashifen\WPHandler\Handlers\AbstractPluginHandler;

class PluginController extends AbstractPluginHandler {
	public function __construct() {
		parent::__construct();

		$plugin = plugin_dir_path(dirname(__FILE__)) . "tmf-challenge.php";
		register_activation_hook($plugin, [$this, "activate"]);
		register_uninstall_hook($plugin, [$this, "uninstall"]);
	}

	public static function activate(): void {

	}

	public static function uninstall(): void {

	}

	/**
	 * getPluginDirectory
	 *
	 * Returns the name of the directory in which our concrete extension
	 * of this class resides.  Avoids the use of a ReflectionClass simply
	 * to get a simple string.
	 *
	 * @return string
	 */
	protected function getPluginDirectory(): string {
		return "tmf-challenge";
	}

	/**
	 * initialize
	 *
	 * Uses addAction() and addFilter() to connect WordPress to the methods
	 * of this object which are intended to be protected.
	 *
	 * @return void
	 */
	public function initialize(): void {

	}
}