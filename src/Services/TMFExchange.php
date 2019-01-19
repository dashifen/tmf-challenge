<?php

namespace Dashifen\TMFChallenge\Services;

use Dashifen\TMFChallenge\TMFChallenge;
use Fadion\Fixerio\Exceptions\ConnectionException;
use Fadion\Fixerio\Exceptions\ResponseException;
use Fadion\Fixerio\Exchange;
use Fadion\Fixerio\Currency;

/**
 * Class TMFExchange
 *
 * A very basic implementation of the Fadion\Fixerio\Exchange object
 * for use within this challenge.  It's not very flexible, since I don't
 * think maximum flexibility is necessary here, but it'll get the job
 * done.
 *
 * @package Dashifen\TMFChallenge\Services
 */
class TMFExchange {

	// normally, I'd put this into a settings page or something like that.
	// but, to keep things simple, I'm going to set it as a private constant
	// so it's only visible to this class.  plus, it's only a legacy account,
	// so even it's visibility in the github repo isn't too worrisome for me
	// at the moment.

	private const FIXER_API_KEY = "4abaaf1d8837591730fe5ddf1074ad02";

	/**
	 * @var Exchange
	 */
	protected $exchange;

	/**
	 * TMFExchange constructor.
	 */
	public function __construct() {
		$this->exchange = new Exchange();
		$this->exchange->key(self::FIXER_API_KEY);
		$this->exchange->symbols(Currency::SGD);
		$this->exchange->base(Currency::USD);
	}

	public function getRate(string $date) {

		// I'm testing for get_transient() because I tested this object
		// outside of the WordPress ecosystem where it wouldn't exist.
		// this simply prevents an error during that testing.

		$rate = function_exists("get_transient")
			? get_transient($date . "SGD")
			: false;

		if ($rate === false) {
			$this->exchange->historical($date);

			try {
				$rates = $this->exchange->getResult();
				$rate = $rates->getRate(Currency::SGD);

				// this is simply to provide an example of how we could avoid
				// hitting the API every time that we request a rate.  in this
				// case it's definitely overkill since the dates we're working
				// with in this challenge are in the past and, therefore,
				// unlikely to change.  but, if this were a live app looking at
				// live results, grabbing the data for a date and currency and
				// storing it in a WordPress transient would help avoid wasting
				// API resources if we don't have to.  fixer.io tells me that
				// it updates historical data once per day, so that's what I
				// used for our transient.

				if (function_exists("set_transient")) {
					set_transient($date . "SGD", $rate, 60*60*24);
				}
			} catch (ResponseException | ConnectionException $e) {

				// for our purposes in this challenge, there's not much to do
				// if we can't hit the API.  in a production system, checking
				// the status of the API would be my first try and solving
				// the problem.  after that, ensuring my key is correct.
				// next, it might be to try X times after which we give up.
				// falling back on prior data in a cache or stored in the
				// database might, at that point, be the only solution.  for
				// now, I'll just catch the exception and write it to the
				// screen or log so that we can try to continue.

				TMFChallenge::catcher($e);
			}
		}

		return $rate;
	}
}
