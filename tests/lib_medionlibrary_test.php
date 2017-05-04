<?php

namespace OCA\Medionlibrarys\Tests;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use OCA\Medionlibrarys\Controller\Lib\Medionlibrarys;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\User;

/**
 * Class Test_LibMedionlibrarys_Medionlibrarys
 *
 * @group DB
 */
class Test_LibMedionlibrarys_Medionlibrarys extends TestCase {

	private $userid;

	/** @var Medionlibrarys */
	protected $libMedionlibrarys;

	protected function setUp() {
		parent::setUp();

		$this->userid = User::getUser();

		$db = \OC::$server->getDatabaseConnection();
		$config = \OC::$server->getConfig();
		$l = \OC::$server->getL10N('medionlibrarys');
		$clientService = \OC::$server->getHTTPClientService();
		$logger = \OC::$server->getLogger();
		$this->libMedionlibrarys = new Medionlibrarys($db, $config, $l, $clientService, $logger);
	}

	function testAddMedionlibrary() {
		$this->cleanDB();
		$this->assertCount(0, $this->libMedionlibrarys->findMedionlibrarys($this->userid, 0, 'id', [], true, -1));
		$this->libMedionlibrarys->addMedionlibrary($this->userid, 'http://nextcloud.com', 'Nextcloud project', ['nc', 'cloud'], 'An awesome project');
		$this->assertCount(1, $this->libMedionlibrarys->findMedionlibrarys($this->userid, 0, 'id', [], true, -1));
		$this->libMedionlibrarys->addMedionlibrary($this->userid, 'http://de.wikipedia.org/Ü', 'Das Ü', ['encyclopedia', 'lang'], 'A terrific letter');
		$this->assertCount(2, $this->libMedionlibrarys->findMedionlibrarys($this->userid, 0, 'id', [], true, -1));
	}

	function testFindMedionlibrarys() {
		$this->cleanDB();
		$this->libMedionlibrarys->addMedionlibrary($this->userid, "http://www.duckduckgo.com", "DuckDuckGo", [], "PrivateNoTag", false);
		$this->libMedionlibrarys->addMedionlibrary($this->userid, "http://www.google.de", "Google", array("one"), "PrivateTwoTags", false);
		$this->libMedionlibrarys->addMedionlibrary($this->userid, "http://www.heise.de", "Heise", array("one", "two"), "PrivatTag", false);
		$this->libMedionlibrarys->addMedionlibrary($this->userid, "http://www.golem.de", "Golem", array("one"), "PublicNoTag", true);
		$this->libMedionlibrarys->addMedionlibrary($this->userid, "http://www.9gag.com", "9gag", array("two", "three"), "PublicTag", true);
		$outputPrivate = $this->libMedionlibrarys->findMedionlibrarys($this->userid, 0, "", [], true, -1, false);
		$this->assertCount(5, $outputPrivate);
		$outputPrivateFiltered = $this->libMedionlibrarys->findMedionlibrarys($this->userid, 0, "", ["one"], true, -1, false);
		$this->assertCount(3, $outputPrivateFiltered);
		$outputPublic = $this->libMedionlibrarys->findMedionlibrarys($this->userid, 0, "", [], true, -1, true);
		$this->assertCount(2, $outputPublic);
		$outputPublicFiltered = $this->libMedionlibrarys->findMedionlibrarys($this->userid, 0, "", ["two"], true, -1, true);
		$this->assertCount(1, $outputPublicFiltered);
	}

