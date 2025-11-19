<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.dc_jsitemap
 */

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Log\Log;
use Joomla\Filesystem\File; 

class PlgSystemDc_Jsitemap extends CMSPlugin{

    protected $autoloadLanguage = true;

    public function onAfterRoute(){
        $app = Factory::getApplication();

        if ($app->isClient('administrator')) {
            return;
        }

        // Ręczne: ?dc_jsitemap=1
        if ($app->input->getInt('dc_jsitemap', 0) === 1) {
            $this->generateSitemap();
            echo "Sitemap wygenerowana ręcznie.";
            $app->close();
        }

        $this->autoGenerateSitemap();
    }

    private function autoGenerateSitemap(): void{
        $path = JPATH_ROOT . '/sitemap.xml';

        if (!file_exists($path)) {
            $this->generateSitemap();
            return;
        }

        $fileAge = time() - filemtime($path);

        if ($fileAge > 86400) {
            $this->generateSitemap();
        }
    }

    private function generateSitemap(){
        $items = [];

        if ($this->params->get('include_articles', 1)) {
            $items = array_merge($items, $this->getArticles());
        }

        if ($this->params->get('include_categories', 1)) {
            $items = array_merge($items, $this->getCategories());
        }

        if ($this->params->get('include_menu', 1)) {
            $items = array_merge($items, $this->getMenuItems());
        }

        $xml = $this->buildXML($items);

        $path = JPATH_ROOT . '/sitemap.xml';

        try {
            File::write($path, $xml);
        } catch (\Exception $e) {
            Log::add('DC Sitemap: Write error: ' . $e->getMessage(), Log::ERROR, 'dc_jsitemap');
        }

        return true;
    }

    private function getArticles(): array{
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select(['id', 'modified'])
            ->from('#__content')
            ->where('state = 1');

        $db->setQuery($query);
        $rows = $db->loadObjectList();

        $priority = $this->params->get('priority_articles', '0.6');
        $baseUrl  = rtrim(Uri::root(), '/');

        $items = [];

        foreach ($rows as $row) {
            $items[] = [
                'loc'      => $baseUrl . Route::_('index.php?option=com_content&view=article&id=' . $row->id, false),
                'lastmod'  => $row->modified ? substr($row->modified, 0, 10) : date('Y-m-d'),
                'priority' => $priority,
            ];
        }

        return $items;
    }


    private function getCategories(): array{
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select(['id'])
            ->from('#__categories')
            ->where('published = 1')
            ->where("extension = 'com_content'");

        $db->setQuery($query);
        $rows = $db->loadObjectList();

        $priority = $this->params->get('priority_categories', '0.5');
        $baseUrl  = rtrim(Uri::root(), '/');

        $items = [];

        foreach ($rows as $row) {
            $items[] = [
                'loc'      => $baseUrl . Route::_('index.php?option=com_content&view=category&id=' . $row->id, false),
                'lastmod'  => date('Y-m-d'),
                'priority' => $priority,
            ];
        }

        return $items;
    }

    private function getMenuItems(): array{
        $menu = Factory::getApplication()->getMenu();
        $items = $menu->getMenu();

        $priority = $this->params->get('priority_menu', '0.8');
        $baseUrl  = rtrim(Uri::root(), '/');

        $output = [];

        foreach ($items as $item) {
            if (
                empty($item->link) ||
                $item->type === 'separator' ||
                $item->type === 'heading' ||
                $item->type === 'alias'
            ) {
                continue;
            }

            $url = $baseUrl . '/' . ltrim($item->route, '/');

            $output[] = [
                'loc'      => $url,
                'lastmod'  => date('Y-m-d'),
                'priority' => $priority,
            ];
        }

        return $output;
    }

    private function buildXML(array $items): string{
        $xml = [];
        $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($items as $i) {
            $xml[] = '  <url>';
            $xml[] = '    <loc>' . htmlspecialchars($i['loc'], ENT_XML1) . '</loc>';
            $xml[] = '    <lastmod>' . $i['lastmod'] . '</lastmod>';
            $xml[] = '    <priority>' . $i['priority'] . '</priority>';
            $xml[] = '  </url>';
        }

        $xml[] = '</urlset>';

        return implode("\n", $xml);
    }
}
