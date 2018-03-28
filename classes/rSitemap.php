<?php

namespace Reach;

use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Menu\SiteMenu;

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

        foreach ($languages as $language) {
            if ($language->published == '1') {
                $lang = new \stdClass;
                $lang->code = $language->lang_code;
                $lang->url = $language->sef;
            }

            $useLanguages[] = $lang;
        }

        if ($useLanguages) {
            return $useLanguages;
        }
        return false;
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


    // Add the attributes for the xhtml:link
    public function addLinkAttribute($locale, $code, $sef, $route = null)
    {
        $locale->addAttribute('rel', 'alternate');
        $locale->addAttribute('hreflang', $code);
        $locale->addAttribute('href', \JURI::root().$sef.'/'.$route);
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
                $fullPath = \JURI::root().($this->multiLanguage ? $language->url.'/' : null).$item->route;
                $url = $this->xml->addChild('url');
                $url->addChild('loc', $fullPath);
                if ($this->multiLanguage) {
                    // We do this twice to add the original language last as in Google's example (don't know if it's required or not)
                    foreach ($this->getLanguages() as $link) {
                        if ($link != $language) {
                            $locale = $url->addChild('xhtml:link', null, 'http://www.w3.org/1999/xhtml');
                            $this->addLinkAttribute($locale, $link->code, $link->url, $item->route);
                        }
                    }
                    $locale = $url->addChild('xhtml:link', null, 'http://www.w3.org/1999/xhtml');
                    $this->addLinkAttribute($locale, $language->code, $language->url, $item->route);
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
