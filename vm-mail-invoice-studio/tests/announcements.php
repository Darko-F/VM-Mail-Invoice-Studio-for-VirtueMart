<?php
/** Copyright (C) 2026 ToPowerYou. @license GNU General Public License version 2 or later */
namespace Joomla\CMS\Http {
    class HttpFactory {
        public static int $calls = 0;
        public static string $url = '';
        public static int $code = 200;
        public static string $body = '';
        public static bool $fail = false;
        public static function getHttp($options) {
            if (($options['follow_location'] ?? true) !== false) { throw new \RuntimeException('Redirects must be disabled'); }
            return new self;
        }
        public function get($url, $headers, $timeout) {
            self::$calls++;
            self::$url = $url;
            if (self::$fail) { throw new \RuntimeException('Shop unavailable'); }
            return (object) ['code' => self::$code, 'body' => self::$body];
        }
    }
}
namespace Joomla\CMS\Component {
    class ComponentHelper {
        public static array $params = [];
        public static function getParams($component) {
            return new class {
                public function get($key, $default = null) { return ComponentHelper::$params[$key] ?? $default; }
            };
        }
    }
}
namespace Joomla\CMS {
    class Factory {
        public static array $cleanedGroups = [];
        public static bool $cleanResult = true;
        public static function getContainer() {
            return new class {
                public function get($class) {
                    if ($class === 'Joomla\\Database\\DatabaseInterface') {
                        return new class {
                            public function getQuery($new) { return $this; }
                            public function __call($name, $args) { return $this; }
                            public function quoteName($name) { return $name; }
                            public function quote($name) { return $name; }
                            public function loadObjectList() { return [(object) ['element'=>'studioinvoicecollection', 'manifest_cache'=>'{"version":"0.9"}']]; }
                        };
                    }
                    return new class {
                        public function createCacheController($type, $options) {
                            if ($options['lifetime'] !== 60) { throw new \RuntimeException('Cache lifetime is in minutes'); }
                            return new class {
                                public function setCaching($enabled) {}
                                public function clean($group) { \Joomla\CMS\Factory::$cleanedGroups[] = $group; return \Joomla\CMS\Factory::$cleanResult; }
                                public function get($callback, $args) { return call_user_func_array($callback, $args); }
                            };
                        }
                    };
                }
            };
        }
    }
}
namespace {
    define('_JEXEC', 1);
    require dirname(__DIR__) . '/component/admin/src/Service/Announcements.php';
    require dirname(__DIR__) . '/component/admin/src/Service/DesignCatalog.php';
    use ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Service\Announcements;
    use Joomla\CMS\Http\HttpFactory;
    use Joomla\CMS\Component\ComponentHelper;
    $count = 0;
    function check(bool $condition, string $message): void {
        global $count;
        if (!$condition) { throw new \RuntimeException($message); }
        $count++;
    }
    $edition = Announcements::EDITION;
    $article = file_get_contents(dirname(__DIR__, 2) . '/article.html');
    $content = Announcements::parseArticle($article, $edition);
    check(count($content['packs']) === 1, 'One matching edition from shared article');
    check(str_contains($content['banner'], 'Pink &amp; Teal'), 'Unicode/escaped banner text preserved');
    check(!str_contains($content['banner'], '"schema"'), 'JSON is not displayed');
    check(!str_contains($content['banner'], 'vmstudio-data'), 'JSON block removed');
    check(Announcements::parseArticle($article, 'other')['packs'] === [], 'Other edition excluded');
    $bannerOnly = Announcements::parseArticle('<!doctype html><html><body><nav>Shop menu</nav><div class="com-content-article__body"><article><h1>News Čč Šš Žž</h1><p>Latest offer</p></article></div></body></html>', $edition);
    check(str_contains($bannerOnly['banner'], 'Čč Šš Žž'), 'UTF-8 banner from standard Joomla article');
    check(!str_contains($bannerOnly['banner'], 'Shop menu'), 'Shop navigation excluded');
    check($bannerOnly['packs'] === [], 'Banner without metadata supported');
    $dirty = '<article id="vmstudio-banner"><p onclick="alert(1)" style="position:fixed">Safe <strong>offer</strong></p><script>alert(1)</script><style>body{display:none}</style><iframe src="https://evil.test"></iframe><svg onload="alert(1)"><text>evil</text></svg><form><input value="secret">FORM</form><a href="javascript:alert(1)">Bad link</a><a href="&#106;avascript:alert(2)">Encoded</a><a href="/buy?x=1&amp;y=2" onclick="evil()">Buy</a><img src="/images/banner.png" onerror="evil()" alt="Banner"><img src="data:text/html,evil"></article>';
    $clean = Announcements::parseArticle($dirty, $edition)['banner'];
    foreach (['onclick', 'onerror', 'position:fixed', '<script', '<style', '<iframe', '<svg', '<form', 'javascript:', 'data:', 'FORM'] as $bad) {
        check(!str_contains($clean, $bad), 'Strip active content: ' . $bad);
    }
    check(str_contains($clean, '<strong>offer</strong>'), 'Keep safe formatting');
    check(str_contains($clean, 'https://shop.topoweryou.com/buy?x=1&amp;y=2'), 'Resolve and escape relative article links');
    check(str_contains($clean, 'https://shop.topoweryou.com/images/banner.png'), 'Resolve article images');
    check(str_contains($clean, 'rel="noopener noreferrer"'), 'Safe external link attributes');
    check(Announcements::parseArticle('<html><body>Login page</body></html>', $edition) === ['banner'=>'', 'packs'=>[]], 'No arbitrary page body fallback');
    check(Announcements::parseArticle(str_repeat('x', 262145), $edition)['banner'] === '', 'Article size limit');
    check(Announcements::parseArticle('<!DOCTYPE html [<!ENTITY x SYSTEM "file:///etc/passwd">]>' . $article, $edition)['banner'] === '', 'Reject entity declarations');
    $duplicate = Announcements::parseArticle('<article><p>Banner</p><pre id="vmstudio-data">SECRET1</pre><pre id="vmstudio-data">SECRET2</pre></article>', $edition);
    check($duplicate['packs'] === [] && !str_contains($duplicate['banner'], 'SECRET'), 'Duplicate JSON blocks are removed and ignored');
    $invalidJson = preg_replace('/"schema": 1/', '"schema": 99', $article);
    $parsed = Announcements::parseArticle($invalidJson, $edition);
    check($parsed['packs'] === [] && $parsed['banner'] !== '', 'Invalid metadata preserves banner');
    check(Announcements::parseData('{bad', $edition) === [], 'Malformed JSON');
    check(Announcements::parseData(str_repeat('x', 131073), $edition) === [], 'JSON size limit');
    $pack = $content['packs'][0];
    $pack['edition'] = $edition;
    foreach (['element'=>'../other', 'version'=>'latest', 'min_core'=>'any', 'url'=>'javascript:evil()', 'name'=>['invalid']] as $key=>$value) {
        $badPack = $pack; $badPack[$key] = $value;
        check(Announcements::parseData(json_encode(['schema'=>1,'packs'=>[$badPack]]), $edition) === [], 'Invalid metadata field: ' . $key);
    }
    check(count(Announcements::parseData(json_encode(['schema'=>1,'packs'=>[$pack,$pack]]), $edition)) === 1, 'Duplicate plugin entries ignored');
    foreach (['http://example.test', 'https://user:secret@example.test', "https://example.test/\nevil", 'https://example.test\\evil'] as $url) {
        check(!Announcements::safeUrl($url), 'Unsafe source or link URL');
    }
    check(Announcements::safeUrl(Announcements::ARTICLE_URL), 'Default article URL is valid');
    check(Announcements::withInstalled([$pack], [])[0]['status'] === 'new', 'Absent pack');
    check(Announcements::withInstalled([$pack], [$pack['element']=>'0.9'])[0]['status'] === 'update', 'Older installed pack');
    check(Announcements::withInstalled([$pack], [$pack['element']=>'1.0'])[0]['status'] === 'current', 'Matching installed pack');
    check(Announcements::withInstalled([$pack], [$pack['element']=>'9.0'])[0]['status'] === 'current', 'Newer installed pack');
    $pack['min_core'] = '9.0';
    check(Announcements::withInstalled([$pack], [])[0]['status'] === 'requires_core', 'Core compatibility takes priority');
    HttpFactory::$body = $article;
    check(count(Announcements::fetchArticle(Announcements::ARTICLE_URL, $edition)['packs']) === 1, 'Fetch parses article');
    HttpFactory::$code = 302;
    check(Announcements::fetchArticle(Announcements::ARTICLE_URL, $edition)['banner'] === '', 'Redirect does not import unintended content');
    HttpFactory::$code = 200;
    HttpFactory::$fail = true;
    check(Announcements::fetchArticle(Announcements::ARTICLE_URL, $edition)['banner'] === '', 'Network failure is harmless');
    HttpFactory::$fail = false;
    ComponentHelper::$params = ['announcements_enabled'=>0];
    $calls = HttpFactory::$calls;
    check(Announcements::content()['banner'] === '' && HttpFactory::$calls === $calls, 'Disabled announcements make no request');
    ComponentHelper::$params = ['announcements_url'=>'https://shop.topoweryou.com/files/announcements/announcements-' . $edition . '.xml'];
    $result = Announcements::content();
    check(HttpFactory::$url === Announcements::ARTICLE_URL, 'Previous default URL migrates to article');
    check($result['packs'][0]['status'] === 'update', 'Installed version compared after cached article load');

