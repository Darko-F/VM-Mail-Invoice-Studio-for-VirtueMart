<?php
/**
 * @package VM Mail & Invoice Studio
 * @copyright Copyright (C) 2026 Darko Fatur / ToPowerYou
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace ToPowerYou\Plugin\Installer\Vmstudioupdate\Extension;

defined('_JEXEC') or die;


use Joomla\CMS\Event\Installer\BeforePackageDownloadEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\SubscriberInterface;
use ToPowerYou\Plugin\Installer\Vmstudioupdate\Service\DownloadUrl;

final class Vmstudioupdate extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return ['onInstallerBeforePackageDownload' => 'beforeDownload'];
    }

    public function beforeDownload(BeforePackageDownloadEvent $event): void
    {
        $host = (string) parse_url(Uri::root(), PHP_URL_HOST);
        $event->updateUrl(DownloadUrl::authorise($event->getUrl(), (string) $this->params->get('download_key', ''), $host));
    }
}
