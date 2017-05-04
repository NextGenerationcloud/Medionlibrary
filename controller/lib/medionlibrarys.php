<?php

/**
 * @author Arthur Schiwon
 * @copyright 2016 Arthur Schiwon blizzz@arthur-schiwon.de
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
/**
 * This class manages medionlibrarys
 */

namespace OCA\Medionlibrarys\Controller\Lib;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\ILogger;

class Medionlibrarys {

	/** @var IDBConnection */
	private $db;

	/** @var IConfig */
	private $config;

	/** @var IL10N */
	private $l;

	/** @var IClientService */
	private $httpClientService;

	/** @var ILogger */
	private $logger;

	public function __construct(
		IDBConnection $db,
		IConfig $config,
		IL10N $l,
		IClientService $httpClientService,
		ILogger $logger
	) {
		$this->db = $db;
		$this->config = $config;
		$this->l = $l;
		$this->httpClientService = $httpClientService;
		$this->logger = $logger;
	}

	/**
	 * @brief Finds all tags for medionlibrarys
	 * @param string $userId UserId
	 * @param array $filterTags of tag to look for if empty then every tag
	 * @param int $offset
	 * @param int $limit
	 * @return array Found Tags
	 */
	public function findTags($userId, $filterTags = [], $offset = 0, $limit = -1) {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('t.tag')
			->selectAlias($qb->createFunction('COUNT(' . $qb->getColumnName('t.medionlibrary_id') . ')'), 'nbr')
			->from('medionlibrarys_tags', 't')
			->innerJoin('t','medionlibrarys','b',$qb->expr()->andX(
				$qb->expr()->eq('b.id', 't.medionlibrary_id'),
				$qb->expr()->eq('b.user_id', $qb->createNamedParameter($userId))));
		if (!empty($filterTags)) {
			$qb->where($qb->expr()->notIn('t.tag', $filterTags));
		}
		$qb
			->groupBy('t.tag')
			->orderBy('nbr', 'DESC')
			->setFirstResult($offset);
		if ($limit != -1) {
			$qb->setMaxResults($limit);
		}
		$tags = $qb->execute()->fetchAll();
		return $tags;
	}

