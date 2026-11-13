<?php
/**
 * @package VM Mail & Invoice Studio - Example Design Pack
 * @copyright Copyright (C) 2026 Darko Fatur / ToPowerYou
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Contract\DesignPackInterface;
use ToPowerYou\Plugin\Vmstudiodesign\Studioexample\Extension\Studioexample;

return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(PluginInterface::class, static function (Container $container) {
            $dispatcher = $container->get(DispatcherInterface::class);
            $config = (array) PluginHelper::getPlugin('vmstudiodesign', 'studioexample');
            $contract = JPATH_ADMINISTRATOR . '/components/com_vmmailinvoicestudio/src/Contract/DesignPackInterface.php';
            if (is_file($contract)) {
                require_once $contract;
            }
            // A removed or incompatible core must not break Joomla's plugin manager.
            // Only load the implementing class after its interface is available.
            $compatible = interface_exists(DesignPackInterface::class, false)
                && defined(DesignPackInterface::class . '::API_VERSION')
                && DesignPackInterface::API_VERSION === 1;
            $plugin = $compatible ? new Studioexample($dispatcher, $config)
                : new class($dispatcher, $config) extends CMSPlugin {};
            $plugin->setApplication(Factory::getApplication());
            return $plugin;
        });
    }
};
