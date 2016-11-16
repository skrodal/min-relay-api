<?php
	/**
	 *
	 * @author Simon Skrødal
	 * @since  November 2016
	 */
	namespace Relay\Api;

	use Relay\Auth\Dataporten;
	use Relay\Conf\Config;
	use Relay\Database\RelayMySQLConnection;

	class Hits {
		private $relayMySQLConnection = false;
		private $config, $tableHits, $tableInfo, $dataporten, $firstRecordTimestamp;
		// private $tableDaily;
		// $this->tableDaily           = $this->config['db_table_daily'];

		public function __construct(Dataporten $dataporten) {
			$this->dataporten           = $dataporten;
			$this->config               = Config::getConfigFromFile(Config::get('auth')['relay_hits']);
			$this->relayMySQLConnection = new RelayMySQLConnection($this->config);
			$this->tableHits            = $this->config['db_table_hits'];
			$this->tableInfo            = $this->config['db_table_info'];
			$this->firstRecordTimestamp = $this->getFirstRecordedTimestamp();
		}

		/**
		 * @return null
		 */
		public function getFirstRecordedTimestamp() {
			$result = $this->relayMySQLConnection->query("SELECT conf_val AS 'timestamp' from $this->tableInfo WHERE conf_key = 'first_record_timestamp'");

			return $result[0]['timestamp'] ? $result[0]['timestamp'] : NULL;
		}


		/**
		 * Get an keyed array with one obj per presentation path (hits, timestamp, username)
		 *
		 * @param $path
		 *
		 * @return array
		 * @internal param null $userName
		 *
		 */
		public function hits($path) {
			$userName = $this->dataporten->userName();
			$path =  $this->relayMySQLConnection->escapeString($path);
			// Hits table does not ever use the ampersand in username (as username was generated from the presentation path)
			// $username = str_replace("@", "", $userName);
			//
			$response   = $this->relayMySQLConnection->query("
						SELECT * 
						FROM $this->tableHits 
						WHERE path LIKE '%$path%'
					");
			//AND username LIKE '$username'

			return !empty($response) ? $response[0] : [];
		}


	}