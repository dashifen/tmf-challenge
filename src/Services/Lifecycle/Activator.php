<?php

namespace Dashifen\TMFChallenge\Services\Lifecycle;

use Dashifen\TMFChallenge\TMFChallenge;
use Dashifen\TMFChallenge\TMFChallengeException;
use Latitude\QueryBuilder\Engine\MySqlEngine;
use Latitude\QueryBuilder\QueryFactory;

;

class Activator {
	/**
	 * @var TMFChallenge
	 */
	protected $tmfChallenge;

	/**
	 * Activator constructor.
	 *
	 * @param TMFChallenge $tmfChallenge
	 */
	public function __construct(TMFChallenge $tmfChallenge) {
		$this->tmfChallenge = $tmfChallenge;
	}

	/**
	 * activate
	 *
	 * This method is called from the TMFChallenge plugin handler
	 * and performs the activation behaviors for it.
	 *
	 * @param string $dataFile
	 *
	 * @return void
	 * @throws TMFChallengeException
	 */
	public function activate(string $dataFile) {
		if (!$this->dataTablesExist()) {
			$this->createDataTables();

			// we could check for data even if the tables did exist,
			// but for the purposes of this challenge, we'll assume that
			// no one has reached into the database and deleted the data
			// without removing the tables.  in a production system, we'd
			// need to work to prevent that and fix it if it happens.

			$this->initializeDataTables($dataFile);
		}
	}

	/**
	 * dataTablesExist
	 *
	 * Returns true if our data tables exist; otherwise, false.
	 *
	 * @return bool
	 */
	protected function dataTablesExist(): bool {
		global $wpdb;

		// to confirm that our tables exist, we'll try to select one
		// of them.  since they're created together, if one exists,
		// the other must as well.

		$sql = "SHOW TABLES LIKE '%s'";
		$table = $this->tmfChallenge->getTickerTable();
		$statement = $wpdb->prepare($sql, $table);
		return $wpdb->get_var($statement) === $table;
	}

	/**
	 * createDataTables
	 *
	 * Creates the database tables when they're unavailable to us.
	 *
	 * @return void
	 */
	protected function createDataTables(): void {

		// there are two tables we create:  the table for our ticker
		// symbols and the one for the prices for them.  each of those
		// gets a method below.  we can require the WP database upgrade
		// script here, though, since we only need to do so once.

		require_once ABSPATH . "wp-admin/includes/upgrade.php";

		$this->createTickerTable();
		$this->createPriceTable();
	}

	/**
	 * createTickerTable
	 *
	 * Creates the table in which the tickers for our fool exchange
	 * are stored.
	 *
	 * @return void
	 */
	protected function createTickerTable(): void {
		global $wpdb;
		$table = $this->tmfChallenge->getTickerTable();

		$sql = <<< SQL
			CREATE TABLE $table (
				`ticker_id` BIGINT(20) NOT NULL AUTO_INCREMENT,
				`ticker` VARCHAR(10) NULL,
				UNIQUE INDEX `UNIQ_ticker` (`ticker` ASC),
				PRIMARY KEY (`ticker_id`)
			)
SQL;

		dbDelta($sql . $wpdb->get_charset_collate());
	}

	/**
	 * createPriceTable
	 *
	 * Creates the table in which prices for our ticker symbols
	 * are stored.
	 */
	protected function createPriceTable(): void {
		global $wpdb;
		$priceTable = $this->tmfChallenge->getPriceTable();
		$tickerTable = $this->tmfChallenge->getTickerTable();

		/** @noinspection SqlResolve */

		$sql = <<< SQL
			CREATE TABLE $priceTable (
				`price_id` BIGINT NOT NULL AUTO_INCREMENT,
				`ticker_id` BIGINT NULL,
				`date` CHAR(8) NULL,
				`time` CHAR(8) NULL,
				`price` FLOAT NULL,
				PRIMARY KEY (`price_id`),
				INDEX `IDX_ticker_id` (`ticker_id` ASC),
				CONSTRAINT `FK_ticker_id`
					FOREIGN KEY (`ticker_id`)
					REFERENCES $tickerTable (`ticker_id`)
					ON DELETE CASCADE
					ON UPDATE RESTRICT
			)
SQL;

		dbDelta($sql . $wpdb->get_charset_collate());
	}


	/**
	 * initializeDataTables
	 *
	 * Initializes our tables with the data provided as a part of
	 * challenge available in the assets/initial-data.csv file.
	 *
	 * @param string $dataFile
	 *
	 * @return void
	 * @throws TMFChallengeException
	 */
	protected function initializeDataTables(string $dataFile): void {

		// before we continue, there's three errors that'll sink us at this
		// time:  (1) a missing file, (2) a file we can't read, (3) a file
		// that's empty.  if we clear all those hurdles, we'll add our data
		// to the database.

		if (!is_file($dataFile)) {
			$message = sprintf("Unable to find %s.", basename($dataFile));
			throw new TMFChallengeException($message, TMFChallengeException::INVALID_DATA_FILE);
		}

		$fp = fopen($dataFile, "r");
		if ($fp === false) {
			$message = sprintf("Unable to open %s.", basename($dataFile));
			throw new TMFChallengeException($message, TMFChallengeException::INVALID_DATA_FILE);
		}

		$data = $this->readDataFile($fp);
		if (sizeof($data) === 0) {
			$message = sprintf("Data file, %s, was empty.", basename($dataFile));
			throw new TMFChallengeException($message, TMFChallengeException::INVALID_DATA_FILE);
		}

		$tickers = array_shift($data);
		$dbTickers = $this->insertTickers($tickers);
		$this->insertPrices($data, $dbTickers);
	}