	function testFindMedionlibrarysSelectAndOrFilteredTags() {
		$this->cleanDB();
		$secondUser = $this->userid . "andHisClone435";
		$this->libMedionlibrarys->addMedionlibrary($this->userid, "http://www.google.de", "Google", array("one"), "PrivateNoTag", false);
		$this->libMedionlibrarys->addMedionlibrary($this->userid, "http://www.heise.de", "Heise", array("one", "two"), "PrivatTag", false);
		$this->libMedionlibrarys->addMedionlibrary($this->userid, "http://www.golem.de", "Golem", array("four"), "PublicNoTag", true);
		$this->libMedionlibrarys->addMedionlibrary($this->userid, "http://www.9gag.com", "9gag", array("two", "three"), "PublicTag", true);
		$this->libMedionlibrarys->addMedionlibrary($secondUser, "http://www.google.de", "Google", array("one"), "PrivateNoTag", false);
		$this->libMedionlibrarys->addMedionlibrary($secondUser, "http://www.heise.de", "Heise", array("one", "two"), "PrivatTag", false);
		$this->libMedionlibrarys->addMedionlibrary($secondUser, "http://www.golem.de", "Golem", array("four"), "PublicNoTag", true);
		$this->libMedionlibrarys->addMedionlibrary($secondUser, "http://www.9gag.com", "9gag", array("two", "three"), "PublicTag", true);
		$resultSetOne = $this->libMedionlibrarys->findMedionlibrarys($this->userid, 0, 'lastmodified', array('one', 'three'), true, -1, false, array('url', 'title'), 'or');
		$this->assertEquals(3, count($resultSetOne));
		$resultOne = $resultSetOne[0];
		$this->assertFalse(isset($resultOne['lastmodified']));
		$this->assertFalse(isset($resultOne['tags']));
	}

	function testFindTags() {
		$this->cleanDB();
		$this->assertEquals($this->libMedionlibrarys->findTags($this->userid), array());
		$this->libMedionlibrarys->addMedionlibrary($this->userid, 'http://nextcloud.com', 'Nextcloud project', array('oc', 'cloud'), 'An awesome project');
		$this->assertEquals(array(0 => array('tag' => 'cloud', 'nbr' => 1), 1 => array('tag' => 'oc', 'nbr' => 1)), $this->libMedionlibrarys->findTags($this->userid));
	}
  
	function testRenameTag() {
		$this->cleanDB();
		$secondUser = $this->userid . "andHisClone435";
		$this->libMedionlibrarys->addMedionlibrary($this->userid, "http://www.google.de", "Google", array("one"), "PrivateNoTag", false);
		$this->libMedionlibrarys->addMedionlibrary($this->userid, "http://www.heise.de", "Heise", array("one", "two"), "PrivatTag", false);
		$this->libMedionlibrarys->addMedionlibrary($this->userid, "http://www.golem.de", "Golem", array("four"), "PublicNoTag", true);
		$this->libMedionlibrarys->addMedionlibrary($this->userid, "http://www.9gag.com", "9gag", array("two", "three"), "PublicTag", true);
		$this->libMedionlibrarys->addMedionlibrary($secondUser, "http://www.google.de", "Google", array("one"), "PrivateNoTag", false);
		$this->libMedionlibrarys->addMedionlibrary($secondUser, "http://www.heise.de", "Heise", array("one", "two"), "PrivatTag", false);
		$this->libMedionlibrarys->addMedionlibrary($secondUser, "http://www.golem.de", "Golem", array("four"), "PublicNoTag", true);
		$this->libMedionlibrarys->addMedionlibrary($secondUser, "http://www.9gag.com", "9gag", array("two", "three"), "PublicTag", true);
		
		$firstUserTags = $this->libMedionlibrarys->findTags($this->userid);
		$this->assertTrue(in_array(['tag' => 'one', 'nbr' => 2], $firstUserTags));
		$this->assertTrue(in_array(['tag' => 'two', 'nbr' => 2], $firstUserTags));
		$this->assertTrue(in_array(['tag' => 'four', 'nbr' => 1], $firstUserTags));
		$this->assertTrue(in_array(['tag' => 'three', 'nbr' => 1], $firstUserTags));
		$this->assertEquals(count($firstUserTags), 4);
		$secondUserTags = $this->libMedionlibrarys->findTags($secondUser);
		$this->assertTrue(in_array(['tag' => 'one', 'nbr' => 2], $secondUserTags));
		$this->assertTrue(in_array(['tag' => 'two', 'nbr' => 2], $secondUserTags));
		$this->assertTrue(in_array(['tag' => 'four', 'nbr' => 1], $secondUserTags));
		$this->assertTrue(in_array(['tag' => 'three', 'nbr' => 1], $secondUserTags));
		$this->assertEquals(count($secondUserTags), 4);

		$this->libMedionlibrarys->renameTag($this->userid, 'four', 'one');
		
		$firstUserTags = $this->libMedionlibrarys->findTags($this->userid);
		$this->assertTrue(in_array(['tag' => 'one', 'nbr' => 3], $firstUserTags));
		$this->assertTrue(in_array(['tag' => 'two', 'nbr' => 2], $firstUserTags));
		$this->assertTrue(in_array(['tag' => 'three', 'nbr' => 1], $firstUserTags));
		$this->assertEquals(count($firstUserTags), 3);
		$secondUserTags = $this->libMedionlibrarys->findTags($secondUser);
		$this->assertTrue(in_array(['tag' => 'one', 'nbr' => 2], $secondUserTags));
		$this->assertTrue(in_array(['tag' => 'two', 'nbr' => 2], $secondUserTags));
		$this->assertTrue(in_array(['tag' => 'four', 'nbr' => 1], $secondUserTags));
		$this->assertTrue(in_array(['tag' => 'three', 'nbr' => 1], $secondUserTags));
		$this->assertEquals(count($secondUserTags), 4);
	}

