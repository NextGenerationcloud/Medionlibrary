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

$navigationEntry = function () {
	return [
		'id' => 'medionlibrarys',
		'order' => 10,
		'name' => \OC::$server->getL10N('medionlibrarys')->t('Medionlibrarys'),
		'href' => \OC::$server->getURLGenerator()->linkToRoute('medionlibrarys.web_view.index'),
		'icon' => \OC::$server->getURLGenerator()->imagePath('medionlibrarys', 'medionlibrarys.svg'),
	];
};
\OC::$server->getNavigationManager()->add($navigationEntry);

\OC::$server->getSearch()->registerProvider('OCA\Medionlibrarys\Controller\Lib\Search', array('apps' => array('medionlibrarys')));
