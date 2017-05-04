<?php

namespace OCA\Medionlibrarys\Tests;

use OCA\Medionlibrarys\Controller\Rest\PublicController;
use OCA\Medionlibrarys\Controller\Lib\Medionlibrarys;

/**
 * Class Test_PublicController_Medionlibrarys
 *
 * @group DB
 */
class Test_PublicController_Medionlibrarys extends TestCase {

	/** @var	Medionlibrarys */
	protected $libMedionlibrarys;
	private $userid;
	private $request;
	private $db;
	private $userManager;
	/** @var	PublicController */
	private $publicController;

	protected function setUp() {
		parent::setUp();

		$this->userid = "testuser";
		$this->request = \OC::$server->getRequest();
		$this->db = \OC::$server->getDatabaseConnection();
		$this->userManager = \OC::$server->getUserManager();
		if (!$this->userManager->userExists($this->userid)) {
			$this->userManager->createUser($this->userid, 'password');	
		}

		$config = \OC::$server->getConfig();
		$l = \OC::$server->getL10N('medionlibrarys');
		$clientService = \OC::$server->getHTTPClientService();
		$logger = \OC::$server->getLogger();
		$this->libMedionlibrarys = new Medionlibrarys($this->db, $config, $l, $clientService, $logger);

		$this->publicController = new PublicController("medionlibrarys", $this->request, $this->userid, $this->libMedionlibrarys, $this->userManager);
	}

	function testPublicQueryNoUser() {
		$output = $this->publicController->returnAsJson(null, "apassword", null);
		$data = $output->getData();
		$status = $data['status'];
		$this->assertEquals($status, 'error');
	}

	function testPublicQueryWrongUser() {
		$output = $this->publicController->returnAsJson("cqc43dr4rx3x4xatr4", "apassword", null);
		$data = $output->getData();
		$status = $data['status'];
		$this->assertEquals($status, 'error');
	}

	function testPublicQuery() {

		$this->libMedionlibrarys->addMedionlibrary($this->userid, "http://www.golem.de", "Golem", array("four"), "PublicNoTag", true);
		$this->libMedionlibrarys->addMedionlibrary($this->userid, "http://www.9gag.com", "9gag", array("two", "three"), "PublicTag", true);

		$output = $this->publicController->returnAsJson($this->userid);
		$data = $output->getData();
		$this->assertEquals(2, count($data));
	}
	
	function testPublicCreate() {
		$this->publicController->newMedionlibrary("http://www.heise.de", array("tags"=> array("four")), "Heise", true, "PublicNoTag");
		
		// the medionlibrary should exist
		$this->assertNotEquals(false, $this->libMedionlibrarys->medionlibraryExists("http://www.heise.de", $this->userid));

    // public should see this medionlibrary
    $output = $this->publicController->returnAsJson($this->userid);
		$data = $output->getData();
		$this->assertEquals(3, count($data));
	}
	
	function testPrivateCreate() {
		$this->publicController->newMedionlibrary("http://www.private-heise.de", array("tags"=> array("four")), "Heise", false, "PublicNoTag");
		
		// the medionlibrary should exist
		$this->assertNotEquals(false, $this->libMedionlibrarys->medionlibraryExists("http://www.private-heise.de", $this->userid));

		// public should not see this medionlibrary
		$output = $this->publicController->returnAsJson($this->userid);
		$data = $output->getData();
		$this->assertEquals(3, count($data));
	}
	
	function testPrivateEditMedionlibrary() {
		$id = $this->libMedionlibrarys->addMedionlibrary($this->userid, "http://www.heise.de", "Golem", array("four"), "PublicNoTag", true);

		$this->publicController->editMedionlibrary($id, 'https://www.heise.de');
		
		$medionlibrary = $this->libMedionlibrarys->findUniqueMedionlibrary($id, $this->userid);
		$this->assertEquals("https://www.heise.de", $medionlibrary['url']);
	}
	
	function testPrivateDeleteMedionlibrary() {
		$id = $this->libMedionlibrarys->addMedionlibrary($this->userid, "http://www.google.com", "Heise", array("one", "two"), "PrivatTag", false);
		$this->assertNotEquals(false, $this->libMedionlibrarys->medionlibraryExists("http://www.google.com", $this->userid));
		$this->publicController->deleteMedionlibrary($id);
		$this->assertFalse($this->libMedionlibrarys->medionlibraryExists("http://www.google.com", $this->userid));
		$this->cleanDB();
	}

	function cleanDB() {
		$query1 = \OC_DB::prepare('DELETE FROM *PREFIX*medionlibrarys');
		$query1->execute();
		$query2 = \OC_DB::prepare('DELETE FROM *PREFIX*medionlibrarys_tags');
		$query2->execute();
	}

}
