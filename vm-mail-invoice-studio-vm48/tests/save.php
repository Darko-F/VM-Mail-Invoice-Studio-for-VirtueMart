<?php
/** Copyright (C) 2026 ToPowerYou. @license GNU General Public License version 2 or later */
// Exercise the actual save controller with persistent and per-request cache doubles.
namespace Joomla\Registry {
    class Registry {
        private array $data;
        public function __construct($data = []) { $this->data = is_array($data) ? $data : (json_decode($data, true) ?: []); }
        public function toArray() { return $this->data; }
        public function set($key, $value) { $this->data[$key] = $value; }
        public function toString() { return json_encode($this->data); }
    }
}
namespace Joomla\CMS\Component {
    class ComponentHelper {
        public static ?object $record = null;
        public static function getComponent($name) {
            return self::$record ??= new class {
                private $params;
                public function __construct() {
                    $this->params = new \Joomla\Registry\Registry(\State::$cache ??= \State::$db);
                }
                public function getParams() { return $this->params; }
                public function setParams($params) { $this->params = $params; }
            };
        }
        public static function getParams($name) { return self::getComponent($name)->getParams(); }
    }
}
namespace Joomla\CMS {
    class Factory {
        public static function getContainer() { return new class {
            public function get($id) {
                if ($id === 'Joomla\\Database\\DatabaseInterface') { return new \Database; }
                if ($id !== 'Joomla\\CMS\\Cache\\CacheControllerFactoryInterface') { throw new \Exception($id); }
                return new class {
                    public function createCacheController($type, $options) {
                        if ($type !== 'callback' || $options['defaultgroup'] !== '_system') { throw new \Exception('Wrong cache'); }
                        return new class { public function clean() { \State::$cache = null; } };
                    }
                };
            }
        }; }
    }
}
namespace Joomla\CMS\Language { class Text { public static function _($s) { return $s; } } }
namespace Joomla\CMS\Router { class Route { public static function _($s, $x = true) { return $s; } } }
namespace Joomla\CMS\Session { class Session { public static function checkToken($method) { return true; } } }
namespace Joomla\CMS\MVC\Controller {
    class BaseController {
        protected $app;
        protected $input;
        public function __construct($app, $input) { $this->app = $app; $this->input = $input; }
        public function setRedirect($url) {}
    }
}
namespace {
    define('_JEXEC', 1);
    class State { public static array $db = ['email_enabled'=>0, 'invoice_enabled'=>0, 'other_setting'=>'preserved']; public static ?array $cache = null; }
    class Database {
        private string $json;
        public function getQuery($new) { return $this; }
        public function quoteName($s) { return $s; }
        public function quote($s) { return $s; }
        public function select($s) { return $this; }
        public function from($s) { return $this; }
        public function loadResult() { return json_encode(State::$db); }
        public function update($s) { return $this; }
        public function set($s) { $this->json = substr($s, strlen('params = ')); return $this; }
        public function where($s) { return $this; }
        public function setQuery($q) { return $this; }
        public function execute() { State::$db = json_decode($this->json, true, 512, JSON_THROW_ON_ERROR); }
    }
    require dirname(__DIR__) . '/component/admin/src/Service/Design.php';
    require dirname(__DIR__) . '/component/admin/src/Controller/DisplayController.php';
    use Joomla\CMS\Component\ComponentHelper;
    use ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Service\Design;
    use ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Controller\DisplayController;
    function check($ok, $message) { if (!$ok) { throw new \RuntimeException($message); } }
    $app = new class {
        public array $messages = [];
        public function isClient($s) { return true; }
        public function getIdentity() { return new class { public function authorise($a, $c) { return true; } }; }
        public function enqueueMessage($s, $type = 'message') { $this->messages[] = [$s, $type]; }
    };
    $post = new class {
        public array $data = [];
        public function get($key, $default, $filter) { return $this->data; }
    };
    $controller = new DisplayController($app, (object) ['post'=>$post]);
    // Warm the cache with disabled designs, as opening the designer does.
    check(Design::settings()['email_enabled'] === 0, 'Initial design disabled');
    foreach ([1, 0, 1] as $enabled) {
        $post->data = array_replace(Design::defaults(), ['email_enabled'=>$enabled, 'invoice_enabled'=>$enabled]);
        $controller->save();
        check(State::$db['email_enabled'] === $enabled, 'Database save');
        check(State::$db['other_setting'] === 'preserved', 'Unrelated settings preserved');
        check(Design::settings()['email_enabled'] === $enabled, 'Current request cache refreshed');
        ComponentHelper::$record = null; // Simulate redirect / next email request.
        check(Design::settings()['email_enabled'] === $enabled, 'Email setting survives redirect');
        check(Design::settings()['invoice_enabled'] === $enabled, 'Invoice setting survives redirect');
    }
    // A different Joomla client still has a green Classic design cached.
    State::$cache = ['invoice_enabled'=>0, 'invoice_preset'=>'classic', 'accent'=>'#147d73'];
    ComponentHelper::$record = null;
    ComponentHelper::getParams('com_vmmailinvoicestudio');
    $post->data = array_replace(Design::defaults(), ['invoice_enabled'=>1, 'invoice_preset'=>'modern', 'accent'=>'#ff8800']);
    $controller->save();
    // Simulate another client returning its stale component cache after the save.
    State::$cache = ['invoice_enabled'=>0, 'invoice_preset'=>'classic', 'accent'=>'#147d73'];
    ComponentHelper::$record = null;
    check(ComponentHelper::getParams('com_vmmailinvoicestudio')->toArray()['invoice_enabled'] === 0, 'Stale cache reproduced');
    $fresh = Design::settings();
    check($fresh['invoice_enabled'] === 1 && $fresh['invoice_preset'] === 'modern' && $fresh['accent'] === '#ff8800', 'Documents bypass stale client caches');
    $html = Design::frame('Invoice body', 'invoice', $fresh, 'Test shop', true);
    check(str_contains($html, 'background-color:#ff8800;color:#ffffff'), 'Modern PDF uses saved orange banner');
    $before = State::$db;
    $post->data['accent'] = 'invalid';
    $controller->save();
    check(State::$db === $before, 'Invalid data does not change saved settings');
    check(end($app->messages)[1] === 'error', 'Validation failure reported');
    // Removing a pack must not erase its saved selection during unrelated branding edits.
    State::$db['invoice_preset'] = 'absent.editorial';
    $post->data = array_replace(Design::defaults(), ['invoice_preset'=>'absent.editorial', 'brand'=>'New branding']);
    $controller->save();
    check(State::$db['invoice_preset'] === 'absent.editorial' && State::$db['brand'] === 'New branding', 'Save retains unavailable pack selection');
    $post->data['invoice_preset'] = 'arbitrary.unknown';
    $controller->save();
    check(State::$db['invoice_preset'] === 'absent.editorial', 'Save rejects new unknown pack ID');
    $post->data['invoice_preset'] = 'modern';
    $controller->save();
    check(State::$db['invoice_preset'] === 'modern', 'Explicit core selection replaces unavailable pack');
    echo "PASS: save, enable/disable, cache reload, settings preservation and invalid input\n";
}
