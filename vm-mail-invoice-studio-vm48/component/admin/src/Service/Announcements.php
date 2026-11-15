<?php
/**
 * @package VM Mail & Invoice Studio
 * @copyright Copyright (C) 2026 Darko Fatur / ToPowerYou
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Service;
defined('_JEXEC') or die;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Http\HttpFactory;
use Joomla\Database\DatabaseInterface;

final class Announcements
{
    public const EDITION = 'vm4';
    public const ARTICLE_URL = 'https://shop.topoweryou.com/?option=com_content&view=article&id=2&tmpl=component';
    public const CACHE_GROUP = 'com_vmmailinvoicestudio_announcements_article_v2';
    private const EMPTY_CONTENT = ['banner' => '', 'packs' => []];

    public static function content(): array
    {
        $params = ComponentHelper::getParams('com_vmmailinvoicestudio');
        if (!$params->get('announcements_enabled', 1)) {
            return self::EMPTY_CONTENT;
        }
        $url = trim((string) $params->get('announcements_url', self::ARTICLE_URL));
        // Migrate the previous default XML setting without altering custom URLs.
        if ($url === '' || in_array($url, [
            'https://shop.topoweryou.com/files/announcements/announcements-vm5.xml',
            'https://shop.topoweryou.com/files/announcements/announcements-vm4.xml',
        ], true)) {
            $url = self::ARTICLE_URL;
        }
        try {
            $cache = Factory::getContainer()->get(CacheControllerFactoryInterface::class)
                ->createCacheController('callback', ['defaultgroup' => self::CACHE_GROUP, 'lifetime' => 60]);
            $cache->setCaching(true);
            $content = $cache->get([self::class, 'fetchArticle'], [$url, self::EDITION]);
        } catch (\Throwable $error) {
            return self::EMPTY_CONTENT;
        }
        if ($content['packs'] === []) {
            return $content;
        }
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)->select($db->quoteName(['element', 'manifest_cache']))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('vmstudiodesign'));
            $installed = [];
            foreach ($db->setQuery($query)->loadObjectList() as $row) {
                $manifest = json_decode($row->manifest_cache, true);
                $installed[$row->element] = (string) ($manifest['version'] ?? '0');
            }
            $content['packs'] = self::withInstalled($content['packs'], $installed);
        } catch (\Throwable $error) {
            $content['packs'] = [];
        }
        return $content;
    }

    public static function clearCache(): void
    {
        $cache = Factory::getContainer()->get(CacheControllerFactoryInterface::class)
            ->createCacheController('callback', ['defaultgroup' => self::CACHE_GROUP, 'lifetime' => 60]);
        if (!$cache->clean(self::CACHE_GROUP)) {
            throw new \RuntimeException('COM_VMMAILINVOICESTUDIO_NEWS_REFRESH_FAILED');
        }
    }

    public static function withInstalled(array $packs, array $installed): array
    {
        foreach ($packs as &$pack) {
            $pack['installed'] = $installed[$pack['element']] ?? '';
            $pack['status'] = version_compare(DesignCatalog::CORE_VERSION, $pack['min_core'], '<') ? 'requires_core'
                : ($pack['installed'] === '' ? 'new' : (version_compare($pack['version'], $pack['installed'], '>') ? 'update' : 'current'));
        }
        unset($pack);
        return $packs;
    }

    public static function fetchArticle(string $url, string $edition): array
    {
        try {
            if (!self::safeUrl($url)) {
                return self::EMPTY_CONTENT;
            }
            $response = HttpFactory::getHttp(['follow_location' => false])->get($url, [], 3);
            if ((int) $response->code !== 200 || strlen($response->body) > 262144) {
                return self::EMPTY_CONTENT;
            }
            return self::parseArticle($response->body, $edition, $url);
        } catch (\Throwable $error) {
            // A shop outage must never prevent designing or sending documents.
            return self::EMPTY_CONTENT;
        }
    }

    public static function safeUrl(string $url): bool
    {
        if (preg_match('/[\x00-\x20\x7f\\\\]/', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $parts = parse_url($url);
        return is_array($parts) && ($parts['scheme'] ?? '') === 'https' && !empty($parts['host'])
            && !isset($parts['user']) && !isset($parts['pass']);
    }

    public static function parseArticle(string $body, string $edition, string $url = self::ARTICLE_URL): array
    {
        if (strlen($body) > 262144 || stripos($body, '<!ENTITY') !== false
            || preg_match('/<!DOCTYPE[^>]*(?:SYSTEM|PUBLIC|\[)/i', $body)) {
            return self::EMPTY_CONTENT;
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new \DOMDocument('1.0', 'UTF-8');
            if (!$dom->loadHTML('<?xml encoding="UTF-8">' . $body, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
                return self::EMPTY_CONTENT;
            }
            $xpath = new \DOMXPath($dom);
            // Never import the shop's navigation, login modules or other page chrome.
            $article = $xpath->query('//*[@id="vmstudio-banner"]')->item(0)
                ?? $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " com-content-article__body ")]')->item(0)
                ?? $xpath->query('//article')->item(0);
            if (!$article) {
                return self::EMPTY_CONTENT;
            }
            // JSON is inert text in a PRE: Joomla editors often strip SCRIPT blocks.
            $blocks = $xpath->query('//pre[@id="vmstudio-data"]');
            $packs = $blocks->length === 1 ? self::parseData($blocks->item(0)->textContent, $edition) : [];
            foreach (iterator_to_array($blocks) as $block) {
                $block->parentNode->removeChild($block);
            }
            return ['banner' => '<div class="vms-article-content"' . self::styleAttribute($article) . '>' . self::renderChildren($article, $url) . '</div>', 'packs' => $packs];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public static function parseData(string $json, string $edition): array
    {
        if (strlen($json) > 131072) {
            return [];
        }
        $data = json_decode($json, true, 32);
        if (!is_array($data) || ($data['schema'] ?? null) !== 1 || !isset($data['packs']) || !is_array($data['packs'])) {
            return [];
        }
        $packs = [];
        $seen = [];
        foreach (array_slice($data['packs'], 0, 100) as $entry) {
            if (!is_array($entry) || ($entry['edition'] ?? '') !== $edition) {
                continue;
            }
            $pack = [];
            foreach (['name', 'description', 'element', 'version', 'min_core', 'url'] as $field) {
                $pack[$field] = isset($entry[$field]) && is_string($entry[$field]) ? trim($entry[$field]) : '';
            }
            if (!preg_match('/^[a-z][a-z0-9_]*$/D', $pack['element']) || $pack['name'] === ''
                || !preg_match('/^\d+\.\d+(?:\.\d+)?$/D', $pack['version'])
                || !preg_match('/^\d+\.\d+(?:\.\d+)?$/D', $pack['min_core']) || !self::safeUrl($pack['url'])
                || isset($seen[$pack['element']])) {
                continue;
            }
            $seen[$pack['element']] = true;
            $packs[] = $pack;
            if (count($packs) >= 50) {
                break;
            }
        }
        return $packs;
    }


    /** Preserve presentation without allowing scripts, URLs, positioning or page-wide CSS. */
    private static function safeStyle(string $style): string
    {
        $color = '(?:#[0-9a-f]{3,8}|[a-z]{1,24}|(?:rgb|rgba|hsl|hsla)\([0-9.,%\s\/+-]+\))';
        $length = '(?:0|\d+(?:\.\d+)?(?:px|em|rem|%))';
        $rules = [
            'color' => $color, 'background-color' => $color, 'background' => $color,
            'font-size' => $length, 'font-weight' => '(?:normal|bold|bolder|lighter|[1-9]00)',
            'font-style' => '(?:normal|italic|oblique)',
            'font-family' => '[a-z0-9\s,\x22\x27-]+',
            'line-height' => '(?:normal|\d+(?:\.\d+)?(?:px|em|rem|%)?)',
            'text-align' => '(?:left|right|center|justify|start|end)',
            'text-decoration' => '(?:none|underline|line-through|overline)',
            'text-transform' => '(?:none|uppercase|lowercase|capitalize)',
            'letter-spacing' => '(?:normal|' . $length . ')',
            'border-radius' => $length . '(?:\s+' . $length . '){0,3}',
            'border-collapse' => '(?:collapse|separate)',
            'display' => '(?:block|inline|inline-block|flex|grid)',
            'flex-wrap' => '(?:wrap|nowrap)', 'flex-direction' => '(?:row|column)',
            'justify-content' => '(?:start|end|center|space-between|space-around|flex-start|flex-end)',
            'align-items' => '(?:start|end|center|stretch|flex-start|flex-end)',
            'gap' => $length . '(?:\s+' . $length . ')?',
        ];
        foreach (['padding', 'margin'] as $property) {
            $rules[$property] = $length . '(?:\s+' . $length . '){0,3}';
            foreach (['top', 'right', 'bottom', 'left'] as $side) {
                $rules[$property . '-' . $side] = $length;
            }
        }
        foreach (['width', 'max-width', 'min-width', 'height', 'max-height'] as $property) {
            $rules[$property] = '(?:auto|' . $length . ')';
        }
        foreach (['border', 'border-top', 'border-right', 'border-bottom', 'border-left'] as $property) {
            $rules[$property] = '(?:none|0|' . $length . '\s+(?:solid|dashed|dotted|double)\s+' . $color . ')';
            $rules[$property . '-color'] = $color;
        }
        $result = [];
        foreach (explode(';', $style) as $declaration) {
            $parts = explode(':', $declaration, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $property = strtolower(trim($parts[0]));
            $value = trim($parts[1]);
            if (strlen($value) <= 300 && isset($rules[$property]) && preg_match('~\A(?:' . $rules[$property] . ')\z~iD', $value)) {
                $result[$property] = $property . ':' . $value;
            }
        }
        return implode(';', $result);
    }

    private static function styleAttribute(\DOMElement $node): string
    {
        $style = self::safeStyle($node->getAttribute('style'));
        return $style === '' ? '' : ' style="' . htmlspecialchars($style, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
    }

    private static function classAttribute(\DOMElement $node): string
    {
        $allowed = ['row', 'col', 'row-cols-1', 'row-cols-md-2', 'row-cols-lg-4', 'g-4',
            'mb-0', 'mb-4', 'h3', 'h5', 'card', 'h-100', 'card-body', 'figure',
            'd-block', 'figure-img', 'img-fluid', 'w-100', 'rounded', 'figure-caption', 'bg-light'];
        $classes = array_values(array_intersect(preg_split('/\s+/', trim($node->getAttribute('class'))), $allowed));
        return $classes === [] ? '' : ' class="' . implode(' ', array_unique($classes)) . '"';
    }

    private static function renderChildren(\DOMNode $parent, string $url): string
    {
        $html = '';
        foreach ($parent->childNodes as $node) {
            if ($node instanceof \DOMText) {
                $html .= htmlspecialchars($node->textContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                continue;
            }
            if (!$node instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($node->tagName);
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'form', 'input', 'button', 'textarea', 'template', 'noscript', 'link', 'meta', 'base'], true)) {
                continue;
            }
            $children = self::renderChildren($node, $url);
            $style = self::classAttribute($node) . self::styleAttribute($node);
            if ($tag === 'a') {
                $href = self::resolveUrl($node->getAttribute('href'), $url);
                $html .= $href === '' ? $children : '<a' . $style . ' href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">' . $children . '</a>';
            } elseif ($tag === 'img') {
                $src = self::resolveUrl($node->getAttribute('src'), $url);
                if ($src !== '') {
                    $html .= '<img' . $style . ' src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($node->getAttribute('alt'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" loading="lazy" referrerpolicy="no-referrer">';
                }
            } elseif (in_array($tag, ['div', 'span', 'section', 'article', 'figure', 'figcaption', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'strong', 'b', 'em', 'i', 'u', 's', 'blockquote', 'code', 'pre', 'br', 'hr'], true)) {
                // Only allowlisted presentation styles are kept; event attributes are excluded.
                $tag = $tag === 'h1' ? 'h3' : $tag;
                $html .= '<' . $tag . $style . '>' . (in_array($tag, ['br', 'hr'], true) ? '' : $children . '</' . $tag . '>');
            } else {
                $html .= $children;
            }
        }
        return $html;
    }

    private static function resolveUrl(string $value, string $base): string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $value)) {
            return '';
        }
        if (str_starts_with($value, '//')) {
            $value = 'https:' . $value;
        } elseif (!preg_match('/^[a-z][a-z0-9+.-]*:/i', $value)) {
            $parts = parse_url($base);
            if (!$parts || empty($parts['host'])) {
                return '';
            }
            $origin = 'https://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
            $path = $parts['path'] ?? '/';
            if ($value[0] === '/' ) {
                $value = $origin . $value;
            } elseif ($value[0] === '?' || $value[0] === '#') {
                $value = $origin . $path . ($value[0] === '#' && isset($parts['query']) ? '?' . $parts['query'] : '') . $value;
            } else {
                $value = $origin . substr($path, 0, (int) strrpos($path, '/') + 1) . $value;
            }
        }
        return self::safeUrl($value) ? $value : '';
    }
}