	function testFindUniqueMedionlibrary() {
		$this->cleanDB();
		$id = $this->libMedionlibrarys->addMedionlibrary($this->userid, "http://www.heise.de", "Heise", array("one", "two"), "PrivatTag", false);
		$medionlibrary = $this->libMedionlibrarys->findUniqueMedionlibrary($id, $this->userid);
		$this->assertEquals($id, $medionlibrary['id']);
		$this->assertEquals("Heise", $medionlibrary['title']);
	}

	function testEditMedionlibrary() {
		$this->cleanDB();
		$control_bm_id = $this->libMedionlibrarys->addMedionlibrary($this->userid, "http://www.golem.de", "Golem", array("four"), "PublicNoTag", true);
		$this->libMedionlibrarys->addMedionlibrary($this->userid, "http://www.9gag.com", "9gag", array("two", "three"), "PublicTag", true);
		$id = $this->libMedionlibrarys->addMedionlibrary($this->userid, "http://www.heise.de", "Heise", array("one", "two"), "PrivatTag", false);
		$this->libMedionlibrarys->editMedionlibrary($this->userid, $id, "http://www.google.de", "NewTitle", array("three"));
		$medionlibrary = $this->libMedionlibrarys->findUniqueMedionlibrary($id, $this->userid);
		$this->assertEquals("NewTitle", $medionlibrary['title']);
		$this->assertEquals("http://www.google.de", $medionlibrary['url']);
		$this->assertEquals($medionlibrary['tags'], 'three');
		
		// Make sure nothing else changed
		$control_medionlibrary = $this->libMedionlibrarys->findUniqueMedionlibrary($control_bm_id, $this->userid);
		$this->assertEquals("Golem", $control_medionlibrary['title']);
		$this->assertEquals("http://www.golem.de", $control_medionlibrary['url']);
		$this->assertEquals($control_medionlibrary['tags'], 'four');
	}

	function testDeleteMedionlibrary() {
		$this->cleanDB();
		$this->libMedionlibrarys->addMedionlibrary($this->userid, "http://www.google.de", "Google", array("one"), "PrivateNoTag", false);
		$id = $this->libMedionlibrarys->addMedionlibrary($this->userid, "http://www.heise.de", "Heise", array("one", "two"), "PrivatTag", false);
		$this->assertNotEquals(false, $this->libMedionlibrarys->medionlibraryExists("http://www.google.de", $this->userid));
		$this->assertNotEquals(false, $this->libMedionlibrarys->medionlibraryExists("http://www.heise.de", $this->userid));
		$this->libMedionlibrarys->deleteUrl($this->userid, $id);
		$this->assertFalse($this->libMedionlibrarys->medionlibraryExists("http://www.heise.de", $this->userid));
	}

