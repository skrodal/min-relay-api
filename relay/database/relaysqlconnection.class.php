<?php
	/**
	 * Connect to and query Relay's DB
	 * @author Simon Skrødal
	 * @since  November 2016
	 */
	namespace Relay\Database;

	use PDO;
	use PDOException;
	use Relay\Utils\Response;

	ini_set('mssql.charset', 'UTF-8');

	class RelaySQLConnection {

		private $connection = NULL;
		private $config;

		function __construct($config) {
			$this->config = $config;
		}
		###
		# Alternative DB implementation using PDO.
		# mssql is DEPRECATED PHP7, using PDO gives forward compatability.
		###

		/**
		 * If key is set, return associative array with $key as key.
		 *
		 * @param      $sql
		 *
		 * @param null $key
		 *
		 * @return array
		 */
		public function query($sql, $key = NULL) {
			$this->connection = $this->getConnection();

			try {
				$response = array();
				$query    = $this->connection->query($sql, PDO::FETCH_ASSOC);
				foreach($query as $row) {
					if(!is_null($key)) {
						// Eg. if $key is 'presId' == $row['presId']
						$response[$row[$key]] = $row;
					} else {
						$response[] = $row;
					}

				}
				$query->closeCursor();

				return $response;
			} catch(PDOException $e) {
				Response::error(500, 'Samtale med database feilet (SQL): ' . $e->getMessage());
			}
		}

		/**
		 * @return PDO
		 */
		private function getConnection() {
			if(!is_null($this->connection)) {
				return $this->connection;
			}
			// Read only access
			$host = $this->config['host'];
			$db   = $this->config['db'];
			$user = $this->config['user'];
			$pass = $this->config['pass'];
			try {
				$connection = new PDO("dblib:host=$host;dbname=$db;charset=UTF8", $user, $pass);
				$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

				return $connection;
			} catch(PDOException $e) {
				Response::error(503, 'Utilgjengelig - databasekobling feilet: ' . $e->getMessage());
			}
		}
	}