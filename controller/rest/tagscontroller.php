<?php

namespace OCA\Medionlibrarys\Controller\Rest;

use \OCA\Medionlibrarys\Controller\Lib\Medionlibrarys;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\AppFramework\Http;
use \OCP\AppFramework\ApiController;
use \OCP\IRequest;

class TagsController extends ApiController {

	private $userId;

	/** @var Medionlibrarys */
	private $medionlibrarys;

	public function __construct($appName, IRequest $request, $userId, Medionlibrarys $medionlibrarys) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->medionlibrarys = $medionlibrarys;
	}

	/**
	 * @param string $old_name
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function deleteTag($old_name = "") {

		if ($old_name == "") {
			return new JSONResponse(array(), Http::STATUS_BAD_REQUEST);
		}

		$this->medionlibrarys->deleteTag($this->userId, $old_name);
		return new JSONResponse(array('status' => 'success'));
	}

	/**
	 * @param string $old_name
	 * @param string $new_name
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function renameTag($old_name = "", $new_name = "") {

		if ($old_name == "" || $new_name == "") {
			return new JSONResponse(array(), Http::STATUS_BAD_REQUEST);
		}

		$this->medionlibrarys->renameTag($this->userId, $old_name, $new_name);
		return new JSONResponse(array('status' => 'success'));
	}

	/**
	 * @NoAdminRequired
	 */
	public function fullTags() {
		
		header("Cache-Control: no-cache, must-revalidate");
		header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
		
		$qtags = $this->medionlibrarys->findTags($this->userId, array(), 0, 400);
		$tags = array();
		foreach ($qtags as $tag) {
			$tags[] = $tag['tag'];
		}

		return new JSONResponse($tags);
	}

}