    $styled = Announcements::parseArticle('<article id="vmstudio-banner" style="background-color:#123456;color:#ffffff;padding:24px;border-radius:12px;position:fixed;z-index:999"><h2 style="color:rgb(255, 210, 120);font-size:24px;text-align:center">Designed title</h2><div style="display:flex;gap:12px"><span style="font-weight:700;font-style:italic">Styled text</span><a style="background:#147d73;color:white;padding:8px 16px;border:1px solid #123456" href="/buy">Buy</a></div><p style="background:url(https://evil.test/pixel);color:expression(alert(1));position:absolute">Unsafe CSS removed</p></article>', $edition)['banner'];
    foreach (['background-color:#123456', 'color:#ffffff', 'padding:24px', 'border-radius:12px', 'color:rgb(255, 210, 120)', 'font-size:24px', 'text-align:center', 'display:flex', 'gap:12px', 'font-weight:700', 'font-style:italic', 'background:#147d73', 'border:1px solid #123456'] as $expected) {
        check(str_contains($styled, $expected), 'Preserve article style: ' . $expected);
    }
    foreach (['position:', 'z-index:', 'url(', 'expression(', 'evil.test'] as $unsafe) {
        check(!str_contains($styled, $unsafe), 'Reject unsafe article style: ' . $unsafe);
    }
    $cachedGroup = 'com_vmmailinvoicestudio_announcements_article_v2';
    check(str_contains(file_get_contents(dirname(__DIR__) . '/component/admin/src/Service/Announcements.php'), $cachedGroup), 'Styled article uses a new cache namespace');
    $template = file_get_contents(dirname(__DIR__) . '/component/admin/tmpl/studio.php');
    check(strpos($template, 'vms-news-banner') > strpos($template, 'vms-footnote'), 'Banner is below component content');
    check(strpos($template, 'foreach ($announcements') > strpos($template, 'vms-footnote'), 'Add-on version notices are below component content');

