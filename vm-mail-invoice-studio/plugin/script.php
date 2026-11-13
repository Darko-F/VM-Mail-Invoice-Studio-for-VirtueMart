<?php
/**
 * @package VM Mail & Invoice Studio
 * @copyright Copyright (C) 2026 Darko Fatur / ToPowerYou
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die;


use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

final class PlgInstallerVmstudioupdateInstallerScript
{
    public function postflight(string $type, $parent): bool
    {
        if ($type === 'install') {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('installer'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('vmstudioupdate'));
            $db->setQuery($query)->execute();
        }
        return true;
    }
}
