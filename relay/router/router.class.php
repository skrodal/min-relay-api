<?php
	/**
	 * Required client scope:
	 *    - admin
	 *
	 * @author Simon Skrødal
	 * @since  November 2016
	 */
	namespace Relay\Router;

	use Relay\Api\Delete;
	use Relay\Api\Hits;
	use Relay\Api\Org;
	use Relay\Api\Service;
	use Relay\Api\User;
	use Relay\Auth\Dataporten;
	use Relay\Conf\Config;
	use Relay\Utils\Response;
	use Relay\Vendor\AltoRouter;


	class Router {

		private $altoRouter, $relayService, $relayUser, $relayOrg, $dataporten;

		function __construct() {
			### ALTO ROUTER
			$this->altoRouter = new AltoRouter();
			$this->altoRouter->setBasePath(Config::get('altoRouter')['api_base_path']);
			$this->altoRouter->addMatchTypes(array('user' => '[0-9A-Za-z.@]++', 'org' => '[0-9A-Za-z.]++'));
			### DATAPORTEN
			$this->dataporten = new Dataporten();
			// Declare all routes
			$this->serviceRoutes_GET();
			$this->meRoutes_GET();
			$this->meRoutes_POST();

			// Activate routes
			$this->matchRoutes();
		}

		/**
		 * Service routes
		 *
		 * @return string
		 */
		private function serviceRoutes_GET() {
			$this->altoRouter->addRoutes([
				// List all routes
				array('GET', '/', function () {
					Response::result($this->altoRouter->getRoutes());
				}, 'All available routes'),

				array('GET', '/dev/', function () {
					$this->relayService = new Service($this->dataporten);
					Response::result($this->relayService->dev());
				}, 'DEV route'),
				//
				array('GET', '/version/', function () {
					$this->relayService = new Service($this->dataporten);
					Response::result($this->relayService->version());
				}, 'TechSmith Service version'),

				array('GET', '/hits/firstrecord/', function () {
					$sqlHits = new Hits($this->dataporten);
					Response::result($sqlHits->getFirstRecordedTimestamp());
				}, 'Get first recorded timestamp for hits in the system (sep. 2015)'),

			]);
		}

		/**
		 * GET Routes for logged on user (on client)
		 */
		private function meRoutes_GET() {
			$this->altoRouter->addRoutes([
				//
				array('GET', '/me/', function () {
					$this->relayUser = new User($this->dataporten);
					Response::result($this->relayUser->info());
				}, 'User account details'),
				//
				array('GET', '/me/presentations/', function () {
					$this->relayUser = new User($this->dataporten);
					Response::result($this->relayUser->presentations());
				}, 'All presentations belonging to user (does not include links!)'),
				//
				array('GET', '/me/presentation/[i:presId]/', function ($presId) {
					$this->relayUser = new User($this->dataporten);
					Response::result($this->relayUser->presentationUrlsAndHits($presId));
				}, 'Details about a single presentation'),
				//
				array('GET', '/me/clients/', function () {
					$this->relayUser = new User($this->dataporten);
					Response::result($this->relayUser->clients());
				}, 'Get list of all clients ever used by user (id and timestamp)'),
			]);
		}


		private function matchRoutes() {
			$match = $this->altoRouter->match();

			if($match && is_callable($match['target'])) {
				call_user_func_array($match['target'], $match['params']);
			} else {
				Response::error(404, "Endepunktet det spørres etter finnes ikke.");
			}
		}

		/**
		 * POST Routes for logged on user (on client)
		 */
		private function meRoutes_POST() {
			$this->altoRouter->addRoutes([
				## Single presentation delete/restore/undelete request
				// Requires `presPath`, `presUsername` and `presUserId` in request body
				array('POST', '/me/presentation/delete/[i:presId]/', function ($presId) {
					$sqlDelete = new Delete($this->dataporten);
					Response::result($sqlDelete->delete($presId));
				}, 'Request for a presentation to be deleted. Expects: presentation.presPath presentation.presUserName and presentation.presUserId'),

				// Requires `path` in request body
				array('POST', '/me/presentation/restore/[i:presId]/', function ($presId) {
					$sqlDelete = new Delete($this->dataporten);
					Response::result($sqlDelete->restore($presId));
				}, 'Request for a presentation already moved to be restored/moved back.'),

				// Requires `path` in request body
				/* Was used in dev, not wired to client any more
				array('POST', '/me/presentation/delete/instantly/[i:presId]/', function ($presId) {
					$sqlDelete = new Delete($this->dataporten);
					Response::result($sqlDelete->deleteInstantly($presId));
				}, 'Request for a presentation to be marked as deleted instantly. Expects: presentation.presPath presentation.presUserName and presentation.presUserId'),
				*/
			]);
		}

	}
