<?php
/**
 * @package VM Mail & Invoice Studio
 * @copyright Copyright (C) 2026 Darko Fatur / ToPowerYou
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace ToPowerYou\Plugin\Installer\Vmstudioupdate\Service;

defined('_JEXEC') or die;


final class DownloadUrl
{
    public static function authorise(string $url, string $key, string $siteHost): string
    {
        $parts = parse_url($url);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || strtolower($parts['host'] ?? '') !== 'shop.topoweryou.com'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || (isset($parts['port']) && $parts['port'] !== 443) || ($parts['path'] ?? '') !== '/index.php') {
            return $url;
        }
        parse_str($parts['query'] ?? '', $query);
        if (($query['option'] ?? '') !== 'com_vmupdatekeymanager' || ($query['task'] ?? '') !== 'download.get'
            || ($query['channel'] ?? '') !== 'update' || !is_string($query['file'] ?? null)) {
            return $url;
        }
        $core = preg_match('/^pkg_vmmailinvoicestudio_v[0-9]+\.[0-9]+\.[0-9]+\.zip$/D', $query['file']);
        $pack = preg_match('/^plg_vmstudiodesign_[a-z][a-z0-9_]*_v[0-9]+\.[0-9]+\.[0-9]+\.zip$/D', $query['file']);
        if (!$core && !$pack) {
            return $url;
        }
        // Packs have independent entitlements. Only Joomla's pack-specific Update Site may supply their key.
        $key = $core ? trim($key) : '';
        if ($key !== '') {
            if (!preg_match('/^[a-zA-Z0-9_-]{16,256}$/D', $key)) {
                throw new \InvalidArgumentException('Invalid VM Mail & Invoice Studio download key.');
            }
            $query['key'] = $key;
            unset($query['dlid']);
        }
        // Joomla may already have added the key from its Update Sites download-key field.
        $suppliedKey = $query['key'] ?? $query['dlid'] ?? '';
        if ($suppliedKey === '') {
            return $url;
        }
        if (!is_string($suppliedKey) || !preg_match('/^[a-zA-Z0-9_-]{16,256}$/D', $suppliedKey)) {
            throw new \InvalidArgumentException('Invalid Studio download key.');
        }
        if (!preg_match('/^[a-zA-Z0-9.-]+$/D', $siteHost)) {
            throw new \InvalidArgumentException('Unable to determine the Joomla site domain.');
        }
        $query['domain'] = strtolower($siteHost);
        return 'https://shop.topoweryou.com/index.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}
