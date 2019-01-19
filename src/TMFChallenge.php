<?php

namespace Dashifen\TMFChallenge;

use Dashifen\TMFChallenge\Services\Lifecycle\Activator;
use Dashifen\TMFChallenge\Services\Lifecycle\Uninstaller;
use Dashifen\TMFChallenge\Services\TMFExchange;
use Dashifen\WPHandler\Handlers\AbstractPluginHandler;
use Dashifen\WPHandler\Hooks\HookException;
use Latitude\QueryBuilder\Engine\MySqlEngine;
use Latitude\QueryBuilder\QueryFactory;
use function Latitude\QueryBuilder\alias;
use function Latitude\QueryBuilder\on;

class TMFChallenge extends AbstractPluginHandler {
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

		// next, we want to add our endpoint for the fool exchange page.
		// likely, in a production tool, we'd need query variables for
		// ticker symbols and dates and maybe even times.  here, though,
		// for our challenge, we'll keep things more simple.

		$this->addAction("init", "addExchangeEndpoint");
		$this->addFilter("template_include", "showExchange");

		// when the exchange mounts, it fires an AJAX request for its data.
		// these actions handle that.  note, we'll allow both anonymous and
		// logged-in visitors to handle these requests.  then, we'll add
		// our scripts so that the app actually does stuff!

		$this->addAction("wp_ajax_nopriv_fetch-exchange", "fetchExchange");
		$this->addAction("wp_ajax_fetch-exchange", "fetchExchange");
		$this->addAction("wp_enqueue_scripts", "enqueueScripts");
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

	/**
	 * addExchangeEndpoint
	 *
	 * Adds the the-fool-exchange endpoint to our URL.
	 *
	 * @return void
	 */
	protected function addExchangeEndpoint(): void {
		add_rewrite_endpoint("the-fool-exchange", EP_ROOT);

		// this is probably overkill for our purposes here, but if the
		// version number bumps, we probably need to flush our rewrite
		// endpoints.  we set a version constant in the primary plugin
		// file.  we can use it here to see if we need to flush.

		if (get_option("tmf-challenge-last-flush", 0) !== TMF_CHALLENGE_VERSION) {
			add_option("tmf-challenge-last-flush", TMF_CHALLENGE_VERSION);
			flush_rewrite_rules();
		}
	}

	/**
	 * showExchange
	 *
	 * Determines if we're at the fool exchange endpoint and then
	 * shows it if we are.
	 *
	 * @param string $template
	 *
	 * @return string
	 */
	protected function showExchange(string $template): string {
		if ($this->isFoolExchange()) {

			// anytime a plugin adds a template, we want to give the theme
			// the opportunity to override the default.  if the theme has a
			// template file named "the-fool-exchange.php" then we use it.
			// otherwise, we fall back on our default template in the assets
			// folder.  this is overkill for the purpose of this challenge,
			// but I wanted to show that I know the way it's supposed to
			// work, even if it's unnecessary in this context.

			$template = locate_template("the-fool-exchange.php");

			if (empty($template)) {
				$template = plugin_dir_path(__DIR__) . "assets/default-template.php";
			}
		}

		return $template;
	}

	/**
	 * isFoolExchange
	 *
	 * If we're at our exchange's endpoint, return true.
	 *
	 * @return bool
	 */
	private function isFoolExchange(): bool {

		// if we try to get the-fool-exchange query variable and it's not
		// equal to the default value of false, then we're on our exchange
		// page.

		return get_query_var("the-fool-exchange", false) !== false;
	}

	/**
	 * fetchExchange
	 *
	 * When the client-side requests our exchange data, this method gives it
	 * to them.
	 *
	 * @return void
	 */
	protected function fetchExchange(): void {
		$exchangeData = $this->fetchExchangeData();
		$exchangeRates = $this->fetchExchangeRates($exchangeData);

		echo json_encode([
			"prices" => $exchangeData,
			"rates"  => $exchangeRates,
		]);

		wp_die();
	}

	/**
	 * fetchExchangeData
	 *
	 * Reaches into the database and gets the prices that we've loaded into
	 * it.
	 *
	 * @return array
	 */
	protected function fetchExchangeData(): array {
		global $wpdb;
		$priceTable = $this->getPriceTable();
		$tickerTable = $this->getTickerTable();

		// if we were working in a production environment, we could probably
		// expect limitations (e.g. a set of symbols, a specific date range,
		// even currencies that aren't USD or SGD).  but, for the purposes
		// of this challenge, those are out of scope.  therefore, we send
		// back the information we have in the database in its entirety.

		$query = (new QueryFactory(new MySqlEngine()))
			->select("ticker", "date", "time", "price")
			->from(alias($priceTable, "p"))
			->join(alias($tickerTable, "t"), on("p.ticker_id", "t.ticker_id"))
			->orderBy("ticker")
			->orderBy("date")
			->orderBy("time")
			->compile();

		return $wpdb->get_results($query->sql(), ARRAY_A);
	}

	/**
	 * fetchExchangeRates
	 *
	 * Given the exchange data, return exchange rate for USD => SGD for
	 * the given dates.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	protected function fetchExchangeRates(array $data): array {
		$exchange = new TMFExchange();

		// we can grab all of the dates in $data be extracting them with
		// an array_map().  the callback for it just returns each date in
		// the array.  but, since the dates are repetitious, we'll then
		// run it through array_unique() so we don't bother the API too
		// much.

		$dates = array_map(function ($datum) {
			return $datum["date"];
		}, $data);

		$dates = array_unique($dates);
		foreach ($dates as $date) {
			$rates[$date] = $exchange->getRate($date);
		}

		// if we run into a problem, then maybe we don't find any dates.
		// the null coalescing operator makes sure we return an array from
		// this function regardless to fulfill the type hint.  in a
		// production environment, we'd need to watch out for problems
		// like this, but for the purposes of this challenge, I didn't
		// worry about it.

		return $rates ?? [];
	}

	/**
	 * enqueueScripts
	 *
	 * If we're on the fool exchange page, enqueue its scripts.
	 *
	 * @return void
	 */
	protected function enqueueScripts(): void {
		if ($this->isFoolExchange()) {
			$jsHandle = $this->enqueue("assets/tmf-challenge.js");

			// on the front-end, the WP default ajax information isn't
			// available unless a person is logged in.  so, we'll localize
			// our script as follows to include that information all the
			// time.

			wp_localize_script($jsHandle, "tmfAjax", [
				"url" => admin_url("admin-ajax.php"),
			]);
		}
	}
}
