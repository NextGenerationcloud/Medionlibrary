<?php

/**
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 * @author Marvin Thomas Rabe <mrabe@marvinrabe.de>
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Stefan Klemm <mail@stefan-klemm.de>
 * @copyright (c) 2011, Marvin Thomas Rabe
 * @copyright (c) 2011, Arthur Schiwon
 * @copyright (c) 2014, Stefan Klemm
 */

namespace OCA\Medionlibrarys\AppInfo;

use OCA\Medionlibrarys\Controller\Lib\Medionlibrarys;
use \OCP\AppFramework\App;
use \OCP\IContainer;
use \OCA\Medionlibrarys\Controller\WebViewController;
use OCA\Medionlibrarys\Controller\Rest\TagsController;
use OCA\Medionlibrarys\Controller\Rest\MedionlibraryController;
use OCA\Medionlibrarys\Controller\Rest\PublicController;
use OCP\IUser;

class Application extends App {

	public function __construct(array $urlParams = array()) {
		parent::__construct('medionlibrarys', $urlParams);

		$container = $this->getContainer();

		/**
		 * Controllers
		 * @param IContainer $c The Container instance that handles the request
		 */
		$container->registerService('WebViewController', function($c) {
			/** @var IUser|null $user */
			$user = $c->query('ServerContainer')->getUserSession()->getUser();
			$uid = is_null($user) ? null : $user->getUID();

			/** @var IContainer $c */
			return new WebViewController(
				$c->query('AppName'),
				$c->query('Request'),
				$uid,
				$c->query('ServerContainer')->getURLGenerator(),
				$c->query('ServerContainer')->query(Medionlibrarys::class)
			);
		});

		$container->registerService('MedionlibraryController', function($c) {
			/** @var IContainer $c */
			return new MedionlibraryController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('ServerContainer')->getUserSession()->getUser()->getUID(),
				$c->query('ServerContainer')->getDatabaseConnection(),
				$c->query('ServerContainer')->getL10NFactory()->get('medionlibrarys'),
				$c->query('ServerContainer')->query(Medionlibrarys::class)
			);
		});

		$container->registerService('TagsController', function($c) {
			/** @var IContainer $c */
			return new TagsController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('ServerContainer')->getUserSession()->getUser()->getUID(),
				$c->query('ServerContainer')->query(Medionlibrarys::class)
			);
		});

		$container->registerService('PublicController', function($c) {
			/** @var IContainer $c */
			return new PublicController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('ServerContainer')->getUserSession()->getUser()->getUID(),
				$c->query('ServerContainer')->query(Medionlibrarys::class),
				$c->query('ServerContainer')->getUserManager()
			);
		});

	}

}
