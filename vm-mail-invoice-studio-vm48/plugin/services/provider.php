<?php
/**
 * @package VM Mail & Invoice Studio
 * @copyright Copyright (C) 2026 Darko Fatur / ToPowerYou
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die;


use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use ToPowerYou\Plugin\Installer\Vmstudioupdate\Extension\Vmstudioupdate;

return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(PluginInterface::class, static function (Container $container) {
            $plugin = new Vmstudioupdate($container->get(DispatcherInterface::class), (array) PluginHelper::getPlugin('installer', 'vmstudioupdate'));
            $plugin->setApplication(Factory::getApplication());
            return $plugin;
        });
    }
};
