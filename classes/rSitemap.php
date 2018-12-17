<?php

namespace Reach;

use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Menu\SiteMenu;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Component\ComponentHelper;

// What do we need to generate a sitemap
class rSitemap
{
    protected $xml;
    protected $multiLanguage;
    protected $ignoreMenus;

    public function __construct($ignoreMenus)
    {
        $this->xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" /><!--?xml version="1.0" encoding="UTF-8"?-->');
        if (count($this->getLanguages()) > 1) {
            $this->multiLanguage = true;
        } else {
            $this->multiLanguage = false;
        }
        $this->ignoreMenus = is_array($ignoreMenus) ? $ignoreMenus : [];
    }

    // Gets the languages and creates an array
    public function getLanguages()
    {
        $languages = LanguageHelper::getContentLanguages();
        $defaultLanguage = ComponentHelper::getParams('com_languages')->get('site');

        foreach ($languages as $language) {
            if ($language->published == '1') {
                $lang = new \stdClass;
                $lang->id = $language->lang_id;
                $lang->code = $language->lang_code;
                if (($language->lang_code == $defaultLanguage) && ($this->noPrefixForDefaultLanguage())) {
                    $lang->url = '';
                    $lang->default = 1;
                } else {
                    $lang->url = $language->sef.'/';
                    $lang->default = 0;
                }                
            }
            $useLanguages[] = $lang;
        }

        usort($useLanguages, array($this, "cmp"));

        if ($useLanguages) {
            return $useLanguages;
        }
        return false;
    }
    
    // Helper function to sort array based on object value
    public function cmp($a, $b)
    {
        return $a->default < $b->default;
    }

    // Gets all menu items
    public function getMenuItems()
    {
        $menu = new SiteMenu;
        $items = $menu->getMenu();
        foreach ($items as $item) {
            if (in_array($item->menutype, $this->ignoreMenus)) {
                continue;
            }
            switch ($item->type) :
                case 'separator':
                case 'url':
                case 'alias':
                    break;
                default:
                    if ($item->home == '1') {
                        break;
                    }
                $realItems[] = $item;
            endswitch;
        }
        return $realItems;
    }
    
    // Get an array of objects from the Falang translations of the menu items
    public function getFalangRouteArray($lang_id)
    {
        if (! ComponentHelper::getComponent('com_falang', true)->enabled) {
            return false;
        }
        $db = \JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select(['reference_id', 'value']);
        $query->from('#__falang_content');
        $query->where($db->quoteName('language_id') . ' = '. $db->quote($lang_id));
        $query->where($db->quoteName('reference_table') . ' LIKE '. $db->quote('menu'));
        $query->where($db->quoteName('reference_field') . ' LIKE '. $db->quote('path'));
        $query->where($db->quoteName('published') . ' = '. 1);
        $rows = $db->setQuery($query);
        return $db->loadObjectList('reference_id');
    }
    
    // Used to replace the route if a falang translation is active
    public function getRoute($menuItem, $language)
    {
        if ($language->default == 1) {
            return $menuItem->route;
        }
        $falang = $this->getFalangRouteArray($language->id);
        if (isset($falang[$menuItem->id])) {
            return $falang[$menuItem->id]->value;
        }
    }

    // Check if we have disabled prefix for the default language
    public function noPrefixForDefaultLanguage() 
    {
        $filterPlugin = PluginHelper::getPlugin('system', 'languagefilter');
        if (!empty($filterPlugin)) {
            $filterPluginParams  = new \JRegistry($filterPlugin->params);
            if ($filterPluginParams->get('remove_default_prefix') == '1') {
                return true;
            }
        }
        return false;
    }

    // Add the attributes for the xhtml:link
    public function addLinkAttribute($locale, $code, $sef, $route = null)
    {
        $locale->addAttribute('rel', 'alternate');
        $locale->addAttribute('hreflang', $code);
        $locale->addAttribute('href', \JURI::root().$sef.$route);
    }

    // Add the XML for the homepage
    public function addHomeXMLNodes()
    {
        foreach ($this->getLanguages() as $language) {
            $fullPath = \JURI::root().($this->multiLanguage ? $language->url : null);
            $url = $this->xml->addChild('url');
            $url->addChild('loc', $fullPath);
            if ($this->multiLanguage) {
                foreach ($this->getLanguages() as $link) {
                    if ($link != $language) {
                        $locale = $url->addChild('xhtml:link', null, 'http://www.w3.org/1999/xhtml');
                        $this->addLinkAttribute($locale, $link->code, $link->url);
                    }
                }
                $locale = $url->addChild('xhtml:link', null, 'http://www.w3.org/1999/xhtml');
                $this->addLinkAttribute($locale, $language->code, $language->url);
            }
        }
    }

    // Add the needed XML nodes
    public function addXMLNodes()
    {
        foreach ($this->getLanguages() as $language) {
            foreach ($this->getMenuItems() as $item) {
                $fullPath = \JURI::root().($this->multiLanguage ? $language->url : null).$this->getRoute($item, $language);
                $url = $this->xml->addChild('url');
                $url->addChild('loc', $fullPath);
                if ($this->multiLanguage) {
                    // We do this twice to add the original language last as in Google's example (don't know if it's required or not)
                    foreach ($this->getLanguages() as $link) {
                        if ($link != $language) {
                            $locale = $url->addChild('xhtml:link', null, 'http://www.w3.org/1999/xhtml');
                            $this->addLinkAttribute($locale, $link->code, $link->url, $this->getRoute($item, $link));
                        }
                    }
                    $locale = $url->addChild('xhtml:link', null, 'http://www.w3.org/1999/xhtml');
                    $this->addLinkAttribute($locale, $language->code, $language->url, $this->getRoute($item, $language));
                }
            }
        }
    }

    public function generateXML()
    {
        $this->addHomeXMLNodes();
        $this->addXMLNodes();
        $dom = new \DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($this->xml->asXML());
        return $dom->saveXML();
    }

    public function saveFile()
    {
        $path = JPATH_ROOT.'/sitemap.xml';
        file_put_contents($path, $this->generateXML());
    }
}
