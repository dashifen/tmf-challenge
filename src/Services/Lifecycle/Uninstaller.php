<?php

namespace Dashifen\TMFChallenge\Services\Lifecycle;

use Dashifen\TMFChallenge\TMFChallenge;

class Uninstaller {
	/**
	 * @var TMFChallenge
	 */
	protected $tmfChallenge;

	/**
	 * Uninstaller constructor.
	 *
	 * @param TMFChallenge $tmfChallenge
	 */
	public function __construct(TMFChallenge $tmfChallenge) {
		$this->tmfChallenge = $tmfChallenge;
	}

	public function uninstall(): void {
		global $wpdb;

		// the only thing we have to do when uninstalling our plugin is
		// delete the database store of the data and then remove the tables.
		// note:  this happens during the uninstall hook, not deactivation.
		// thus, a person can deactivate and re-activate the plugin without
		// removing their data.  only if they click the "delete" link on the
		// Plugins page of the Dashboard will this operation be run.

		$prices = $this->tmfChallenge->getPriceTable();
		$tickers = $this->tmfChallenge->getTickerTable();

		// the foreign key constraint between $tickers and $prices will
		// cascade this DELETE statement between both tables.  thus, we
		// only need to DELETE once.  that same constraint requires that
		// we drop the prices table first, though.

		$wpdb->query("DELETE FROM $tickers WHERE 1=1");
		$wpdb->query("DROP TABLE $prices");
		$wpdb->query("DROP TABLE $tickers");
	}
}
