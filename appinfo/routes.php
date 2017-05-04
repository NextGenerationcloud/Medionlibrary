<?php

/**
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Stefan Klemm <mail@stefan-klemm.de>
 * @copyright (c) 2014, Stefan Klemm
 */

namespace OCA\Medionlibrarys\AppInfo;

/**
 * Create your routes in here. The name is the lowercase name of the controller
 * without the controller part, the stuff after the hash is the method.
 * e.g. page#index -> PageController->index()
 *
 * The controller class has to be registered in the application.php file since
 * it's instantiated in there
 */
$application = new Application();

$application->registerRoutes($this, array('routes' => array(
		//Web Template Route
		array('name' => 'web_view#index', 'url' => '/', 'verb' => 'GET'),
		array('name' => 'web_view#medionlibrarylet', 'url' => '/medionlibrarylet', 'verb' => 'GET'),
		//Session Based and CSRF secured Routes
		array('name' => 'medionlibrary#get_medionlibrarys', 'url' => '/medionlibrary', 'verb' => 'GET'),
		array('name' => 'medionlibrary#new_medionlibrary', 'url' => '/medionlibrary', 'verb' => 'POST'),
		array('name' => 'medionlibrary#edit_medionlibrary', 'url' => '/medionlibrary/{id}', 'verb' => 'PUT'),
		array('name' => 'medionlibrary#delete_medionlibrary', 'url' => '/medionlibrary/{id}', 'verb' => 'DELETE'),
		array('name' => 'medionlibrary#click_medionlibrary', 'url' => '/medionlibrary/click', 'verb' => 'POST'),
		array('name' => 'medionlibrary#export_medionlibrary', 'url' => '/medionlibrary/export', 'verb' => 'GET'),
		array('name' => 'medionlibrary#import_medionlibrary', 'url' => '/medionlibrary/import', 'verb' => 'POST'),
		array('name' => 'tags#full_tags', 'url' => '/tag', 'verb' => 'GET'),
		array('name' => 'tags#rename_tag', 'url' => '/tag', 'verb' => 'POST'),
		array('name' => 'tags#delete_tag', 'url' => '/tag', 'verb' => 'DELETE'),
		//Public Rest Api
		array('name' => 'public#return_as_json', 'url' => '/public/rest/v1/medionlibrary', 'verb' => 'GET'),
		array('name' => 'public#new_medionlibrary', 'url' => '/public/rest/v1/medionlibrary', 'verb' => 'POST'),
		array('name' => 'public#edit_medionlibrary', 'url' => '/public/rest/v1/medionlibrary/{id}', 'verb' => 'PUT'),
		array('name' => 'public#delete_medionlibrary', 'url' => '/public/rest/v1/medionlibrary/{id}', 'verb' => 'DELETE'),
		//Legacy Routes
		array('name' => 'medionlibrary#legacy_get_medionlibrarys', 'url' => '/ajax/updateList.php', 'verb' => 'POST'),
		array('name' => 'medionlibrary#legacy_edit_medionlibrary', 'url' => '/ajax/editMedionlibrary.php', 'verb' => 'POST'),
		array('name' => 'medionlibrary#legacy_delete_medionlibrary', 'url' => '/ajax/delMedionlibrary.php', 'verb' => 'POST'),
)));
