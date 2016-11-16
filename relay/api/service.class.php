<?php
	/**
	 *
	 * @author Simon Skrødal
	 * @since  November 2016
	 */
	namespace Relay\Api;


	class Service extends Relay {
		public function version() {
			$sqlResponse = $this->relaySQLConnection->query("SELECT versValue FROM tblVersion")[0];

			return $sqlResponse['versValue'];
		}

		/**
		 * Wired to /dev/ and USED FOR DEV ONLY
		 * @return array
		 */
		public function dev() {
			return $this->relaySQLConnection->query("
											SELECT something
											FROM tblSomewhere
											WHERE something = something
										");
		}


		##------------- UNUSED BELOW --------------- ##


		/**
		 * Long title, I know. This function was run once (NOV 11 2016) to catch all presentations in Relay DB that
		 * no longer exist on file server — in order to update Relay Delete service (own table in UNINETT SQL Cluster).
		 *
		 * The presentations_deletelist table is now up to date and this function need not be run again (unless the table should
		 * disappear).
		 *
		 * Other functions in this API reads from this table to get all deleted presentations for a given user (by userId). This response
		 * is used in conjunction with all user presentations from Relay DB, which has no idea which presentations are deleted.
		 *
		 * Leaving the code here for future reference only.
		 *
		 * @return array
		 */
		public function scanAllOrgsUsersPresentationsFilesystemCheckForDeleted() {
			// TODO FIRST: Get an updated list from $this->orgList() and fill the array
			$orgs = array("uninett.no", "someother.no", "andanother.no", "andsoon.no");
			// Recommend to do each org manually (one call per [index]) - it's timeconsuming, and useful to monitor a little
			$org = $this->orgs[0];
			// Class that provides the delete functionality
			$sqlDelete = new Delete($this->dataporten);
			// Some info about which presentations were not found
			$response = [];
			#1 Get all users
			$usersArr = $this->relaySQLConnection->query("
				SELECT userId, userName
				FROM tblUser
				WHERE userName LIKE '%$org'
			");

			#2 Loop all users and get their presentations
			foreach($usersArr as $uIdx => $userObj) {
				// User ID
				$userId = $userObj['userId'];
				//
				$userPresentations = $this->relaySQLConnection->query("
					SELECT presId
					FROM tblPresentation
					WHERE presCompleted = 1
					AND presDeleted = 0
					AND  presUser_userId = $userId
				");

				#3 Loop all user presentations and get mp3 url for each and every one of them
				// a) if no mp3 is found in root path, it is pretty safe to assume that this presentation was never published
				// b) if an mp3 is found, then we can check to see if it exists on the file server. If not, pretty safe to assume that the presentation is deleted.
				foreach($userPresentations as $pIdx => $presentationObj) {
					$presId            = $presentationObj['presId'];
					$presentationFiles = $this->relaySQLConnection->query("
						SELECT TOP(1) filePath, fileName
						FROM tblFile
						WHERE filePresentation_presId = $presId
						AND fileName LIKE '%.mp3'
						AND filePath LIKE '%screencast.uninett.no%'
					");
					//

					if(!empty($presentationFiles)) {
						// Server root path
						$rootPath = '/path/to/mounted/relaymedia/';
						// https://screencast.uninett.no/relay/ansatt/simon@uninett.no/2013/22.05/169333/Test_av_opptak_-_Nettbrett_-_20130522_11.57.00AM.mp4;
						// Remove URL root path
						$relativePath = str_replace("https://screencast.uninett.no/relay/", "", $presentationFiles[0]['filePath']);
						// Prespath
						$presPath   = $rootPath . $relativePath;
						$path_parts = pathinfo($presPath);
						$presDir    = $path_parts['dirname'] . DIRECTORY_SEPARATOR;
						// If pres is deleted
						if(!is_dir($presDir)) {
							// Get the relative publish path for presentation (e.g. ansatt/simon@uninett.no/2013/22.05/169333/)
							$presDirToDelete = str_replace($rootPath, "", $presDir);
							// Now make the DB call to add this record to the deletetable, marked as moved and deleted
							$sqlDelete->deletePermanently($userObj['userName'], $userId, $presDirToDelete, $presentationObj['presId']);
							// Some info to return
							$response[$userObj['userName']][] = [
								'presentationId' => $presentationObj['presId'],
								'url'            => $presentationFiles[0]['filePath'],
								'path'           => $presPath,
								'dir'            => $presDir,
								'dir_exists'     => is_dir($presDir),
								'file_exists'    => is_file($presPath)
							];
						}
					} else {
						// For some reason, no MP3 file was found in the query to Relay's DB.
						// Likely reason here is that the conversion/publication of the MP3 format failed.
						// If, at the same time, this presentation was deleted by user back when Min Relay had self-service delete (< SEP 2015)
						// there is a chance that the presentation will appear as undeleted in new Min Relay. If any, we're talking max a handful
						// of really old presentations - not worried.
						$response[$userObj['userName']]['empty'][] = $presId;
					}
				}
			}

			return $response;
		}


	}