<?php

/**
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Stefan Klemm <mail@stefan-klemm.de>
 * @copyright Stefan Klemm 2014
 */

namespace OCA\Medionlibrarys\Controller;

use OCP\AppFramework\Http\ContentSecurityPolicy;
use \OCP\IRequest;
use \OCP\AppFramework\Http\TemplateResponse;
use \OCP\AppFramework\Controller;
use \OCA\Medionlibrarys\Controller\Lib\Medionlibrarys;
use OCP\IURLGenerator;

class WebViewController extends Controller {

	/** @var  string */
	private $userId;

	/** @var IURLGenerator  */
	private $urlgenerator;

	/** @var Medionlibrarys */
	private $medionlibrarys;

	/**
	 * WebViewController constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param $userId
	 * @param IURLGenerator $urlgenerator
	 * @param Medionlibrarys $medionlibrarys
	 */
	public function __construct($appName, IRequest $request, $userId, IURLGenerator $urlgenerator, Medionlibrarys $medionlibrarys) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->urlgenerator = $urlgenerator;
		$this->medionlibrarys = $medionlibrarys;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index() {
		$medionlibraryleturl = $this->urlgenerator->getAbsoluteURL('index.php/apps/medionlibrarys/medionlibrarylet');
		$params = array('user' => $this->userId, 'medionlibraryleturl' => $medionlibraryleturl);

		$policy = new ContentSecurityPolicy();
		$policy->addAllowedFrameDomain("'self'");

		$response = new TemplateResponse('medionlibrarys', 'main', $params);
		$response->setContentSecurityPolicy($policy);
		return $response;
	}

	/**
	 * @param string $url
	 * @param string $title
	 * @return TemplateResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function medionlibrarylet($url = "", $title = "") {
		$medionlibraryExists = $this->medionlibrarys->medionlibraryExists($url, $this->userId);
		$description = "";
        $tags = [];
		if ($medionlibraryExists !== false){
			$medionlibrary = $this->medionlibrarys->findUniqueMedionlibrary($medionlibraryExists, $this->userId);
			$description = $medionlibrary['description'];
            $tags = $medionlibrary['tags'];
		}
		$params = array(
            'url'           => $url,
            'title'         => $title,
            'description'   => $description,
            'medionlibraryExists'=> $medionlibraryExists,
            'tags'          => $tags
        );
		return new TemplateResponse('medionlibrarys', 'addMedionlibrarylet', $params);  // templates/main.php
	}

}
