<?php
/**
 * @package VM Mail & Invoice Studio
 * @copyright Copyright (C) 2026 Darko Fatur / ToPowerYou
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die;


use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

final class Com_VmmailinvoicestudioInstallerScript
{
    public function postflight(string $type, $parent): void
    {
        if ($type === 'uninstall') {
            return;
        }
        // Remove update associations only for our two template plugins.
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)->select($db->quoteName('extension_id'))->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('vmstudiodesign'))
            ->where($db->quoteName('element') . ' IN (' . $db->quote('studioexample') . ',' . $db->quote('studioinvoicecollection') . ')');
        foreach ($db->setQuery($query)->loadColumn() as $extensionId) {
            $extensionId = (int) $extensionId;
            $query = $db->getQuery(true)->select($db->quoteName('update_site_id'))->from($db->quoteName('#__update_sites_extensions'))
                ->where($db->quoteName('extension_id') . ' = ' . $extensionId);
            $sites = $db->setQuery($query)->loadColumn();
            foreach (['#__update_sites_extensions', '#__updates'] as $table) {
                $db->setQuery($db->getQuery(true)->delete($db->quoteName($table))
                    ->where($db->quoteName('extension_id') . ' = ' . $extensionId))->execute();
            }
            foreach ($sites as $siteId) {
                $siteId = (int) $siteId;
                $query = $db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__update_sites_extensions'))
                    ->where($db->quoteName('update_site_id') . ' = ' . $siteId);
                if (!(int) $db->setQuery($query)->loadResult()) {
                    $db->setQuery($db->getQuery(true)->delete($db->quoteName('#__update_sites'))
                        ->where($db->quoteName('update_site_id') . ' = ' . $siteId))->execute();
                }
            }
        }
    }

    public function preflight(string $type, $parent): bool
    {
        if ($type === 'uninstall') {
            return true;
        }
        if (version_compare(PHP_VERSION, '8.1.0', '<') || version_compare(JVERSION, '5.0.0', '<') || version_compare(JVERSION, '6.0.0', '>=')) {
            Factory::getApplication()->enqueueMessage('VM Mail & Invoice Studio requires Joomla 5 and PHP 8.1 or later.', 'error');
            return false;
        }
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)->select($db->quoteName('manifest_cache'))->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_virtuemart'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'));
        $manifest = json_decode((string) $db->setQuery($query)->loadResult(), true);
        $version = (string) ($manifest['version'] ?? '');
        // 4.2.6 introduced Joomla 5 support. Limit this edition to reviewed stable
        // VM4 branches; do not admit development branches or prerelease suffixes.
        if (!preg_match('/^4\.(?:2|4|6|8)\.\d+(?:\.\d+)?$/D', $version)
            || version_compare($version, '4.2.6', '<')
            || !is_file(JPATH_ROOT . '/components/com_virtuemart/views/invoice/tmpl/mail_html.php')) {
            Factory::getApplication()->enqueueMessage('Install VirtueMart 4.2.6 or later in the 4.2, 4.4, 4.6 or 4.8 series before installing VM Mail & Invoice Studio.', 'error');
            return false;
        }
        return true;
    }

    public function uninstall($parent): bool
    {
        require_once __DIR__ . '/src/Service/Overrides.php';
        $service = \ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Service\Overrides::class;
        foreach ($service::templates() as $template) {
            try {
                if (!$service::remove($template)) {
                    Factory::getApplication()->enqueueMessage('Studio preserved modified template overrides in ' . htmlspecialchars($template, ENT_QUOTES, 'UTF-8') . '. They fall back to VirtueMart after uninstall.', 'warning');
                }
            } catch (\RuntimeException $error) {
                Factory::getApplication()->enqueueMessage('Studio could not remove an override in ' . htmlspecialchars($template, ENT_QUOTES, 'UTF-8') . '. Review this template manually.', 'warning');
            }
        }
        return true;
    }
}
