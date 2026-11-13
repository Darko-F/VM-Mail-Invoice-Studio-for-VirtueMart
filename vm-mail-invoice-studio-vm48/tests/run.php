<?php
/** Copyright (C) 2026 ToPowerYou. @license GNU General Public License version 2 or later */
namespace Joomla\Registry {
    class Registry {
        private array $data;
        public function __construct($data) { $this->data = is_array($data) ? $data : (json_decode($data, true) ?: []); }
        public function toArray() { return $this->data; }
    }
}
namespace Joomla\CMS\Uri {
    class Uri { public static function root() { return 'https://example.test/shop/'; } }
}
namespace Joomla\CMS\Component {
    class ComponentHelper {
        public static array $data = [];
        public static function getParams($name) { return new class { public function toArray() { return ComponentHelper::$data; } }; }
    }
}
namespace Joomla\CMS {
    class Factory {
        public static function getContainer() { return new class { public function get($id) { return new class {
            public function getQuery($new) { return $this; }
            public function __call($name, $args) { return $this; }
            public function quoteName($s) { return $s; }
            public function quote($s) { return $s; }
            public function loadColumn() { return ['demo']; }
            public function loadResult() { return json_encode(\Joomla\CMS\Component\ComponentHelper::$data); }
        }; } }; }
    }
}
namespace {
    define('_JEXEC', 1);
    $root = sys_get_temp_dir() . '/vmstudio-tests-' . bin2hex(random_bytes(5));
    define('JPATH_ROOT', $root);
    define('JPATH_ADMINISTRATOR', $root . '/administrator');
    mkdir($root . '/images', 0755, true);
    mkdir($root . '/templates/demo', 0755, true);
    mkdir($root . '/components/com_virtuemart/views/invoice/tmpl', 0755, true);
    $project = dirname(__DIR__);
    require $project . '/component/admin/src/Service/Design.php';
    require $project . '/component/admin/src/Service/Overrides.php';
    require $project . '/plugin/src/Service/DownloadUrl.php';
    use ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Service\Design;
    use ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Service\Overrides;
    use ToPowerYou\Plugin\Installer\Vmstudioupdate\Service\DownloadUrl;
    use Joomla\CMS\Component\ComponentHelper;
    $count = 0;
    function check(bool $ok, string $message): void { global $count; ++$count; if (!$ok) { throw new \RuntimeException($message); } }
    function rejects(callable $action, string $message): void {
        try { $action(); } catch (\InvalidArgumentException | \RuntimeException $e) { check(true, $message); return; }
        check(false, $message);
    }
    try {
        $base = Design::defaults();
        check(Design::clean($base) === $base, 'Default round trip');
        foreach (['#fff', 'red', '#123456;display:none', '<script>'] as $colour) {
            rejects(fn() => Design::clean(array_replace($base, ['accent'=>$colour])), 'Reject CSS injection');
        }
        rejects(fn() => Design::clean(['brand'=>['bad']]), 'Reject array input');
        rejects(fn() => Design::clean(['email_preset'=>'../evil']), 'Reject arbitrary preset path');
        foreach (['https://example.org/logo.png','../logo.png','images/../../secret.png','images/missing.png'] as $logo) {
            rejects(fn() => Design::clean(['logo'=>$logo]), 'Reject invalid logo');
        }
        file_put_contents($root . '/images/fake.png', 'Not a PNG');
        rejects(fn() => Design::clean(['logo'=>'images/fake.png']), 'Reject non-image payload');
        // A known transparent one-pixel GIF, represented as bytes without encoded executable content.
        file_put_contents($root . '/images/logo.gif', hex2bin('47494638396101000100800000000000ffffff21f90401000000002c00000000010001000002024401003b'));
        check(Design::logoPath('images/logo.gif') !== null, 'Valid local logo');
        symlink('/etc/passwd', $root . '/images/outside.png');
        check(Design::logoPath('images/outside.png') === null, 'Reject image symlink outside images');
        $settings = array_replace($base, ['brand'=>'<b>Shop & Sons</b>', 'email_intro'=>'<script>alert(1)</script>', 'logo'=>'images/logo.gif']);
        $html = Design::sample('email', $settings);
        check(!str_contains($html, '<script>'), 'Preview text escaped');
        check(str_contains($html, '&lt;b&gt;Shop &amp; Sons&lt;/b&gt;'), 'Brand escaped');
        check(str_contains($html, 'https://example.test/shop/images/logo.gif'), 'Absolute email logo with site subdirectory');
        $pdfHtml = Design::sample('invoice', $settings, true);
        check(str_contains($pdfHtml, $root . '/images/logo.gif'), 'PDF uses local logo path');
        check(!str_contains($pdfHtml, '<html>'), 'PDF returns fragment');
        $url = 'https://shop.topoweryou.com/index.php?option=com_vmupdatekeymanager&task=download.get&file=pkg_vmmailinvoicestudio_vm48_v1.0.1.zip&channel=update';
        $key = str_repeat('a',64);
        $vm5Url = str_replace('pkg_vmmailinvoicestudio_vm48_v', 'pkg_vmmailinvoicestudio_v', $url);
        check(DownloadUrl::authorise($vm5Url, $key, 'example.test') === $vm5Url, 'VM4 edition never attaches its key to a VM5 package');
        $authorised = DownloadUrl::authorise($url, $key, 'Example.test');
        check(str_contains($authorised, 'key=' . $key) && str_contains($authorised,'domain=example.test'), 'Key and domain added');
        foreach (['http://shop.topoweryou.com', 'https://evil.test', 'https://shop.topoweryou.com.evil.test', 'https://user@shop.topoweryou.com', 'https://shop.topoweryou.com:8443'] as $origin) {
            $other = str_replace('https://shop.topoweryou.com', $origin, $url);
            check(DownloadUrl::authorise($other, $key, 'example.test') === $other, 'No key disclosure to other origins');
        }
        foreach (['file=pkg_other_v1.0.1.zip','file=../pkg_vmmailinvoicestudio_vm48_v1.0.1.zip','file[]=pkg_vmmailinvoicestudio_vm48_v1.0.1.zip'] as $file) {
            $other = preg_replace('/file=[^&]+/', $file, $url);
            check(DownloadUrl::authorise($other, $key, 'example.test') === $other, 'Reject other packages and path traversal');
        }
        $other = str_replace('download.get','purchase.get',$url);
        check(DownloadUrl::authorise($other,$key,'example.test') === $other,'Reject other task');
        check(DownloadUrl::authorise($url,'','example.test') === $url,'No key no mutation');
        check(str_contains(DownloadUrl::authorise($url.'&key='.$key,'','example.test'),'domain=example.test'),'Joomla update-site key supported');
        $packUrl = str_replace('pkg_vmmailinvoicestudio_vm48_v1.0.1.zip', 'plg_vmstudiodesign_editorial_v1.0.1.zip', $url);
        $packKey = str_repeat('b', 64);
        check(DownloadUrl::authorise($packUrl, $key, 'example.test') === $packUrl, 'Never send core key to pack');
        $packDownload = DownloadUrl::authorise($packUrl . '&key=' . $packKey, $key, 'example.test');
        check(str_contains($packDownload, 'key=' . $packKey) && !str_contains($packDownload, 'key=' . $key)
            && str_contains($packDownload, 'domain=example.test'), 'Independent pack key retained with domain');
        check(str_contains(DownloadUrl::authorise($packUrl . '&dlid=' . $packKey, $key, 'example.test'), 'dlid=' . $packKey), 'Pack dlid supported');
        rejects(fn()=>DownloadUrl::authorise($packUrl . '&key[]=bad', $key, 'example.test'), 'Reject malformed pack key');
        $foreignPack = str_replace('shop.topoweryou.com', 'evil.test', $packUrl) . '&key=' . $packKey;
        check(DownloadUrl::authorise($foreignPack, $key, 'example.test') === $foreignPack, 'Pack credentials never redirected to other origins');
        rejects(fn()=>DownloadUrl::authorise($url,'short','example.test'),'Reject invalid key');
        rejects(fn()=>DownloadUrl::authorise($url,$key,"example.test\n"),'Reject invalid domain');
        check(Overrides::status('demo') === 'COM_VMMAILINVOICESTUDIO_NOT_APPLIED','Initial status');
        Overrides::apply('demo');
        check(Overrides::status('demo') === 'COM_VMMAILINVOICESTUDIO_ACTIVE','Applied status');
        Overrides::apply('demo');
        check(Overrides::status('demo') === 'COM_VMMAILINVOICESTUDIO_ACTIVE','Idempotent apply');
        rejects(fn()=>Overrides::apply('../demo'),'Reject template traversal');
        $dir = $root.'/templates/demo/html/com_virtuemart/invoice';
        foreach (['mail_html','invoice'] as $layout) {
            exec(escapeshellarg(PHP_BINARY).' -n -l '.escapeshellarg($dir.'/'.$layout.'.php'),$output,$code);
            check($code === 0,'Generated wrapper syntax');
        }
        file_put_contents($dir.'/invoice.php',"<?php // third party\n");
        rejects(fn()=>Overrides::apply('demo'),'Do not overwrite modified override');
        check(!Overrides::remove('demo'),'Report preserved modified override');
        check(file_get_contents($dir.'/invoice.php') === "<?php // third party\n",'Preserve modified file');
        unlink($dir.'/invoice.php');
        file_put_contents($dir.'/bs5-invoice.php','existing');
        rejects(fn()=>Overrides::apply('demo'),'Detect bootstrap override precedence');
        unlink($dir.'/bs5-invoice.php');
        Overrides::apply('demo');
        check(Overrides::remove('demo'),'Remove own wrappers');
        $view = new class {
            public string $recipient = 'shopper';
            public string $doctype = 'invoice';
            public bool $isPdf = false;
            public object $vendor;
            public function __construct() { $this->vendor=(object)['vendor_store_name'=>'Vendor']; }
            public function loadTemplate($part) { return '['.$part.']'; }
        };
        ComponentHelper::$data = array_replace($base,['email_enabled'=>1]);
        ob_start(); Design::render($view,'email'); $rendered=ob_get_clean();
        foreach (['header','shopper','shopperaddresses','pricelist','shopper_more','footer'] as $part) {
            check(str_contains($rendered,'['.$part.']'),'Preserve native mail section '.$part);
        }
        $view->recipient='vendor';
        ob_start(); Design::render($view,'email'); $rendered=ob_get_clean();
        check(!str_contains($rendered,'[header]') && str_contains($rendered,'[vendor_more]'),'Vendor routing preserved');
        file_put_contents($root.'/components/com_virtuemart/views/invoice/tmpl/invoice.php','<?php echo "native-".$this->doctype;');
        ComponentHelper::$data = $base;
        ob_start(); Design::render($view,'invoice'); $rendered=ob_get_clean();
        check($rendered === 'native-invoice','Disabled design exact native fallback');
        ComponentHelper::$data = array_replace($base,['invoice_enabled'=>1]);
        $view->doctype='deliverynote';
        ob_start(); Design::render($view,'invoice'); $rendered=ob_get_clean();
        check($rendered === 'native-deliverynote','Delivery note native fallback');
        $previewDir=$project.'/previews'; if (!is_dir($previewDir)) mkdir($previewDir);
        foreach (['classic','modern'] as $preset) {
            foreach (['email','invoice'] as $kind) {
                $s=array_replace($base,[$kind.'_preset'=>$preset,'brand'=>'North & Pine','email_intro'=>'Thank you for shopping with us. Your order details are below.','email_closing'=>'Questions about your order? Reply to this email.','invoice_closing'=>'Thank you for your business.']);
                file_put_contents($previewDir.'/'.$kind.'-'.$preset.'.html',Design::sample($kind,$s));
            }
        }
        echo "PASS: $count assertions\n";
    } finally {
        $iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST);
        foreach($iterator as $file) { if ($file->isDir() && !$file->isLink()) rmdir($file->getPathname()); else unlink($file->getPathname()); }
        rmdir($root);
    }
}
