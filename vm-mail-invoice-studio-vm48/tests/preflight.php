<?php
/** Copyright (C) 2026 ToPowerYou. GNU GPL version 2 or later. */
namespace Joomla\CMS {
    class Factory {
        public static function getApplication() { return new class {
            public function enqueueMessage($message, $type) {}
        }; }
        public static function getContainer() { return new class {
            public function get($id) { return new class {
                public function __call($name, $args) { return $this; }
                public function quoteName($value) { return $value; }
                public function quote($value) { return $value; }
                public function loadResult() { return json_encode(['version' => $GLOBALS['argv'][2]]); }
            }; }
        }; }
    }
}
namespace {
    define('_JEXEC', 1);
    define('JVERSION', $argv[1]);
    $root = sys_get_temp_dir() . '/studio-preflight-' . bin2hex(random_bytes(6));
    $layout = $root . '/components/com_virtuemart/views/invoice/tmpl';
    mkdir($layout, 0755, true);
    define('JPATH_ROOT', $root);
    if ($argv[3] === 'present') { file_put_contents($layout . '/mail_html.php', '<?php'); }
    require dirname(__DIR__) . '/component/script.php';
    try {
        $actual = (new \Com_VmmailinvoicestudioInstallerScript())->preflight('install', null);
        if ($actual !== ($argv[4] === 'accept')) { throw new \RuntimeException('Unexpected preflight result'); }
        echo "PASS: Joomla {$argv[1]}, VM {$argv[2]}, layout {$argv[3]}: {$argv[4]}\n";
    } finally {
        if (is_file($layout . '/mail_html.php')) { unlink($layout . '/mail_html.php'); }
        for ($dir = $layout; $dir !== dirname($root); $dir = dirname($dir)) { rmdir($dir); }
    }
}
