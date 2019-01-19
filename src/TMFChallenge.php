<?php

namespace Dashifen\TMFChallenge;

use Dashifen\TMFChallenge\Services\Lifecycle\Activator;
use Dashifen\TMFChallenge\Services\Lifecycle\Uninstaller;
use Dashifen\WPHandler\Handlers\AbstractPluginHandler;
use Dashifen\WPHandler\Hooks\HookException;

class TMFChallenge extends AbstractPluginHandler {

	// normally, we'd put this into a settings page or something like that.
	// but, to keep things simple, we're going to set it as a protected
	// constant so it's only visible to this class and any of its children.

	protected const FIXER_API_KEY = "a2b65d00aa8a6092003eed20a746ed40";

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
		return "the-motley-fool-challenge";
	}

    /**
     * initialize
     *
     * Uses addAction() and addFilter() to connect WordPress to the methods
     * of this object which are intended to be protected.
     *
     * @return void
     * @throws HookException
     */
	public function initialize(): void {

	    // disliking the structure of the register activation and
        // uninstallation hooks as I do, we can work the same magic as
        // those core functions here with out them.  to do so, we need
        // the folder and filename for our plugin.  luckily, we can get
        // that using our method above.  then, we use it to construct
        // activate and uninstall hooks as follows.

		$directory = $this->getPluginDirectory();
        $filename = sprintf("%s/%s.php", $directory, $directory);
        $this->addAction("activate_$filename", "activate");
        $this->addAction("uninstall_$filename", "uninstall");
	}

	/**
	 * getTickerTable
	 *
	 * Returns the name of the table that stores the indices on the
	 * fool exchange.
	 *
	 * @return string
	 */
	public function getTickerTable(): string {
		global $wpdb;
		return $wpdb->prefix . "fool_exchange_tickers";
	}

	/**
	 * getPriceTable
	 *
	 * Returns the name of the table that stores pricing information
	 * at various timestamps for the indices on the exchange.s
	 *
	 * @return string
	 */
	public function getPriceTable(): string {
		global $wpdb;
		return $wpdb->prefix . "fool_exchange_prices";
	}

	/**
	 * activate
	 *
	 * Executes the necessary behaviors when the plugin is activated.
	 * Relies on the Activator object to do its work to avoid cramming
	 * a bunch of additional code into this object for that purpose.
	 *
	 * @return void
	 * @throws TMFChallengeException
	 */
    protected function activate(): void {

    	// our activator needs to open and read the initial-data.csv in
	    // the plugin's assets folder.  it's a lot easier to identify the
	    // path to that folder here than it is in the Activator itself.
	    // so, we'll get a link to it here and pass it there.

		$dataFile = plugin_dir_path(__DIR__) . "assets/initial-data.csv";
	    (new Activator($this))->activate($dataFile);
	}

    /**
     * uninstall
     *
     * Executes the necessary behaviors once the plugin is deleted from
     * within the WordPress Dashboard.  Relies on the Uninstaller object
     * to do its work to avoid cramming a bunch of additional code into
     * this object for that purpose.
     *
     * @return void
     */
    protected function uninstall(): void {
	    (new Uninstaller($this))->uninstall();
	}
}
