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
    public function preflight(string $type, $parent): bool
    {
        if ($type === 'uninstall') {
            return true;
        }
        if (version_compare(PHP_VERSION, '8.3.0', '<') || version_compare(JVERSION, '6.0.0', '<') || version_compare(JVERSION, '7.0.0', '>=')) {
            Factory::getApplication()->enqueueMessage('VM Mail & Invoice Studio requires Joomla 6 and PHP 8.3 or later.', 'error');
            return false;
        }
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)->select($db->quoteName('manifest_cache'))->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_virtuemart'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'));
        $manifest = json_decode((string) $db->setQuery($query)->loadResult(), true);
        $version = (string) ($manifest['version'] ?? '');
        if (!str_starts_with($version, '5.') || !is_file(JPATH_ROOT . '/components/com_virtuemart/views/invoice/tmpl/mail_html.php')) {
            Factory::getApplication()->enqueueMessage('Install VirtueMart 5 before installing VM Mail & Invoice Studio.', 'error');
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
