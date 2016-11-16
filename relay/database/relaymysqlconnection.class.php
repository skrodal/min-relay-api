<?php
	/**
	 * Connect to and query 3rd party DBs (hits, deletelist)
	 *
	 * @author Simon Skrødal
	 * @since  November 2016
	 */
	namespace Relay\Database;

	use Relay\Utils\Response;
	use Relay\Utils\Utils;

	class RelayMySQLConnection {
		private $connection = NULL;
		private $config;

		function __construct($config) {
			$this->config     = $config;
			$this->connection = $this->getConnection();
		}

		private function getConnection() {
			$connection = new \mysqli($this->config['db_host'], $this->config['db_user'], $this->config['db_pass'], $this->config['db_name']);
			//
			if($connection->connect_errno) {
				Response::error(503, "Utilgjengelig - databasekobling feilet: " . $connection->connect_error);
			}

			Utils::log("MySQL DB CONNECTED: " . json_encode($connection->get_charset()));

			return $connection;
		}

		/**
		 * @param      $sql
		 *
		 * @param null $key
		 *
		 * @return array
		 */
		public function query($sql, $key = NULL) {
			// Run query
			$query = $this->connection->query($sql);
			// On error
			if($query === false) {
				Response::error(500, 'Samtale med database feilet (SQL): ' . mysqli_error());
			}
			// Response
			$response = array();
			// Loop rows and add to response array
			if($query->num_rows > 0) {
				Utils::log("Rows returned: " . $query->num_rows);
				while($row = $query->fetch_assoc()) {
					if(!is_null($key)) {
						// Eg. if $key is 'presId' == $row['presId']
						$response[$row[$key]] = $row;
					} else {
						$response[] = $row;
					}

				}
				// Free the query result
				$query->free_result();
			}

			return $response;
		}

		/**
		 * For bool queries
		 *
		 * @param $sql
		 *
		 * @return array
		 */
		public function exec($sql) {
			// Run query
			$query = $this->connection->query($sql);
			// On error
			if($query === false) {
				Response::error(500, 'Samtale med database feilet (SQL): ' . mysqli_error());
			}

			return true;
		}

		public function escapeString($string) {
			return $this->connection->real_escape_string($string);
		}


	}