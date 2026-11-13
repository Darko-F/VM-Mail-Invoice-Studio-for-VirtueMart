<?php
/**
 * @package VM Mail & Invoice Studio - Invoice Collection
 * @copyright Copyright (C) 2026 Darko Fatur / ToPowerYou
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Contract\DesignPackInterface;

final class PlgVmstudiodesignStudioinvoicecollectionInstallerScript
{
    public function preflight(string $type, $parent): bool
    {
        if ($type === 'uninstall') {
            return true;
        }
        if (version_compare(PHP_VERSION, '8.3.0', '<')
            || !defined('JVERSION') || version_compare(JVERSION, '6.0.0', '<')
            || version_compare(JVERSION, '7.0.0', '>=')) {
            return $this->fail('Studio Invoice Collection requires Joomla 6 and PHP 8.3 or later.');
        }
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)->select($db->quoteName('extension_id'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_vmmailinvoicestudio'));
        $contract = JPATH_ADMINISTRATOR . '/components/com_vmmailinvoicestudio/src/Contract/DesignPackInterface.php';
        if (!$db->setQuery($query)->loadResult() || !is_file($contract)) {
            return $this->fail('Install VM Mail & Invoice Studio 1.0.0 with design-pack support before installing this pack.');
        }
        require_once $contract;
        if (!interface_exists(DesignPackInterface::class, false)
            || !defined(DesignPackInterface::class . '::API_VERSION') || DesignPackInterface::API_VERSION !== 1) {
            return $this->fail('This pack requires VM Mail & Invoice Studio design-pack API version 1.');
        }
        $catalog = JPATH_ADMINISTRATOR . '/components/com_vmmailinvoicestudio/src/Service/DesignCatalog.php';
        if (!is_file($catalog)) {
            return $this->fail('Install Studio core 1.0.1 or later before this pack.');
        }
        require_once $catalog;
        if (version_compare(\ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Service\DesignCatalog::CORE_VERSION, '1.0.1', '<')) {
            return $this->fail('Install Studio core 1.0.1 or later before this pack.');
        }
        return true;
    }

    private function fail(string $message): bool
    {
        Factory::getApplication()->enqueueMessage($message, 'error');
        return false;
    }
}
