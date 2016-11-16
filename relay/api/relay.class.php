<?php
	/**
	 *
	 * @author Simon Skrødal
	 * @since  November 2016
	 */
	namespace relay\api;

	use Relay\Auth\Dataporten;
	use Relay\Conf\Config;
	use Relay\Database\RelaySQLConnection;

	class Relay {
		protected $relaySQLConnection, $dataporten, $config;

		function __construct(Dataporten $dataporten) {
			$this->config             = Config::getConfigFromFile(Config::get('auth')['relay_sql']);
			$this->relaySQLConnection = new RelaySQLConnection($this->config);
			$this->dataporten         = $dataporten;
		}

		public function profileIdToString($id) {
			switch((int)$id) {
				case $this->studentProfileId():
					return 'student';
					break;
				case $this->employeeProfileId():
					return 'ansatt';
					break;
				default:
					return 'other';
					break;
			}
		}

		public function studentProfileId() {
			return (int)$this->config['studentProfileId'];
		}

		public function employeeProfileId() {
			return (int)$this->config['employeeProfileId'];
		}
	}