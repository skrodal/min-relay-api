<?php
	/**
	 *
	 * @author Simon Skrødal
	 * @since  November 2016
	 */

	use Relay\Conf\Config;

	// Error logging
	$debug = !true;
	// All paths/filenames are defined here.
	$apiConfig = Config::getConfigFromFile('etc/.api_config.js');

	// Shouldn't need to change anything below
	Config::add(
		[
			'altoRouter' => [
				// Remember to update .htacces as well:
				'api_base_path' => $apiConfig['apiBasePath']
			],
			'auth'       => [
				'dataporten'   => $apiConfig['configRootPath'] . $apiConfig['dataportenConfigFile'],
				'relay_sql'    => $apiConfig['configRootPath'] . $apiConfig['relayConfigFile'],
				'relay_hits'   => $apiConfig['configRootPath'] . $apiConfig['relayHitsConfigFile'],
				'relay_delete' => $apiConfig['configRootPath'] . $apiConfig['relayDeleteConfigFile']
			],
			'utils'      => [
				'debug' => $debug
			],
		]);

	// Stupid TechSmith has changed how it indicates paths/formats in filename over time
	// This object must be updated, should TechSmith decide to make changes again...
	Config::add(
		[
			'profile_codes' => [
				// Format name baked into filename
				'2013_1' => [
					'PC'        => '-_Flash_(Original_Size)_-',
					'NETTBRETT' => '_-_iPad_-_',
					'MOBIL'     => '_-_iPod_and_iPhone_-_'
				],
				'2013_2' => [
					'PC'        => '_-_PC_(Flash)_-_',
					'NETTBRETT' => '_-_Nettbrett_-_',
					'MOBIL'     => '_-_Mobil_-_'
				],
				// Only PC produced a html player
				'2014'   => [
					'PC'        => '_8.html',
					'NETTBRETT' => '_10.mp4',
					'MOBIL'     => '_11.mp4'
				],
				// All formats have html player
				'2015'   => [
					'PC'        => '_39.html',
					'NETTBRETT' => '_36.html',
					'MOBIL'     => '_38.html'
				]
			]
		]
	);

