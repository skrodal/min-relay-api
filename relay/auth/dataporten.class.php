<?php
	/**
	 * Single scope, `user`, required
	 *
	 *
	 * @author Simon Skrødal
	 * @since  November 2016
	 */

	namespace Relay\Auth;

	use Relay\Conf\Config;
	use Relay\Utils\Response;

	class Dataporten {

		private $config;

		function __construct() {
			// Exits on OPTION call
			$this->checkCORS();
			// Dataporten username and pass (will exit on fail)
			$this->config = Config::getConfigFromFile(Config::get('auth')['dataporten']);
			// Exits on incorrect credentials
			$this->checkGateKeeperCredentials();
			// Will exit if client does not have required scope
			if(!$this->hasDataportenScope('user')) {
				Response::error(403, "Client is missing required Dataporten scope to access this API");
			};
		}

		/**
		 * Access-Control headers are received during OPTIONS requests
		 */
		private function checkCORS() {
			if(strcasecmp($_SERVER['REQUEST_METHOD'], "OPTIONS") === 0) {
				Response::result('CORS OK :-)');
			}
		}

		/**
		 * Will exit with error if any issues with credentials
		 */
		private function checkGateKeeperCredentials() {
			if(empty($_SERVER["PHP_AUTH_USER"]) || empty($_SERVER["PHP_AUTH_PW"])) {
				Response::error(401, "Unauthorized (Missing Dataporten API Gatekeeper Credentials)");
			}
			// Gatekeeper. user/pwd is passed along by the Dataporten Gatekeeper and must matched that of the registered API:
			if((strcmp($_SERVER["PHP_AUTH_USER"], $this->config['user']) !== 0) ||
				(strcmp($_SERVER["PHP_AUTH_PW"], $this->config['passwd']) !== 0)
			) {
				// The status code will be set in the header
				Response::error(401, "Unauthorized (Incorrect Dataporten API Gatekeeper Credentials)");
			}
		}

		/**
		 * Check if client has access to $scope (set in Dataporten Dashboard)
		 *
		 * @param $scope
		 *
		 * @return bool
		 */
		private function hasDataportenScope($scope) {
			$scopes = $_SERVER["HTTP_X_DATAPORTEN_SCOPES"];
			$scopes = explode(',', $scopes);
			if(empty($scopes)) {
				Response::error(403, "Client is missing required Dataporten scope(s) to access this API");
			}

			return in_array($scope, $scopes);
		}

		/**
		 * Pull org ID from username
		 * @return mixed
		 */
		public function userOrgId() {
			$userOrg = explode('@', $this->userName());

			// e.g. 'uninett.no'
			return $userOrg[1];
		}

		/**
		 * Feide username from Dataporten headers
		 *
		 * @return null
		 */
		public function userName() {
			if(!isset($_SERVER["HTTP_X_DATAPORTEN_USERID_SEC"])) {
				Response::error(401, "Beklager - fant ikke ditt (Feide) brukernavn.");
			}
			$userIdSec = NULL;
			// Get the username(s)
			$userid = $_SERVER["HTTP_X_DATAPORTEN_USERID_SEC"];
			// Future proofing...
			if(!is_array($userid)) {
				// If not already an array, make it so. If it is not a comma separated list, we'll get a single array item.
				$userid = explode(',', $userid);
			}
			// Fish for a Feide username
			foreach($userid as $key => $value) {
				if(strpos($value, 'feide:') !== false) {
					$value     = explode(':', $value);
					$userIdSec = $value[1];
				}
			}
			// No Feide...
			if(is_null($userIdSec)) {
				Response::error(401, "Beklager - fant ikke ditt (Feide) brukernavn.");
			}

			// e.g. 'username@org.no'
			return $userIdSec;
		}

		/**
		 * Email from Dataporten /userinfo endpoint (thus API must have access to `email`)
		 * @return mixed
		 */
		public function userEmail() {
			return isset($this->getUserInfo()['email']) ? $this->getUserInfo()['email'] : $this->userName();
		}

		// Call /userinfo/ for name/email of user
		public function getUserInfo() {
			return $this->protectedRequest('https://auth.dataporten.no/userinfo')['user'];
		}


		/**
		 * @param $url
		 *
		 * @return bool|mixed
		 */
		private function protectedRequest($url) {
			$token = $_SERVER['HTTP_X_DATAPORTEN_TOKEN'];
			if(empty($token)) {
				Response::error(403, "Access denied: Dataporten token missing.");
			}

			$opts    = array(
				'http' => array(
					'method' => 'GET',
					'header' => "Authorization: Bearer " . $token,
				),
			);
			$context = stream_context_create($opts);
			$result  = file_get_contents($url, false, $context);

			return $result ? json_decode($result, true) : false;
		}

		public function userOrgName() {
			$userOrg = explode('@', $this->userName());
			$userOrg = explode('.', $userOrg[1]);

			// e.g. 'uninett'
			return $userOrg[0];
		}
	}