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
	use Relay\Utils\Response;
	use Relay\Utils\Utils;

	class Delete {
		private $relayMySQLConnection = false;
		private $config, $tableDeleteList, $dataporten;

		public function __construct(Dataporten $dataporten) {
			$this->dataporten           = $dataporten;
			$this->config               = Config::getConfigFromFile(Config::get('auth')['relay_delete']);
			$this->relayMySQLConnection = new RelayMySQLConnection($this->config);
			$this->tableDeleteList      = $this->config['db_table_name'];
		}

		/**
		 * Get all user presentations that exist in delete table (as deleted/moved/notmoved).
		 *
		 * @param $userId
		 *
		 * @return array
		 */
		public function userPresentationsInDeleteList($userId) {
			// Return as associative with presId as key
			return $this->relayMySQLConnection->query("
							SELECT *
							FROM $this->tableDeleteList 
							WHERE userId = '$userId'
						", "presId");
		}


		###### POST ROUTES ######

		/**
		 * Add a single presentation to the deletelist.
		 *
		 * @param $presId
		 *
		 * @return string
		 */
		public function delete($presId) {
			// Will exit on errors
			$requestBody = Utils::getPresentationRequestBody();
			// Check request body for required fields
			$presUserName = isset($requestBody['presentation']['presUserName']) ? $this->relayMySQLConnection->escapeString($requestBody['presentation']['presUserName']) : Response::error(400, 'Bad request: Missing required data in request body.');
			$presUserId   = isset($requestBody['presentation']['presUserId']) ? $this->relayMySQLConnection->escapeString($requestBody['presentation']['presUserId']) : Response::error(400, 'Bad request: Missing required data in request body.');
			$presPath     = isset($requestBody['presentation']['presPath']) ? $this->relayMySQLConnection->escapeString($requestBody['presentation']['presPath']) : Response::error(400, 'Bad request: Missing required data in request body.');
			// If path does not exist on file server
			if(!is_dir($this->config['relaymedia_basepath'] . $presPath)) {
				Response::error(409, 'Conflict: Sletting mislyktes - filene finnes ikke på server.');
			}
			// Double check that the username in request equals Dataporten user
			$userName = $this->dataporten->userName();
			if(strcasecmp($presUserName, $userName) !== 0) {
				Response::error(400, "Bad request: Sletting mislyktes - $userName har ikke rettigheter til å slette presentasjoner som tilhører $presUserName.");
			}
			// If the presentation is already in the table, exit
			$selectResponse = $this->relayMySQLConnection->query("
							SELECT presId 
							FROM $this->tableDeleteList 
							WHERE path = '$presPath' 
							OR presId = '$presId'
						");
			if(!empty($selectResponse)) {
				Response::error(409, 'Conflict: Presentasjonen er allerede markert for sletting.');
			}

			// Do the insert (will handle error)
			$insertResponse = $this->relayMySQLConnection->exec("
							INSERT INTO $this->tableDeleteList (path, username, presId, userId) 
							VALUES ('$presPath', '$presUserName', $presId, $presUserId)
						");

			return 'Request to delete presentation OK.';
		}


		/**
		 * Request a moved presentation to be moved back.
		 * @return string
		 */
		public function restore($presId) {
			// See if entry is in table and that it is already marked as moved (and not deleted)
			$selectResponse = $this->relayMySQLConnection->query("
							SELECT presId
							FROM $this->tableDeleteList 
							WHERE presId = $presId 
							AND moved = 1 AND deleted <> 1 AND undelete = 0
						");
			if(empty($selectResponse)) {
				Response::error(409, 'Conflict: Angre på sletting mislyktes - presentasjonen finnes ikke i sletteliste, eller den har allerede blitt slettet.');
			}

			// Presentation was found, let's mark it for undeletion
			$updateResponse = $this->relayMySQLConnection->exec("
							UPDATE $this->tableDeleteList
							SET undelete=1 
							WHERE presId = $presId 
						");

			return 'Request to restore presentation OK.';
		}


		/**
		 * May be used to mark a presentation as permanently deleted (e.g. if content is already gone from disk, but not in deletetable).
		 *
		 * Was used for dev and is no longer wired to a route.
		 *
		 * @param $presId
		 *
		 * @return string
		 */
		public function deleteInstantly($presId) {
			// Will exit on errors
			$requestBody = Utils::getPresentationRequestBody();
			// Check request body for required fields
			$presUserName = isset($requestBody['presentation']['presUserName']) ? $this->relayMySQLConnection->escapeString($requestBody['presentation']['presUserName']) : Response::error(400, 'Bad request: Missing required data in request body.');
			$presUserId   = isset($requestBody['presentation']['presUserId']) ? $this->relayMySQLConnection->escapeString($requestBody['presentation']['presUserId']) : Response::error(400, 'Bad request: Missing required data in request body.');
			$presPath     = isset($requestBody['presentation']['presPath']) ? $this->relayMySQLConnection->escapeString($requestBody['presentation']['presPath']) : Response::error(400, 'Bad request: Missing required data in request body.');
			// If path does exist on file server
			if(is_dir($this->config['relaymedia_basepath'] . $presPath)) {
				Response::error(409, 'Conflict: Instant sletting mislyktes - filene eksisterer på server.');
			}
			// Double check that the username in request equals Dataporten user
			$userName = $this->dataporten->userName();
			if(strcasecmp($presUserName, $userName) !== 0) {
				Response::error(400, "Bad request: Sletting mislyktes - $userName har ikke rettigheter til å slette presentasjoner som tilhører $presUserName.");
			}
			// If the presentation is already in the table, exit
			$selectResponse = $this->relayMySQLConnection->query("
							SELECT presId 
							FROM $this->tableDeleteList 
							WHERE path = '$presPath' 
							OR presId = '$presId'
						");
			if(!empty($selectResponse)) {
				Response::error(409, 'Conflict: Presentasjonen er allerede registrert som slettet.');
			}

			// Do the insert (will handle error)
			$insertDeleteRecord = $this->relayMySQLConnection->exec("
							INSERT INTO $this->tableDeleteList (path, username, deleted, moved, presId, userId) 
							VALUES ('$presPath', '$presUserName', 1, 1, $presId, $presUserId)
						");

			return 'Request to delete presentation OK.';
		}


		/**
		 * FOR DEV - NOT WIRED TO ROUTE - see Service.scanAllOrgsUsersPresentationsFilesystemCheckForDeleted() for details
		 *
		 * @return string
		 */
		public function deletePermanently($userName, $userId, $presPath, $presId) {
			// If the presentation is already in the table, exit
			$selectResponse = $this->relayMySQLConnection->query("
							SELECT * 
							FROM $this->tableDeleteList 
							WHERE presId = '$presId'
						");
			if(!empty($selectResponse)) {
				return false;
			}

			// Do the insert
			$insertResponse = $this->relayMySQLConnection->exec("
							INSERT INTO $this->tableDeleteList (path, username, deleted, moved, presId, userId) 
							VALUES ('$presPath', '$userName', 1, 1, $presId, $userId)
						");

			return true;
		}

	}