    Announcements::clearCache();
    check(\Joomla\CMS\Factory::$cleanedGroups === [Announcements::CACHE_GROUP], 'Refresh clears only the Studio article cache');
    \Joomla\CMS\Factory::$cleanResult = false;
    $failed = false;
    try { Announcements::clearCache(); } catch (\RuntimeException $error) { $failed = true; }
    check($failed, 'Cache cleanup failure is reported');
    check(str_contains($template, 'name="task" value="refreshNews"'), 'Refresh action is available even if banner is empty');
    $cards = Announcements::parseArticle('<div class="com-content-article__body"><section class="mb-4"><h2 class="h3">Four designs included</h2><div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4 mb-4"><div class="col"><article class="card h-100"><div class="card-body"><h3 class="h5">Minimal Signature</h3><figure class="figure d-block mb-0"><img class="figure-img img-fluid w-100 rounded" src="/images/virtuemart/product/minimal-signiture.png" onerror="evil()"><figcaption class="figure-caption">Caption</figcaption></figure><p class="position-fixed modal text-white">Safe text</p></div></article></div></div></section></div>', $edition)['banner'];
    foreach (['class="mb-4"', 'class="h3"', 'class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4 mb-4"', 'class="col"', 'class="card h-100"', 'class="card-body"', 'class="h5"', '<figure class="figure d-block mb-0"', 'class="figure-img img-fluid w-100 rounded"', '<figcaption class="figure-caption">Caption</figcaption>', 'https://shop.topoweryou.com/images/virtuemart/product/minimal-signiture.png'] as $expected) {
        check(str_contains($cards, $expected), 'Article cards preserve: ' . $expected);
    }
    check(!str_contains($cards, 'position-fixed') && !str_contains($cards, 'modal') && !str_contains($cards, 'onerror'), 'Unsafe/global admin classes and events are excluded');
    $lightCard = Announcements::parseArticle('<article id="vmstudio-banner"><article class="card h-100 bg-light"><div class="card-body"><h3 class="h5">Light card</h3><p>Readable content</p></div></article></article>', $edition)['banner'];
    check(str_contains($lightCard, 'class="card h-100 bg-light"'), 'Preserve bg-light on article cards');
    if (isset($argv[1])) {
        $live = Announcements::parseArticle(file_get_contents($argv[1]), $edition);
        check(str_contains($live['banner'], 'Template Pack'), 'Captured public article displays as banner');
        check(!str_contains($live['banner'], '<script'), 'Captured public article excludes scripts');
    }
    echo "PASS: $count article, JSON, sanitization, status and fetch assertions ($edition)\n";
}
