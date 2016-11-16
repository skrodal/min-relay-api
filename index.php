<?php
	/**
	 *
	 * @author Simon Skrødal
	 * @since  November 2016
	 */

	namespace Relay;
	date_default_timezone_set('CET');

	###	   LOAD DEPENDENCIES	###
	require_once('relay/autoload.php');
	//
	use Relay\Router\Router;

	// Init
	$router = new Router();