<?php
	/**
	 *
	 * @author Simon Skrødal
	 * @since  November 2016
	 */
	namespace Relay\Utils;
	use Relay\Conf\Config;
	use Mail;

	class Utils {

		public static function log($text) {
			if(Config::get('utils')['debug']) {
				$trace  = debug_backtrace();
				$caller = $trace[1];
				error_log($caller['class'] . $caller['type'] . $caller['function'] . '::' . $caller['line'] . ': ' . $text);
			}
		}

		public static function getPresentationRequestBody(){
			$requestBody = json_decode(file_get_contents('php://input'), true);
			// No presentation content in the request body
			if(!$requestBody['presentation'] || empty($requestBody['presentation'])) {
				Response::error(400, "Forespørsel om sletting kunne ikke gjennomføres - tjenesten sendte ikke med nok informasjon om hva som skal slettes...");
			}
			return $requestBody;
		}

		/**
		 * Returns the response code for a screencast URL
		 *
		 * @param $url
		 * @return string
		 */
		public static function checkUrlExists($url){
			// Important to utf8-decode URL - this test would have failed on æøå otherwise!!
			$headers = get_headers(utf8_decode($url));
			return (int)substr($headers[0], 9, 3) === 200;
		}


	}