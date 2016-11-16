<?php
	/**
	 *
	 *
	 *
	 * @author Simon Skrødal
	 * @since  November 2016
	 */
	namespace Relay\Api;

	use Relay\Conf\Config;
	use Relay\Utils\Response;

	class User extends Relay {
		/**
		 * Must have relay.userId here, since presentation records do not include relay.userName.
		 * This is actually a good thing, since usernames may change anyways (e.g. "Fusjonering").
		 *
		 * We also check the delete-service here (which is rewritten to use relay.userId) to:
		 *
		 * 1. Remove permanently deleted presentations from response
		 * 2. Flag presentations in the deletelist as moved (can be restoren) or notmoved (can be cancelled)
		 *
		 * @return array|null
		 */
		public function presentations() {
			$test = false;
			$userId = $this->accountId();
			$userEmail = $this->dataporten->userEmail();
			if($test){
				$userId = 124;
				$userEmail = 'odder@uio.no';
			}
			// Ask for associative array with presId as key
			$sqlResponse = $this->relaySQLConnection->query("
				SELECT presId, presProfile_profId as profile_id, presPresenterName as presenter_name, presPresenterEmail as presenter_email, presTitle as title, presDescription as description, presDuration as duration_ms, presMaxResolution as max_resolution, presPlatform as platform, presClient_clntId as clientId, DATEDIFF(s, '1970-01-01 00:00:00', presRecordTime) as timestamp
				FROM tblPresentation
				WHERE (presUser_userId = $userId OR presPresenterEmail = '$userEmail')
				AND tblPresentation.presCompleted = 1 
				AND tblPresentation.presDeleted = 0
			", 'presId');

			// If user has presentations
			if(!empty($sqlResponse)) {
				// ...check if any of these are deleted
				$sqlDelete  = new Delete($this->dataporten);
				$deletedIDs = $sqlDelete->userPresentationsInDeleteList($userId);
				// Yes, at least one presentation is marked as permanently deleted
				if(!empty($deletedIDs)) {
					foreach($sqlResponse as $id => $presObj) {
						// If presentation ID id found in deletelist
						if(isset($deletedIDs[$id])){
							// If presentation with $id is marked as permanently deleted, get rid of it - do not include in response
							if($deletedIDs[$id]['deleted'] == 1){
								unset($sqlResponse[$id]);
							} else {
								// Add delete status object from table (i.e. presId, userId, moved, undelete, timestamp, path, etc....)
								$sqlResponse[$id]['delete_status'] = $deletedIDs[$id];
							}
						} else {
							$sqlResponse[$id]['delete_status'] = false;
						}
					}
				}
			}

			// Returned as indexed
			return array_values($sqlResponse);
		}

		public function accountId($userName = NULL) {
			$userName    = is_null($userName) ? $this->dataporten->userName() : $userName;
			$sqlResponse = $this->relaySQLConnection->query("
				SELECT userId 
				FROM tblUser 
				WHERE userName LIKE '" . $userName . "'"
			);

			if(empty($sqlResponse)) {
				Response::error(404, 'Fant ingen konto for bruker');
			}

			return (int)$sqlResponse[0]['userId'];
		}

		public function clients() {
			// Get user ID
			$userId = $this->accountId();
			// Get list of all unique clientIDs that this user has used to create presentations
			$sqlResponse = $this->relaySQLConnection->query("
				SELECT DISTINCT presClient_clntId as clientId
				FROM tblPresentation
				WHERE presUser_userId = $userId
			");

			$clients = [];
			// Loop all returned clientIds to get more info on each client (computer, last used, version)
			if(!empty($sqlResponse)) {
				foreach($sqlResponse as $index => $client) {
					if(!is_null($client['clientId'])) {
						$clientResponse = $this->clientInfo($client['clientId']);
						if(!empty($clientResponse)) {
							$clients[] = $clientResponse;
						}
					}
				}
			}

			return $clients;
		}

		/**
		 * Helper, not wired to any routes. See function clients()
		 *
		 * @param $clientId
		 *
		 * @return array
		 */
		private function clientInfo($clientId) {
			$sqlResponse = $this->relaySQLConnection->query("
				SELECT clntComputerName as computer, clntVersion as version, DATEDIFF(s, '1970-01-01 00:00:00', clntLastAccess) as timestamp
				FROM tblClient 
				WHERE clntId = $clientId
			");

			return !empty($sqlResponse) ? $sqlResponse[0] : [];
		}

		/**
		 * Takes a presId and checks with tblFile for any relevant links on screencast.uninett.no.
		 *
		 * Service has evovled through versions (and profiles), so we won't get much extra info (e.g. format type),
		 * only html-player links (if available), and direct links to mp3/mp4 (which, depending on Service version
		 * may be located in presentation root or /media/video/mp4).
		 *
		 * If future versions of Service should (again) mess with publication points, this function would be a
		 * good place to start to fix response.
		 *
		 * @param $presId
		 *
		 * @return array
		 */
		public function presentationUrlsAndHits($presId) {
			// This query should give us (screencast) URLs to mp3, xml and html files only.
			// Also double check that the record is not marked as deleted (in tblPresentations)

			// It is not feasible to grab tblFile.fileResolution and tblFile.fileSize, since tblFile does not
			// store export paths for mp4 files (only corresponding htmls), which means we get null resolution and
			// filesize == html filesize... :(
			$sqlResponse = $this->relaySQLConnection->query("
				SELECT tblFile.filePath, tblFile.fileName
				FROM tblFile
				INNER JOIN tblPresentation
				ON tblFile.filePresentation_presId = tblPresentation.presId
				WHERE tblFile.filePresentation_presId = $presId
				AND tblPresentation.presDeleted = 0
				AND tblFile.fileName NOT LIKE '%index.html'
				AND tblFile.fileName NOT LIKE '%.xml'
				AND tblFile.filePath LIKE '%screencast.uninett.no%'"
			);

			// Post-process our response
			if(empty($sqlResponse)) {
				Response::error(404, 'Fant ingen filer for denne presentasjonen. Dette kan skyldes at du tidligere har slettet denne presentasjonen, eller at konvertering/publisering av innhold feilet (kræsjet).');
			} else {
				/* Note: Checks if URL exists. Disabled since $this->presentations() checks existence
					if(!Utils::checkUrlExists($sqlResponse[0]['filePath'])) { return []; }
				*/
				/*
				 * SEE Config.php for details on formats.
				 *
				Sample of a NEW (2015-2016) publication path (html player in root, mp4 in filename/media/video.mp4). Numbers _36, _38 or _39 postfixes in MP4 filenames
				Every (mp4) format has a corresponding html player
					"filePath": "https://screencast.uninett.no/relay/ansatt/simonuninett.no/2016/31.10/223500/ConnectAdmin_-_Kort_intro_-_20161031_072531_39.html",
					"fileName": "ConnectAdmin_-_Kort_intro_-_20161031_072531_39.html",
					"fileSize": "979"

				Sample of an OLD (2014) publication path
				HTML5-players for all, all MP4s in pres path with numbers _8, _10 or _11 postfixes in MP4 filenames
					"filePath": "https://screencast.uninett.no/relay/ansatt/simon@uninett.no/2013/07.12/135393/HK_Julebord_-_20131207_030839_11.mp4",
					"fileName": "HK_Julebord_-_20131207_030839_11.mp4",
					"fileSize": "53655429"

				Sample of an OLDEST (2013 to late 2014) publication path (mp4 is located in presentation root).
				The "Mobil, Nettbrett, PC (Flash)" profile only generated a html player for it's "Flash" version. String in MP4 filenames to indicate format.
					"filePath": "https://screencast.uninett.no/relay/ansatt/simon@uninett.no/2013/15.08/5476733/Sivilprosess_-_Mobil_-_20130815_12.29.50PM.mp4",
					"fileName": "Sivilprosess_-_Mobil_-_20130815_12.29.50PM.mp4",
					"fileSize": "53655429"
				*/
				$response          = [];
				$response['files'] = [];
				$basePathForHits   = NULL;
				foreach($sqlResponse as $i => $fileInfo) {
					$response['files'][$i] = [];
					$path_parts            = pathinfo($fileInfo['filePath']);
					// We'll set this later..
					$encoding = NULL;
					if(is_null($basePathForHits)) {
						$basePathForHits = $path_parts['dirname'];
					}
					// Check each extension returned and tweak paths where need be
					switch($path_parts['extension']) {
						// Recent versions of relay points to HTML player(s) in presentation root, mp4 in filename_XX/media/video.mp4
						case 'html':
							$response['files'][$i]['url'] = $path_parts['dirname'] . DIRECTORY_SEPARATOR . $path_parts['filename'] . '/media/video.mp4';
							break;
						// Earlier versions of relay (ca. < 4.4.1) points to mp4 in presentation root
						case 'mp4':
							$response['files'][$i]['url'] = $fileInfo['filePath'];
							break;
						// MP3 is always in presentation root
						case 'mp3':
							$response['files'][$i]['url'] = $fileInfo['filePath'];
							$encoding                     = "Lyd";
							break;
						// Turned off in query above, for now... Trying to do this without using XMLs/filesystem
						/*
						case 'xml':
							$sqlResponse[$i]['type']      = 'meta';
							$sqlResponse[$i]['mediaPath'] = $fileInfo['filePath'];
							break;
						*/
					}

					// If file is of type MP4, find out which format it is (see Config for
					// details as to why we need to such a stupid thing
					if(is_null($encoding)) {
						foreach(Config::get('profile_codes') as $year => $codes) {
							if(strpos($fileInfo['fileName'], $codes['PC']) !== false) {
								$encoding = "PC";
								break;
							}
							if(strpos($fileInfo['fileName'], $codes['NETTBRETT']) !== false) {
								$encoding = "Nettbrett";
								break;
							}
							if(strpos($fileInfo['fileName'], $codes['MOBIL']) !== false) {
								$encoding = "Mobil";
								break;
							}
						}
					}
					$response['files'][$i]['encoding'] = $encoding;
				}

				// Sort files array alphabetically by encoding (PC, Nettbrett, Mobil, Lyd)
				foreach($response['files'] as $key => $row) {
					$encodingArr[$key] = $row['encoding'];
				}
				array_multisort($encodingArr, SORT_DESC, $response['files']);

				// Remove preceding 'https://screencast.unine......./'
				$basePathForHits = explode('/relay/', $basePathForHits);
				// Should now be left with 'ansatt|student/username/year/date/#####/'
				if(isset($basePathForHits[1])) {
					$sqlHits = new Hits($this->dataporten);
					// Get hitcount for this presentation path (all formats combined)
					$sqlHitsResponse  = $sqlHits->hits($basePathForHits[1]);
					$response['path'] = $basePathForHits[1] . DIRECTORY_SEPARATOR;
				}

				if(!empty($sqlHitsResponse)) {
					$response['hits'] = $sqlHitsResponse;
				}

				return $response;
			}
		}

		/**
		 * Returns false on no account
		 * /me/
		 */
		public function info() {
			$userName = $this->dataporten->userName();
			$sqlResponse = $this->relaySQLConnection->query("
				SELECT userId, userName, userDisplayName, userEmail, usprProfile_profId AS affiliation
				FROM tblUser, tblUserProfile
				WHERE tblUser.userId = tblUserProfile.usprUser_userId
				AND userName = '$userName'
				");

			// Convert affiliation code to text
			if(!empty($sqlResponse)) {
				$sqlResponse[0]['affiliation']   = $this->profileIdToString($sqlResponse[0]['affiliation']);
				$sqlResponse[0]['presentations'] = $this->presentationCount($sqlResponse[0]['userId']);

				return $sqlResponse[0];
			}

			// Most appropriate would be a 404 here, but the client now tests for a data/false to confirm account.
			return false;
		}


		#### ----------- HELPER (unwired) FUNCTIONS ----------- ####

		/**
		 * Helper
		 *
		 * @param $userId
		 *
		 * @return mixed
		 */
		private function presentationCount($userId) {
			$sqlResponseUserPresentations = $this->relaySQLConnection->query("
					SELECT COUNT(*) as total
					FROM tblPresentation 
					WHERE tblPresentation.presCompleted = 1
					AND tblPresentation.presDeleted = 0
					AND presUser_userId = $userId
			");

			return $sqlResponseUserPresentations[0]['total'];
		}

	}