<?php
/** Copyright (C) 2026 ToPowerYou. @license GNU General Public License version 2 or later */
namespace Joomla\Registry {
    class Registry {
        private array $data;
        public function __construct($data) { $this->data = json_decode($data, true) ?: []; }
        public function toArray() { return $this->data; }
    }
}
namespace Joomla\CMS\Plugin { abstract class CMSPlugin {} }
namespace Joomla\CMS {
    class Factory {
        public static array $params = [];
        public static function getContainer() { return new class {
            public function get($id) { return new class {
                public function __call($name, $args) { return $this; }
                public function quoteName($s) { return $s; }
                public function quote($s) { return $s; }
                public function loadResult() { return json_encode(Factory::$params); }
            }; }
        }; }
    }
}
namespace {
    define('_JEXEC', 1);
    define('JPATH_ROOT', $argv[2]);
    define('VMPATH_ROOT', JPATH_ROOT);
    define('VMPATH_TCPDF', dirname($argv[1]));
    define('DS', DIRECTORY_SEPARATOR);
    require $argv[1];
    require dirname(__DIR__) . '/component/admin/src/Service/Design.php';
    use ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Service\Design;
    use ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Service\Invoice;
    function check($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
    function vmError($message) {}
    function vmdebug(...$args) {}
    class VmConfig { public static function loadConfig() {} }
    class JFactory { public static function getDocument() { return vmFactory::getDocument(); } }
    class vmDefines { public static function tcpdf() {} }
    class JFile { public static function exists($file) { return file_exists($file); } }
    class vFile { public static function exists($file) { return file_exists($file); } }
    class vmVersion { public static $RELEASE = '4.8.0'; }
    class vmText {
        public static function _($key) { return [
            'COM_VIRTUEMART_DATE'=>'Date', 'COM_VIRTUEMART_ORDER_COMMENT'=>'Comment', 'COM_VIRTUEMART_ORDER_HISTORY'=>'Order history',
            'COM_VIRTUEMART_USER_FORM_NAME'=>'Name', 'COM_VIRTUEMART_ADDRESS'=>'Address',
            'COM_VIRTUEMART_INVOICE'=>'Invoice', 'COM_VIRTUEMART_CREDIT_NOTE'=>'Credit note',
            'COM_VIRTUEMART_INVOICE_DATE'=>'Invoice date', 'COM_VIRTUEMART_DELIVERY_DATE'=>'Delivery date',
            'COM_VIRTUEMART_ORDER_PRINT_PO_NUMBER'=>'Order number', 'COM_VIRTUEMART_ORDER_PRINT_PO_DATE'=>'Order date',
            'COM_VIRTUEMART_ORDER_PRINT_PO_STATUS'=>'Status', 'COM_VIRTUEMART_ORDER_PRINT_SHIPMENT_LBL'=>'Shipment',
            'COM_VIRTUEMART_ORDER_PRINT_PAYMENT_LBL'=>'Payment', 'COM_VIRTUEMART_ORDER_PRINT_CUSTOMER_NOTE'=>'Note',
            'COM_VIRTUEMART_ORDER_PRINT_TOTAL'=>'Total', 'COM_VIRTUEMART_ORDER_PRINT_BILL_TO_LBL'=>'Billing details',
            'COM_VIRTUEMART_ORDER_PRINT_SHIP_TO_LBL'=>'Ship to', 'COM_VM_ST_SAME_AS_BT'=>'Same as billing address',
        ][$key] ?? $key; }
        public static function sprintf($key, ...$args) { return self::_($key); }
    }
    class vmJsApi { public static function css($s) {} public static function date($date, ...$args) { return $date; } }
    class vmFactory { public static function getDocument() { return new class { public function setTitle($title) {} }; } }
    class shopFunctionsF {
        public static function renderVendorAddress(...$args) { return 'Ajdovščina Čč Šš Žž'; }
        public static function buildTabs($view, $tabs) {
            echo '<div id="ui-tabs">';
            $i = 1;
            foreach ($tabs as $part => $title) {
                echo '<div id="tab-' . $i++ . '" class="tabs" title="' . vmText::_($title) . '">' . $view->loadTemplate($part) . '<div class="clear"></div></div>';
            }
            echo '</div>';
        }
    }
    class VmModel {
        public static function getModel($name) { return new self; }
        public function getVendor($id) { return (object) [
            'vendor_store_name'=>'Test shop', 'vendor_store_desc'=>'', 'vendor_legal_info'=>'', 'vendor_terms_of_service'=>'Terms preserved',
            'virtuemart_vendor_id'=>1, 'vendor_letter_font'=>'helvetica', 'vendor_letter_font_size'=>10,
            'vendor_letter_header_font_size'=>9, 'vendor_letter_footer_font_size'=>8,
            'vendor_letter_format'=>'A4', 'vendor_letter_orientation'=>'P',
            'vendor_letter_header'=>1, 'vendor_letter_footer'=>1,
            'vendor_letter_header_html'=>'<strong>Header: Ajdovščina Čč Šš Žž</strong>',
            'vendor_letter_footer_html'=>'Footer: Ajdovščina Čč Šš Žž',
            'vendor_letter_css'=>'', 'vendor_letter_header_image'=>0,
            'vendor_letter_header_line'=>0, 'vendor_letter_footer_line'=>0,
            'vendor_letter_header_line_color'=>'#000000', 'vendor_letter_footer_line_color'=>'#000000',
            'vendor_letter_header_cell_height_ratio'=>1.25, 'vendor_letter_footer_cell_height_ratio'=>1.25,
            'vendor_letter_margin_left'=>15, 'vendor_letter_margin_right'=>15, 'vendor_letter_margin_top'=>25,
            'vendor_letter_margin_header'=>10, 'vendor_letter_margin_footer'=>15, 'vendor_letter_margin_bottom'=>25,
            'vendor_letter_add_tos'=>1, 'vendor_letter_add_tos_newpage'=>0,
        ]; }
        public function addImages(...$args) {}
        public function getVendorAddressFields($id) { return ['fields'=>[]]; }
    }

    require JPATH_ROOT . '/components/com_virtuemart/helpers/vmpdf.php';
    $view = new class {
        protected $_layout = 'invoice';
        public $doctype = 'invoice', $isPdf = true, $print = false, $headFooter = false, $invoiceNumber = 'TEST-001', $invoiceDate = '2026-11-12';
        public $vendor, $pdf_formatter, $currency, $orderDetails, $userfields, $shipmentfields;
        public $user_currency_id = 1;
        public $orderstatuses = ['C'=>'Confirmed'];
        public function loadTemplate($part) {
            if ($part === 'order') {
                ob_start(); include JPATH_ROOT . '/components/com_virtuemart/views/invoice/tmpl/invoice_order.php'; return ob_get_clean();
            }
            if ($part === 'items') { return '<table class="html-email" width="100%" cellpadding="0"><tr class="sectiontableheader"><td>Product</td><td>Total</td></tr><tr><td>Product with custom field</td><td>19.47 EUR</td></tr></table>'; }
            ob_start(); include JPATH_ROOT . '/components/com_virtuemart/views/invoice/tmpl/invoice_history.php'; return ob_get_clean();
        }
    };
    $view->currency = new class { public function priceDisplay(...$args) { return '19.47 EUR'; } };
    $view->orderDetails = ['details'=>['BT'=>(object)[
        'toPay'=>19.47, 'order_total'=>19.47, 'order_number'=>'TEST-ORDER', 'created_on'=>'2026-11-12', 'order_status'=>'C',
        'delivery_date'=>'Same as invoice date', 'customer_note'=>'Long customer note stays intact with its original value.',
    ], 'has_ST'=>false], 'shipmentName'=>'Pickup', 'paymentName'=>'Cash on delivery', 'history'=>[(object)['customer_notified'=>1, 'created_on'=>'2026-11-12', 'order_status_code'=>'C', 'comments'=>'History preserved']]];
    $view->userfields = ['fields'=>[
        ['name'=>'company', 'title'=>'Company', 'value'=>'Test company'],
        ['name'=>'first_name', 'title'=>'First name', 'value'=>'Črt'], ['name'=>'last_name', 'title'=>'Last name', 'value'=>'Žagar'],
        ['name'=>'address_1', 'title'=>'Address 1', 'value'=>'Test street 15'], ['name'=>'address_2', 'title'=>'Address 2', 'value'=>'Identifier SI-TEST'],
        ['name'=>'zip', 'title'=>'Postcode', 'value'=>'5270'], ['name'=>'city', 'title'=>'City', 'value'=>'Ajdovščina Čč Šš Žž'],
        ['name'=>'email', 'title'=>'Email', 'value'=>'customer@example.test'],
        ['name'=>'custom_field', 'title'=>'Custom field', 'value'=>'Keep this value'],
    ]];
    $view->shipmentfields = ['fields'=>[['title'=>'City', 'value'=>'Shipping Ajdovščina'], ['title'=>'Address', 'value'=>'Other street 12']]];
    require dirname(__DIR__) . '/design-packs/studioexample/src/Extension/Studioexample.php';
    \ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Service\DesignCatalog::current()->register('studioexample',
        (new \ToPowerYou\Plugin\Vmstudiodesign\Studioexample\Extension\Studioexample)->getDesignPack());
    require dirname(__DIR__) . '/design-packs/studioinvoicecollection/src/Extension/Studioinvoicecollection.php';
    $collection = new \ToPowerYou\Plugin\Vmstudiodesign\Studioinvoicecollection\Extension\Studioinvoicecollection;
    \ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Service\DesignCatalog::current()->register('studioinvoicecollection', $collection->getDesignPack());
    $collectionIds = array_map(static fn($id) => 'studioinvoicecollection.' . $id, array_keys($collection->getDesignPack()['designs']));
    foreach (array_merge(['classic', 'modern', 'studioexample.editorial'], $collectionIds) as $preset) {
        $isCollection = str_starts_with($preset, 'studioinvoicecollection.');
        foreach ([false, true] as $shipping) {
            $view->orderDetails['details']['has_ST'] = $shipping;
            $view->pdf_formatter = new \VmVendorPDF();
            $view->vendor = $view->pdf_formatter->vendor;
            \Joomla\CMS\Factory::$params = array_replace(Design::defaults(), ['invoice_enabled'=>1, 'invoice_preset'=>$preset, 'accent'=>'#24559a']);
            ob_start(); Design::render($view, 'invoice'); $html = ob_get_clean();
            if ($preset === 'studioexample.editorial') {
                check(str_contains($html, 'border-left:5pt solid #24559a'), 'Pack renderer uses the actual VM invoice and saved blue accent');
            }
            check($view->pdf_formatter->getFontFamily() === 'vmsans', 'Real invoice renderer activates Unicode');
            check(str_contains($html, 'class="vms-details') && str_contains($html, 'class="vms-addresses"'), 'Native details converted');
            check(str_contains($html, $isCollection ? 'font-size:9pt' : 'font-size:8.5pt'), 'Compact detail font');
            if ($isCollection) { check(str_contains($html, 'collection-items'), 'Native invoice uses the collection content renderer'); }
            foreach (['Ajdovščina Čč Šš Žž', 'Črt', 'Žagar', 'Identifier SI-TEST', 'Keep this value', 'customer@example.test', 'Terms preserved', 'Long customer note'] as $value) {
                check(str_contains($html, $value), 'Content preserved: ' . $value);
            }
            if ($preset === 'classic') {
                check(str_contains($html, 'History preserved') && str_contains($html, 'id="tab-2"'), 'Classic retains native history');
                check(str_contains($html, $shipping ? 'Shipping Ajdovščina' : 'Same as billing address'), 'Classic shipping variant preserved');
                foreach (['Order date', 'Status', 'First name', 'Last name', 'Ship to'] as $label) {
                    check(preg_match('~>\s*' . preg_quote($label, '~') . '\s*<~u', $html), 'Classic keeps ' . $label);
                }
            } else {
                check(!str_contains($html, 'History preserved') && !str_contains($html, 'id="tab-2"'), 'Modern removes native history panel');
                check(str_contains($html, 'id="tab-1"') && str_contains($html, 'Product with custom field'), 'Modern retains native item panel');
                foreach (['Order date', 'Status', 'First name', 'Last name', 'Ship to'] as $label) {
                    check(!preg_match('~>\s*' . preg_quote($label, '~') . '\s*<~u', $html), 'Modern omits ' . $label);
                }
                check(str_contains($html, 'Črt Žagar'), 'Modern combines full name');
                check(str_contains($html, 'Test street 15, 5270 Ajdovščina'), 'Modern combines street, postcode and city');
                check(str_contains($html, '>Invoice date<'), 'Invoice date retained');
                check(!str_contains($html, 'Shipping Ajdovščina'), 'Modern shipping address hidden');
            }
            $dom = new DOMDocument(); @$dom->loadHTML($html); $xp = new DOMXPath($dom);
            foreach ($xp->query('//table[contains(concat(" ",@class," ")," vms-details ")]/tr[not(@class="vms-addresses")]') as $row) {
                check($xp->query('./td', $row)->length === 4, 'Details use four columns');
            }
            if ($preset !== 'classic') {
                $first = $xp->query('//table[contains(concat(" ",@class," ")," vms-details ")]/tr[2]/td');
                check(str_contains($first->item(0)->textContent, 'Company') && str_contains($first->item(1)->textContent, 'Test company'), 'Billing occupies the left column');
                check(str_contains($first->item(2)->textContent, 'Invoice date'), 'Invoice details occupy the right column');
            }
            $view->pdf_formatter->AddPage();
            $view->pdf_formatter->PrintContents($html);
            $path = dirname(__DIR__) . '/previews/invoice-details-' . $preset . ($shipping ? '-shipping' : '') . '.pdf';
            $view->pdf_formatter->Output($path, 'F');
            file_put_contents(substr($path, 0, -4) . '.html', $html);
            $lines = [];
            exec('pdftotext -layout ' . escapeshellarg($path) . ' -', $lines, $code);
            check($code === 0, 'Native PDF text can be extracted');
            $text = preg_replace('/\s+/u', ' ', implode("\n", $lines));
            foreach (['Header: Ajdovščina Čč Šš Žž', 'Footer: Ajdovščina Čč Šš Žž', 'Identifier SI-TEST', 'Keep this value', 'customer@example.test', 'Terms preserved', '19.47 EUR'] as $value) {
                check(str_contains($text, $value), 'Native PDF retains readable content: ' . $value);
            }

        }
    }
    $unicode = new TCPDF();
    $unicode->SetFont('freemono', 'B', 11);
    $unicode->setHeaderFont(['freemono', '', 9]);
    $unicode->setFooterFont(['freemono', '', 8]);
    Invoice::preparePdf($unicode);
    check($unicode->getFontFamily() === 'freemono' && $unicode->getFontSizePt() === 11.0, 'Existing Unicode font and size preserved');
    check(Invoice::compact('<h1>Custom</h1><table><tr><td>Custom layout</td></tr></table>') === '<h1>Custom</h1><table><tr><td>Custom layout</td></tr></table>', 'Unknown layout preserved');
    echo "PASS: native VM4 order layout, real vendor PDF, four columns, both core designs, example/collection packs and shipping variants\n";
}