	function testGetURLMetadata() {
		$amazonResponse = $this->fetchMock(IResponse::class);
		$amazonResponse->expects($this->once())
			->method('getBody')
			->will($this->returnValue(file_get_contents(__DIR__ . '/res/amazonHtml.file')));
		$amazonResponse->expects($this->once())
			->method('getHeader')
			->with('Content-Type')
			->will($this->returnValue(''));

		$golemResponse = $this->fetchMock(IResponse::class);
		$golemResponse->expects($this->once())
			->method('getBody')
			->will($this->returnValue(file_get_contents(__DIR__ . '/res/golemHtml.file')));
		$golemResponse->expects($this->once())
			->method('getHeader')
			->with('Content-Type')
			->will($this->returnValue('text/html; charset=UTF-8'));

		$clientMock = $this->fetchMock(IClient::class);
		$clientMock->expects($this->exactly(2))
			->method('get')
			->will($this->returnCallback(function ($page) use($amazonResponse, $golemResponse) {
				if($page === 'amazonHtml') {
					return $amazonResponse;
				} else if($page === 'golemHtml') {
					return $golemResponse;
				}
				return null;
			}));

		$clientServiceMock = $this->fetchMock(IClientService::class);
		$clientServiceMock->expects($this->any())
			->method('newClient')
			->will($this->returnValue($clientMock));

		$this->registerHttpService($clientServiceMock);

		// ugly, but works
		$db = \OC::$server->getDatabaseConnection();
		$config = \OC::$server->getConfig();
		$l = \OC::$server->getL10N('medionlibrarys');
		$clientService = \OC::$server->getHTTPClientService();
		$logger = \OC::$server->getLogger();
		$this->libMedionlibrarys = new Medionlibrarys($db, $config, $l, $clientService, $logger);

		$metadataAmazon = $this->libMedionlibrarys->getURLMetadata('amazonHtml');
		$this->assertTrue($metadataAmazon['url'] == 'amazonHtml');
		$this->assertTrue(strpos($metadataAmazon['title'], 'ü') !== false);

		$metadataGolem = $this->libMedionlibrarys->getURLMetadata('golemHtml');
		$this->assertTrue($metadataGolem['url'] == 'golemHtml');
		$this->assertTrue(strpos($metadataGolem['title'], 'f&uuml;r') == false);
	}

	/**
	 * @expectedException \GuzzleHttp\Exception\RequestException
	 */
	public function testGetURLMetaDataTryHarder() {
		$url = 'https://yolo.swag/check';

		$curlOptions = [ 'curl' =>
			[ CURLOPT_HTTPHEADER => ['Expect:'] ]
		];
		if(version_compare(ClientInterface::VERSION, '6') === -1) {
			$options = ['config' => $curlOptions];
		} else {
			$options = $curlOptions;
		}

		$exceptionMock = $this->getMockBuilder(RequestException::class)
			->disableOriginalConstructor()
			->getMock();
		$clientMock = $this->fetchMock(IClient::class);
		$clientMock->expects($this->exactly(2))
			->method('get')
			->withConsecutive(
				[$url, []],
				[$url, $options]
			)
			->willThrowException($exceptionMock);

		$clientServiceMock = $this->fetchMock(IClientService::class);
		$clientServiceMock->expects($this->any())
			->method('newClient')
			->will($this->returnValue($clientMock));

		// ugly, but works
		$db = \OC::$server->getDatabaseConnection();
		$config = \OC::$server->getConfig();
		$l = \OC::$server->getL10N('medionlibrarys');
		$logger = \OC::$server->getLogger();
		$this->libMedionlibrarys = new Medionlibrarys($db, $config, $l, $clientServiceMock, $logger);

		$this->libMedionlibrarys->getURLMetadata($url);
	}

	protected function tearDown() {
		$this->cleanDB();
	}

	function cleanDB() {
		$query1 = \OC_DB::prepare('DELETE FROM *PREFIX*medionlibrarys');
		$query1->execute();
		$query2 = \OC_DB::prepare('DELETE FROM *PREFIX*medionlibrarys_tags');
		$query2->execute();
	}

	/**
	 * Register an http service mock for testing purposes.
	 *
	 * @param IClientService $service
	 */
	private function registerHttpService($service) {
		\OC::$server->registerService('HttpClientService', function () use ($service) {
			return $service;
		});
	}

}
