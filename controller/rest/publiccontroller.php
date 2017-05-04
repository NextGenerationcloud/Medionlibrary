<?php

namespace OCA\Medionlibrarys\Controller\Rest;

use \OCP\AppFramework\ApiController;
use \OCP\IRequest;
use \OCP\AppFramework\Http;
use \OCP\AppFramework\Http\JSONResponse;
use \OC\User\Manager;
use OCA\Medionlibrarys\Controller\Lib\Medionlibrarys;
use OCP\Util;

class PublicController extends ApiController {

	private $userManager;
	
	private $userId;

	/** @var Medionlibrarys */
	protected $medionlibrarys;

	public function __construct($appName, IRequest $request, $userId, Medionlibrarys $medionlibrarys, Manager $userManager) {
		parent::__construct($appName, $request);

		$this->medionlibrarys = $medionlibrarys;
		$this->userManager = $userManager;
		$this->userId = $userId;
	}
	
	/**
	 * @param string $user
	 * @param string $password
	 * @param array $item
	 * @param string $title
	 * @param bool $is_public
	 * @param string $description
	 * @return JSONResponse
	 *
	 * @CORS
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function newMedionlibrary($url = "", $item = array(), $title = "", $is_public = false, $description = "") {
		$title = trim($title);
		if ($title === '') {
			$title = $url;
			// allow only http(s) and (s)ftp
			$protocols = '/^(https?|s?ftp)\:\/\//i';
			try {
				if (preg_match($protocols, $url)) {
					$data = $this->medionlibrarys->getURLMetadata($url);
					$title = isset($data['title']) ? $data['title'] : $title;
				} else {
					// if no allowed protocol is given, evaluate https and https
					foreach(['https://', 'http://'] as $protocol) {
						$testUrl = $protocol . $url;
						$data = $this->medionlibrarys->getURLMetadata($testUrl);
						if(isset($data['title'])) {
							$title = $data['title'];
							$url   = $testUrl;
							break;
						}
					}
				}
			} catch (\Exception $e) {
				// only because the server cannot reach a certain URL it does not
				// mean the user's browser cannot.
				\OC::$server->getLogger()->logException($e, ['app' => 'medionlibrarys']);
			}
		}
		// Check if it is a valid URL (after adding http(s) prefix)
		$urlData = parse_url($url);
		if(!$this->isProperURL($urlData)) {
			return new JSONResponse(array('status' => 'error'), Http::STATUS_BAD_REQUEST);
		}
		$tags = isset($item['tags']) ? $item['tags'] : array();
		$id = $this->medionlibrarys->addMedionlibrary($this->userId, $url, $title, $tags, $description, $is_public);
		$bm = $this->medionlibrarys->findUniqueMedionlibrary($id, $this->userId);
		return new JSONResponse(array('item' => $bm, 'status' => 'success'));
	}
	
	/**
	 * @param int $id
	 * @param string $url
	 * @param array $item
	 * @param string $title
	 * @param bool $is_public Description
	 * @param null $record_id
	 * @param string $description
	 * @return JSONResponse
	 *
	 * @CORS
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function editMedionlibrary($record_id = null, $url = "", $item = array(), $title = "", $is_public = false, $description = "") {
		// Check if it is a valid URL
		$urlData = parse_url($url);
		if(!$this->isProperURL($urlData)) {
			return new JSONResponse(array(), Http::STATUS_BAD_REQUEST);
		}
		if ($record_id == null) {
			return new JSONResponse(array(), Http::STATUS_BAD_REQUEST);
		}
		$tags = isset($item['tags']) ? $item['tags'] : array();
		if (is_numeric($record_id)) {
			$id = $this->medionlibrarys->editMedionlibrary($this->userId, $record_id, $url, $title, $tags, $description, $is_public = false);
		}
		$bm = $this->medionlibrarys->findUniqueMedionlibrary($id, $this->userId);
		return new JSONResponse(array('item' => $bm, 'status' => 'success'));
	}
	
	/**
	 * @param int $id
	 * @return \OCP\AppFramework\Http\JSONResponse
	 *
	 * @CORS
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function deleteMedionlibrary($id = -1) {
		if ($id == -1) {
			return new JSONResponse(array(), Http::STATUS_BAD_REQUEST);
		}
		if (!$this->medionlibrarys->deleteUrl($this->userId, $id)) {
			return new JSONResponse(array(), Http::STATUS_BAD_REQUEST);
		} else {
			return new JSONResponse(array('status' => 'success'), Http::STATUS_OK);
		}
	}

	/**
	 * @param string $user
	 * @param string $password
	 * @param array $tags
	 * @param string $conjunction
	 * @param array $select
	 * @param string $sortby
	 * @return JSONResponse
	 *
	 * @CORS
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function returnAsJson($user, $password = null, $tags = array(), $conjunction = "or", $select = null, $sortby = "") {

		if ($user == null || $this->userManager->userExists($user) == false) {
			return $this->newJsonErrorMessage("User could not be identified");
		}

		if (!is_array($tags)) {
			if(is_string($tags) && $tags !== '') {
				$tags = [ $tags ];
			} else {
				$tags = array();
			}
		}

		$public = true;

		if ($password != null) {
			$public = false;
		}


		if (!$public && !$this->userManager->checkPassword($user, $password)) {

			$msg = 'REST API accessed with wrong password';
			Util::writeLog('medionlibrarys', $msg, Util::WARN);

			return $this->newJsonErrorMessage("Wrong password for user " . $user);
		}

		$attributesToSelect = array('url', 'title');

		if ($select != null) {
			$attributesToSelect = array_merge($attributesToSelect, $select);
			$attributesToSelect = array_unique($attributesToSelect);
		}

		$output = $this->medionlibrarys->findMedionlibrarys($user, 0, $sortby, $tags, true, -1, $public, $attributesToSelect, $conjunction);

		if (count($output) == 0) {
			$output["status"] = 'error';
			$output["message"] = "No results from this query";
			return new JSONResponse($output);
		}

		return new JSONResponse($output);
	}

	/**
	 * @param string $message
	 * @return JSONResponse
	 */
	public function newJsonErrorMessage($message) {
		$output = array();
		$output["status"] = 'error';
		$output["message"] = $message;
		return new JSONResponse($output);
	}

	/**
	 * Checks whether parse_url was able to return proper URL data
	 *
	 * @param bool|array $urlData result of parse_url
	 * @return bool
	 */
	protected function isProperURL($urlData) {
		if ($urlData === false || !isset($urlData['scheme']) || !isset($urlData['host'])) {
			return false;
		}
		return true;
	}
}