	/**
	 * @brief Finds Medionlibrary with certain ID
	 * @param int $id MedionlibraryId
	 * @param string $userId UserId
	 * @return array Specific Medionlibrary
	 */
	public function findUniqueMedionlibrary($id, $userId) {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('*')
			->from('medionlibrarys')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('id', $qb->createNamedParameter($id)));
		$result = $qb->execute()->fetch();
		
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('tag')
			->from('medionlibrarys_tags')
			->where($qb->expr()->eq('medionlibrary_id', $qb->createNamedParameter($id)));
		$result['tags'] = $qb->execute()->fetchColumn();
		return $result;
	}

	/**
	 * @brief Check if an URL is medionlibraryed
	 * @param string $url Url of a possible medionlibrary
	 * @param string $userId UserId
	 * @return bool|int the medionlibrary ID if existing, false otherwise
	 */
	public function medionlibraryExists($url, $userId) {
		$encodedUrl = htmlspecialchars_decode($url);
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('id')
			->from('medionlibrarys')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('url', $qb->createNamedParameter($encodedUrl)));
		$result = $qb->execute()->fetch();
		if ($result) {
			return $result['id'];
		} else {
			return false;
		}
	}

	/**
	 * @brief Finds all medionlibrarys, matching the filter
	 * @param string $userid UserId
	 * @param int $offset offset
	 * @param string $sqlSortColumn result with this column
	 * @param string|array $filters filters can be: empty -> no filter, a string -> filter this, a string array -> filter for all strings
	 * @param bool $filterTagOnly true, filter affects only tags, else filter affects url, title and tags
	 * @param int $limit limit of items to return (default 10) if -1 or false then all items are returned
	 * @param bool $public check if only public medionlibrarys should be returned
	 * @param array $requestedAttributes select all the attributes that should be returned. default is * + tags
	 * @param string $tagFilterConjunction select wether the filterTagOnly should filter with an AND or an OR  conjunction
	 * @return array Collection of specified medionlibrarys
	 */
	public function findMedionlibrarys(
		$userid,
		$offset,
		$sqlSortColumn,
		$filters,
		$filterTagOnly,
		$limit = 10,
		$public = false,
		$requestedAttributes = null,
		$tagFilterConjunction = "and"
	) {
		$dbType = $this->config->getSystemValue('dbtype', 'sqlite');
		if (is_string($filters)) {
			$filters = array($filters);
		}

		$tableAttributes = array('id', 'url', 'title', 'user_id', 'description',
			'public', 'added', 'lastmodified', 'clickcount',);

		$returnTags = true;
		
		$qb = $this->db->getQueryBuilder();
		
		if ($requestedAttributes != null) {
			$key = array_search('tags', $requestedAttributes);
			if ($key == false) {
				$returnTags = false;
			} else {
				unset($requestedAttributes[$key]);
			}
			$selectedAttributes = array_intersect($tableAttributes, $requestedAttributes);
			$qb->select($selectedAttributes);
		}else{
			$selectedAttributes = $tableAttributes;
		}
		$qb->select($selectedAttributes);

		if ($dbType == 'pgsql') {
			$qb->selectAlias($qb->createFunction("array_to_string(array_agg(" . $qb->getColumnName('t.tag') . "), ',')"), 'tags');
        }else{
			$qb->selectAlias($qb->createFunction('GROUP_CONCAT(' . $qb->getColumnName('t.tag') . ')'), 'tags');
		}
		if (!in_array($sqlSortColumn, $tableAttributes)) {
			$sqlSortColumn = 'lastmodified';
		}

		$qb
			->from('medionlibrarys', 'b')
			->leftJoin('b', 'medionlibrarys_tags', 't', $qb->expr()->eq('t.medionlibrary_id', 'b.id'))
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userid)))
			->groupBy(array_merge($selectedAttributes, [$sqlSortColumn]));

		if ($public) {
			$qb->andWhere($qb->expr()->eq('public', $qb->createNamedParameter(1)));
		}
		
		if (count($filters) > 0) {
			$this->findMedionlibrarysBuildFilter($qb, $filters, $filterTagOnly, $tagFilterConjunction);
		}

		$qb->orderBy($sqlSortColumn, 'DESC');
		if ($limit != -1 && $limit !== false) {
			$qb->setMaxResults($limit);
			if ($offset != null) {
				$qb->setFirstResult($offset);
			}
		}

		$results = $qb->execute()->fetchAll();
		$medionlibrarys = array();
		foreach ($results as $result) {
			if ($returnTags) {
				$result['tags'] = explode(',', $result['tags']);
			} else {
				unset($result['tags']);
			}
			$medionlibrarys[] = $result;
		}
		return $medionlibrarys;
	}

	/**
	 * @param IQueryBuilder $qb
	 * @param array $filters
	 * @param bool $filterTagOnly
	 * @param string $tagFilterConjunction
	 */
	private function findMedionlibrarysBuildFilter(&$qb, $filters, $filterTagOnly, $tagFilterConjunction) {
		$connectWord = 'AND';
		if ($tagFilterConjunction == 'or') {
			$connectWord = 'OR';
		}
		if (count($filters) == 0) {
			return;
		}
		$filterExpressions = [];
		$otherColumns = ['b.url', 'b.title', 'b.description'];
    $i = 0;
		foreach ($filters as $filter) {
      $qb->leftJoin('b', 'medionlibrarys_tags', 't' . $i, $qb->expr()->eq('t' . $i . '.medionlibrary_id', 'b.id'));
			$filterExpressions[] = $qb->expr()->eq('t'.$i.'.tag', $qb->createNamedParameter($filter));
			if (!$filterTagOnly) {
				foreach ($otherColumns as $col) {
					$filterExpressions[] = $qb->expr()->like($qb->createFunction('lower(' . $qb->getColumnName($col) . ')'),
						$qb->createNamedParameter('%' . $this->db->escapeLikeParameter(strtolower($filter)) . '%'));
				}
			}
      $i++;
		}
		if ($connectWord == 'AND') {
			$filterExpression = call_user_func_array([$qb->expr(), 'andX'], $filterExpressions);
		}else {
			$filterExpression = call_user_func_array([$qb->expr(), 'orX'], $filterExpressions);
		}
		$qb->andWhere($filterExpression);
	}

	/**
	 * @brief Delete medionlibrary with specific id
	 * @param string $userId UserId
	 * @param int $id Medionlibrary ID to delete
	 * @return boolean Success of operation
	 */
	public function deleteUrl($userId, $id) {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('id')
			->from('medionlibrarys')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('id', $qb->createNamedParameter($id)));

		$id = $qb->execute()->fetchColumn();
		if ($id === false) {
			return false;
		}

		$qb = $this->db->getQueryBuilder();
		$qb
			->delete('medionlibrarys')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
		$qb->execute();

		$qb = $this->db->getQueryBuilder();
		$qb
			->delete('medionlibrarys_tags')
			->where($qb->expr()->eq('medionlibrary_id', $qb->createNamedParameter($id)));
		$qb->execute();
		return true;
	}

	/**
	 * @brief Rename a tag
	 * @param string $userId UserId
	 * @param string $old Old Tag Name
	 * @param string $new New Tag Name
	 * @return boolean Success of operation
	 */
	public function renameTag($userId, $old, $new) {
		// Remove about-to-be duplicated tags
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('tgs.medionlibrary_id')
			->from('medionlibrarys_tags', 'tgs')
			->innerJoin('tgs', 'medionlibrarys', 'bm', $qb->expr()->eq('tgs.medionlibrary_id', 'bm.id'))
			->innerJoin('tgs', 'medionlibrarys_tags', 't', $qb->expr()->eq('tgs.medionlibrary_id', 't.medionlibrary_id'))
			->where($qb->expr()->eq('tgs.tag', $qb->createNamedParameter($new)))
			->andWhere($qb->expr()->eq('bm.user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('t.tag', $qb->createNamedParameter($old)));
		$duplicates = $qb->execute()->fetchColumn();
		if ($duplicates !== false) {
			$qb = $this->db->getQueryBuilder();
			$qb
				->delete('medionlibrarys_tags', 't')
				->where($qb->expr()->in('t.medionlibrary_id', $qb->createNamedParameter($duplicates)))
				->andWhere($qb->expr()->eq('t.tag', $qb->createNamedParameter($old)));
			$qb->execute();
		}

		// Update tags to the new label
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('tgs.medionlibrary_id')
			->from('medionlibrarys_tags', 'tgs')
			->innerJoin('tgs', 'medionlibrarys', 'bm', $qb->expr()->eq('tgs.medionlibrary_id', 'bm.id'))
			->where($qb->expr()->eq('tgs.tag', $qb->createNamedParameter($old)))
			->andWhere($qb->expr()->eq('bm.user_id', $qb->createNamedParameter($userId)));
		$medionlibrarys = $qb->execute()->fetchColumn();
		if ($medionlibrarys !== false) {
			$qb = $this->db->getQueryBuilder();
			$qb
				->update('medionlibrarys_tags')
				->set('tag', $qb->createNamedParameter($new))
				->where($qb->expr()->eq('tag', $qb->createNamedParameter($old)))
				->andWhere($qb->expr()->in('medionlibrary_id', $qb->createNamedParameter($medionlibrarys)));
			$qb->execute();
		}
		return true;
	}

	/**
	 * @brief Delete a tag
	 * @param string $userid UserId
	 * @param string $old Tag Name to delete
	 * @return boolean Success of operation
	 */
	public function deleteTag($userid, $old) {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('tgs.medionlibrary_id')
			->from('medionlibrarys_tags', 'tgs')
			->innerJoin('tgs', 'medionlibrarys', 'bm', $qb->expr()->eq('tgs.medionlibrary_id', 'bm.id'))
			->where($qb->expr()->eq('tgs.tag', $qb->createNamedParameter($old)))
			->andWhere($qb->expr()->eq('bm.user_id', $qb->createNamedParameter($userid)));
		$medionlibrarys = $qb->execute()->fetchColumn();
		if ($medionlibrarys !== false) {
			$qb = $this->db->getQueryBuilder();
			$qb
				->delete('medionlibrarys_tags', 'tgs')
				->where($qb->expr()->eq('tgs.tag', $qb->createNamedParameter($old)))
				->andWhere($qb->expr()->in('bm.user_id', $qb->createNamedParameter($medionlibrarys)));
			return $qb->execute();
		}
		return true;
	}

	/**
	 * Edit a medionlibrary
	 *
	 * @param string $userid UserId
	 * @param int $id The id of the medionlibrary to edit
	 * @param string $url The url to set
	 * @param string $title Name of the medionlibrary
	 * @param array $tags Simple array of tags to qualify the medionlibrary (different tags are taken from values)
	 * @param string $description A longer description about the medionlibrary
	 * @param boolean $isPublic True if the medionlibrary is publishable to not registered users
	 * @return null
	 */
	public function editMedionlibrary($userid, $id, $url, $title, $tags = [], $description = '', $isPublic = false) {

		$isPublic = $isPublic ? 1 : 0;

		// Update the record

		$qb = $this->db->getQueryBuilder();
		$qb
			->update('medionlibrarys')
			->set('url', $qb->createNamedParameter(htmlspecialchars_decode($url)))
			->set('title', $qb->createNamedParameter(htmlspecialchars_decode($title)))
			->set('public', $qb->createNamedParameter($isPublic))
			->set('description', $qb->createNamedParameter(htmlspecialchars_decode($description)))
			->set('lastmodified', $qb->createFunction('UNIX_TIMESTAMP()'))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userid)));

		$result = $qb->execute();
		// Abort the operation if medionlibrary couldn't be set
		// (probably because the user is not allowed to edit this medionlibrary)
		if ($result == 0) {
			exit();
		}

		// Remove old tags

		$qb = $this->db->getQueryBuilder();
		$qb
			->delete('medionlibrarys_tags')
			->where($qb->expr()->eq('medionlibrary_id', $qb->createNamedParameter($id)));
		$qb->execute();

		// Add New Tags
		$this->addTags($id, $tags);

		return $id;
	}

	/**
	 * Add a medionlibrary
	 *
	 * @param string $userid UserId
	 * @param string $url
	 * @param string $title Name of the medionlibrary
	 * @param array $tags Simple array of tags to qualify the medionlibrary (different tags are taken from values)
	 * @param string $description A longer description about the medionlibrary
	 * @param boolean $isPublic True if the medionlibrary is publishable to not registered users
	 * @return int The id of the medionlibrary created
	 */
	public function addMedionlibrary($userid, $url, $title, $tags = array(), $description = '', $isPublic = false) {
		$public = $isPublic ? 1 : 0;
		$urlWithoutPrefix = trim(substr($url, strpos($url, "://") + 3)); // Removes everything from the url before the "://" pattern (included)
		if($urlWithoutPrefix === '') {
			throw new \InvalidArgumentException('Medionlibrary URL is missing');
		}
		$decodedUrlNoPrefix = htmlspecialchars_decode($urlWithoutPrefix);
		$decodedUrl = htmlspecialchars_decode($url);

		$title = mb_substr($title, 0, 4096);
		$description = mb_substr($description, 0, 4096);

		// Change lastmodified date if the record if already exists
		
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('*')
			->from('medionlibrarys')
			->where($qb->expr()->like('url', $qb->createParameter('url'))) // Find url in the db independantly from its protocol
			->andWhere($qb->expr()->eq('user_id', $qb->createParameter('userID')));
		$qb->setParameters([
			'userID' => $userid,
			'url' => '%' . $this->db->escapeLikeParameter($decodedUrlNoPrefix)
		]);
		$row = $qb->execute()->fetch();
		
		if ($row) {
			$qb = $this->db->getQueryBuilder();
			$qb
				->update('medionlibrarys')
				->set('lastmodified', $qb->createFunction('UNIX_TIMESTAMP()'))
				->set('url', $qb->createParameter('url'));
			if (trim($title) != '') { // Do we replace the old title
				$qb->set('title', $qb->createParameter('title'));
			}

			if (trim($description) != '') { // Do we replace the old description
				$qb->set('description', $qb->createParameter('description'));
			}

			$qb
				->where($qb->expr()->like('url', $qb->createParameter('compareUrl'))) // Find url in the db independantly from its protocol
				->andWhere($qb->expr()->eq('user_id', $qb->createParameter('userID')));
				$qb->setParameters([
					'userID' => $userid,
					'url' => $decodedUrl,
					'compareUrl' => '%' . $this->db->escapeLikeParameter($decodedUrlNoPrefix),
					'title' => $title,
					'description' => $description,
				]);
				$qb->execute();
			return $row['id'];
		} else {
			$qb = $this->db->getQueryBuilder();
			$qb
				->insert('medionlibrarys')
				->values(array(
					'url' => $qb->createParameter('url'),
					'title' => $qb->createParameter('title'),
					'user_id' => $qb->createParameter('user_id'),
					'public' => $qb->createParameter('public'),
					'added' => $qb->createFunction('UNIX_TIMESTAMP()'),
					'lastmodified' => $qb->createFunction('UNIX_TIMESTAMP()'),
					'description' => $qb->createParameter('description')
				))
				->where($qb->expr()->eq('user_id', $qb->createParameter('user_id')));
			$qb->setParameters(array(
				'user_id' => $userid,
				'url' => $decodedUrl,
				'title' => htmlspecialchars_decode($title), // XXX: Should the title update above also decode it first?
				'public' => $public,
				'description' => $description
			));	

			$qb->execute();

			$insertId = $qb->getLastInsertId();

			if ($insertId !== false) {
				$this->addTags($insertId, $tags);
				return $insertId;
			}
		}
		return -1;
	}

	/**
	 * @brief Add a set of tags for a medionlibrary
	 * @param int $medionlibraryID The medionlibrary reference
	 * @param array $tags Set of tags to add to the medionlibrary
	 * */
	private function addTags($medionlibraryID, $tags) {
		foreach ($tags as $tag) {
			$tag = trim($tag);
			if (empty($tag)) {
				//avoid saving white spaces
				continue;
			}

			// check if tag for this medionlibrary exists

			$qb = $this->db->getQueryBuilder();
			$qb
			->select('*')
			->from('medionlibrarys_tags')
				->where($qb->expr()->eq('medionlibrary_id', $qb->createNamedParameter($medionlibraryID)))
				->andWhere($qb->expr()->eq('tag', $qb->createNamedParameter($tag)));

			if ($qb->execute()->fetch()) continue;

			$qb = $this->db->getQueryBuilder();
			$qb
				->insert('medionlibrarys_tags')
				->values(array(
					'tag' => $qb->createNamedParameter($tag),
					'medionlibrary_id' => $qb->createNamedParameter($medionlibraryID)
				));
			$qb->execute();
		}
	}

	/**
	 * @brief Import Medionlibrarys from html formatted file
	 * @param string $user User imported Medionlibrarys should belong to
	 * @param string $file Content to import
	 * @return null
	 * */
	public function importFile($user, $file) {
		libxml_use_internal_errors(true);
		$dom = new \domDocument();

		$dom->loadHTMLFile($file);
		$links = $dom->getElementsByTagName('a');

		$errors = [];

		// Reintroduce transaction here!?
		foreach ($links as $link) {
			/* @var \DOMElement $link */
			$title = $link->nodeValue;
			$ref = $link->getAttribute("href");
			$tagStr = '';
			if ($link->hasAttribute("tags"))
				$tagStr = $link->getAttribute("tags");
			$tags = explode(',', $tagStr);

			$descriptionStr = '';
			if ($link->hasAttribute("description"))
				$descriptionStr = $link->getAttribute("description");
			try {
				$this->addMedionlibrary($user, $ref, $title, $tags, $descriptionStr);
			} catch (\InvalidArgumentException $e) {
				$this->logger->logException($e, ['app' => 'medionlibrarys']);
				$errors[] =  $this->l->t('Failed to import one medionlibrary, because: ') . $e->getMessage();
			}
		}

		return $errors;
	}

	/**
	 * @brief Load Url and receive Metadata (Title)
	 * @param string $url Url to load and analyze
	 * @param bool $tryHarder modifies cURL options for another atttempt if the
	 *                        first request did not succeed (e.g. cURL error 18)
	 * @return array Metadata for url;
	 * @throws \Exception|ClientException
	 */
	public function getURLMetadata($url, $tryHarder = false) {
		$metadata = ['url' => $url];
		$page = $contentType = '';
		
		try {
			$client = $this->httpClientService->newClient();
			$options = [];
			if($tryHarder) {
				$curlOptions = [ 'curl' =>
					[ CURLOPT_HTTPHEADER => ['Expect:'] ]
				];
				if(version_compare(ClientInterface::VERSION, '6') === -1) {
					$options = ['config' => $curlOptions];
				} else {
					$options = $curlOptions;
				}
			}
			$request = $client->get($url, $options);
			$page = $request->getBody();
			$contentType = $request->getHeader('Content-Type');
		} catch (ClientException $e) {
			$errorCode = $e->getCode();
			if (!($errorCode >= 401 && $errorCode <= 403)) {
				// whitelist Unauthorized, Forbidden and Paid pages
				throw $e;
			}
		} catch (\GuzzleHttp\Exception\RequestException $e) {
			if($tryHarder) {
				throw $e;
			}
			return $this->getURLMetadata($url, true);
		} catch (\Exception $e) {
			throw $e;
		}
		
		//Check for encoding of site.
		//If not UTF-8 convert it.
		$encoding = array();
		preg_match('#.+?/.+?;\\s?charset\\s?=\\s?(.+)#i', $contentType, $encoding);
		if(empty($encoding)) {
			preg_match('/charset="?(.*?)["|;]/i', $page, $encoding);
		}

		if (isset($encoding[1])) {
			$decodeFrom = strtoupper($encoding[1]);
		} else {
			$decodeFrom = 'UTF-8';
		}

		if ($page) {

			if ($decodeFrom != 'UTF-8') {
				$page = iconv($decodeFrom, "UTF-8", $page);
			}

			preg_match("/<title>(.*)<\/title>/si", $page, $match);
			
			if (isset($match[1])) {
				$metadata['title'] = html_entity_decode($match[1]);
			}
		}
		
		return $metadata;
	}

	/**
	 * @brief Separate Url String at comma character
	 * @param $line String of Tags
	 * @return array Array of Tags
	 * */
	public function analyzeTagRequest($line) {
		$tags = explode(',', $line);
		$filterTag = array();
		foreach ($tags as $tag) {
			if (trim($tag) != '')
				$filterTag[] = trim($tag);
		}
		return $filterTag;
	}

}