	/**
	 * readDataFile
	 *
	 * Given a file pointer resource to our data file, reads it line by
	 * line and returns the data within it as an array.
	 *
	 * @param $fp
	 *
	 * @return array
	 */
	protected function readDataFile($fp): array {
		while($line = fgetcsv($fp)) {
			$data[] = $line;
		}

		// just in case the file is completely empty, we'll use the
		// null coalescing operator to be sure that we return an array.

		return $data ?? [];
	}

	/**
	 * insertTickers
	 *
	 * In a perfect world, we'd have a better way to read the file
	 * rather than relying on its structure never changing.  But,
	 * since that's not this world, this is a brute force method of
	 * reading the file and adding the information about our tickers
	 * to the file.
	 *
	 * @param array $tickers
	 *
	 * @return array
	 */
	protected function insertTickers(array $tickers): array {
		global $wpdb;
		$table = $this->tmfChallenge->getTickerTable();
		$dbTickers = [];

		// the zeroth and first columns in our tickers are the date and
		// time information.  thus, with the second column we start to
		// get the names of our ticker symbols.  they're in the format of
		// <TICKER> Price USD, but all we care about is <TICKER>.

		for ($i = 2; $i < sizeof($tickers); $i++) {

			// exploding our tickers on spaces gives us an array where
			// the symbol is first.  since it's the only thing we care
			// about at this time, we can shift if off that array and use
			// it below.

			$splitTicker = explode(" ", $tickers[$i]);
			$ticker = array_shift($splitTicker);

			// the insert() method array arguments are first, the parameters,
			// and, second, the parameter types.  thus, here we're telling
			// the WPDB that we're sending a value to be inserted into the
			// "ticker" column that is a string.

			$wpdb->insert($table, ["ticker" => $ticker], ["%s"]);
			$dbTickers[] = $wpdb->insert_id;
		}

		return $dbTickers;
	}

	/**
	 * insertPrices
	 *
	 * Given both the pricing information and the ticker ID numbers,
	 * we parse the former into the pricing table using the latter
	 * for our foreign keys.
	 *
	 * @param array $rows
	 * @param array $tickerIds
	 *
	 * @return void
	 */
	protected function insertPrices(array $rows, array $tickerIds): void {
		global $wpdb;

		// as we noted above, in a perfect world, we'd have a better way
		// to parse our file's data.  for now, though, we'll just handle
		// things in a way that'll work, even if it's fairly fragile.
		// we know the first two columns of a row are the date and time.
		// after that, it's all pricing data for our tickers.  since we
		// don't need to access the individual primary key ID numbers for
		// our pricing data, we can use the latitude query builder to
		// insert multiple values in one statement.

		$engine = new MySqlEngine();
		$factory = new QueryFactory($engine);
		$query = $factory
			->insert($this->tmfChallenge->getPriceTable())
			->columns("ticker_id", "date", "time", "price");

		foreach ($rows as $row) {
			$date = array_shift($row);
			$time = array_shift($row);

			// because we shifted those data off our row, what's left is
			// our pricing.  the ticker ID numbers are in the same order
			// as our prices, so $i in this loop will correctly link our
			// two tables together.

			foreach ($row as $i => $price) {

				// prices may include both dollar signs and commas.  we'll
				// want to remove those before we insert.  at this time,
				// numbers are formatted in the US style (commas for
				// separators and a dot for the decimal point) so we don't
				// worry about other international number formatting.

				$price = preg_replace("/[\$|,]+/", "", $price);
				$query->values($tickerIds[$i], $date, $time, $price);
			}
		}

		$query = $query->compile();
		$wpdbSql = $this->transformSql($query->sql());
		$statement = $wpdb->prepare($wpdbSql, $query->params());
		$wpdb->query($statement);
	}

	/**
	 * transformSql
	 *
	 * The latitude query factory produces SQL with ?'s as its placeholders.
	 * But, the WPDB object uses C-style placeholders like %s.  This method
	 * switches the former for the latter based on our needs here.
	 *
	 * @param string $sql
	 *
	 * @return string
	 */
	protected function transformSql(string $sql): string {
		$markCount = 0;
		$replacer = function () use (&$markCount) {

			// each query has 4 placeholders.  thus, we want to repeat
			// the sequence of placeholder replacements also in sets of
			// 4.  $markCount % 4 keeps track of that for us.  then, we
			// increment $markCount so that we "move" onto the next
			// question mark in sequence.

			$i = $markCount % 4;
			$markCount++;

			switch ($i) {
				case 0:
					return "%d";        // zeroth parameter is the ticker ID

				case 3:
					return "%f";        // third parameter is the price

				default:
					return "%s";        // other parameters are strings
			}
		};

		return preg_replace_callback("/\?/", $replacer, $sql);
	}
}
