<?php

// no direct access
defined('_JEXEC') or die ;

// Include the autoloader for the plugin
require_once(JPATH_ROOT.'/plugins/system/rsitemap/vendor/autoload.php');

use Reach\rSitemap;

// Initiate class to hold plugin events
class plgSystemRSitemap extends JPlugin
{

    function onAfterInitialise() {

        $app = JFactory::getApplication();      

        if ($app->isAdmin() and ($app->input->get('rsitemap') == 'regen')) {
        	$r = new rSitemap;
	        $r->saveFile();

        }
    }


    function onContentAfterSave($context, $article, $isNew)
	{
		if ($context = 'com_menus.item') {
			$r = new rSitemap;
	        $r->saveFile();
		}
		return true;
	}

} // END CLASS