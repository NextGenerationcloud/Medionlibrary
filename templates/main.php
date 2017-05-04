<?php
script('medionlibrarys', 'settings');
script('medionlibrarys', 'medionlibrarys');
style('medionlibrarys', 'medionlibrarys');

script('medionlibrarys', '3rdparty/tag-it');
script('medionlibrarys', '3rdparty/js_tpl');
style('medionlibrarys', '3rdparty/jquery.tagit');

/**
 * Copyright (c) 2011 Marvin Thomas Rabe <mrabe@marvinrabe.de>
 * Copyright (c) 2011 Arthur Schiwon <blizzz@arthur-schiwon.de>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */
$medionlibraryleturl = $_['medionlibraryleturl'];
$medionlibraryletscript = medionlibrarylet($medionlibraryleturl);

function medionlibrarylet($medionlibraryleturl) {
	$l = \OC::$server->getL10N('medionlibrarys');
	$defaults = \OC::$server->getThemingDefaults();
	$blet = "javascript:(function(){var a=window,b=document,c=encodeURIComponent,e=c(document.title),d=a.open('";
	$blet .= $medionlibraryleturl;
	$blet .= "?output=popup&url='+c(b.location)+'&title='+e,'bkmk_popup','left='+((a.screenX||a.screenLeft)+10)+',top='+((a.screenY||a.screenTop)+10)+',height=400px,width=550px,resizable=1,alwaysRaised=1');a.setTimeout(function(){d.focus()},300);})();";
	$help_msg = $l->t('Drag this to your browser medionlibrarys and click it, when you want to medionlibrary a webpage quickly:');
	$output = '<div id="medionlibrarylet_hint" class="bkm_hint">' . $help_msg . '</div><a class="button medionlibrarylet" href="' . $blet . '">' . $l->t('Add to ' . \OCP\Util::sanitizeHTML($defaults->getName())) . '</a>';
	return $output;
}
?>

<div id="app-navigation">
    <ul id="navigation-list">
        <li>
            <form id="add_form">
                <input type="text" id="add_url" value="" placeholder="<?php p($l->t('Address')); ?>"/>
                <button id="medionlibrary_add_submit" title="Add" class="icon-add"></button>
            </form>
            <p id="tag_filter" class="open">
                <input type="text" value="<?php if(isset($_['req_tag'])) p($_['req_tag']); else ""; ?>"/>


            </p>
            <input type="hidden" id="medionlibraryFilterTag" value="<?php if(isset($_['req_tag'])) p($_['req_tag']); else ""; ?>" />
            <label id="tag_select_label"><?php p($l->t('Filterable Tags')); ?></label>
        </li>
        <li class="tag_list">
        </li>
    </ul>

    <div id="app-settings">
        <div id="app-settings-header">
            <button class="settings-button generalsettings" data-apps-slide-toggle="#app-settings-content" tabindex="0"></button>
        </div>
        <div id="app-settings-content">


			<?php require 'settings.php'; ?>
        </div>
    </div>

</div>
<div id="app-content">
    <div id="emptycontent" style="display: none;">
        <p class="title"><?php
			p($l->t('You have no medionlibrarys'));
			$embedded = true;
			print_unescaped($medionlibraryletscript);
			?></p>
        <br/><br/>


        <div class="bkm_hint">
            <a href="#" id="firstrun_setting">
				<?php p($l->t('You can also import a medionlibrary file')); ?>
            </a></div>
    </div>
    <div class="medionlibrarys_list"></div>
</div>

<?php
require 'js_tpl.php';
