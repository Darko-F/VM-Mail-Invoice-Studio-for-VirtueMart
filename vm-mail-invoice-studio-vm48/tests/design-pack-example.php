<?php
/** Copyright (C) 2026 ToPowerYou. @license GNU General Public License version 2 or later */
namespace Joomla\CMS\Plugin {
    class CMSPlugin {}
    class PluginHelper {
        public static function getPlugin($group) {
            if ($group !== 'vmstudiodesign') { throw new \RuntimeException('Unexpected plugin group'); }
            return [(object) ['name'=>'studioexample']];
        }
    }
}
namespace Joomla\CMS {
    class Factory {
        public static object $application;
        public static function getApplication() { return self::$application; }
    }
}
namespace {
    define('_JEXEC', 1);
    define('JPATH_ROOT', dirname(__DIR__));
    if (empty($argv[1]) || !is_file($argv[1])) {
        throw new \InvalidArgumentException('Usage: php tests/design-pack-example.php /path/to/tcpdf.php');
    }
    require $argv[1];
    require dirname(__DIR__) . '/component/admin/src/Service/Design.php';
    require dirname(__DIR__) . '/design-packs/studioexample/src/Extension/Studioexample.php';
    use Joomla\CMS\Factory;
    use ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Service\Design;
    use ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Service\DesignCatalog;
    use ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Service\Invoice;
    use ToPowerYou\Plugin\Vmstudiodesign\Studioexample\Extension\Studioexample;

    class ExampleTestPDF extends \TCPDF {
        public function parsed(string $html): array { return $this->getHtmlDomArray($html); }
    }
    $count = 0;
    function check(bool $condition, string $message): void {
        global $count;
        ++$count;
        if (!$condition) { throw new \RuntimeException($message); }
    }
    function makePdf(): ExampleTestPDF {
        $pdf = new ExampleTestPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator('VM Mail & Invoice Studio example-pack test');
        $pdf->SetTitle('Studio Example Pack — fictional invoice');
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->SetFont('helvetica', '', 10);
        Invoice::preparePdf($pdf);
        $pdf->AddPage();
        return $pdf;
    }
    function pdfText(string $path): string {
        exec('pdftotext -layout ' . escapeshellarg($path) . ' -', $lines, $code);
        check($code === 0, 'pdftotext extracted ' . basename($path));
        return implode("\n", $lines);
    }
    Factory::$application = new class {
        public function bootPlugin($element, $group) {
            check($element === 'studioexample' && $group === 'vmstudiodesign', 'Actual example class discovered through Joomla plugin contract');
            return new Studioexample();
        }
    };
    $catalog = DesignCatalog::current();
    check(array_keys($catalog->all()) === ['classic', 'modern', 'studioexample.editorial'], 'Actual example adds its own design beside both core designs');
    $settings = array_replace(Design::defaults(), [
        'email_preset'=>'studioexample.editorial', 'invoice_preset'=>'studioexample.editorial',
        'accent'=>'#24559a', 'brand'=>'North & Pine <Studio>',
        'email_intro'=>'Welcome Črt Žagar', 'email_closing'=>'Thank you & see you again.',
        'invoice_intro'=>'Črt Žagar — Ajdovščina', 'invoice_closing'=>'Closing note: ČŠŽ čšž.',
    ]);
    $previews = dirname(__DIR__) . '/previews';
    if (!is_dir($previews)) { mkdir($previews, 0755, true); }
    $email = Design::sample('email', $settings);
    check(str_contains($email, 'border-left:8px solid #24559a'), 'Sample email uses the real example renderer and current blue accent');
    check(str_contains($email, 'North &amp; Pine &lt;Studio&gt;'), 'Sample email escapes saved brand');
    check(str_contains($email, 'Welcome Črt Žagar') && str_contains($email, 'Thank you &amp; see you again.'), 'Sample email preserves Unicode intro and escaped closing');
    foreach (['Example product', '100.00 EUR', 'VAT (sample 22%)', '22.00 EUR', '122.00 EUR'] as $value) {
        check(str_contains($email, $value), 'Sample email preserves ' . $value);
    }
    check(!str_contains($email, DesignCatalog::CONTENT_TOKEN), 'Actual example consumes its content token');
    file_put_contents($previews . '/pack-example-email.html', $email);

    $sample = Design::sample('invoice', $settings, true);
    check(str_contains($sample, 'border-left:5pt solid #24559a'), 'Sample invoice uses real example renderer and current blue accent');
    check(str_contains($sample, 'North &amp; Pine &lt;Studio&gt;'), 'Sample invoice escapes saved brand');
    check(str_contains($sample, 'Example Customer') && str_contains($sample, 'Sample Street 12, 5270 Ajdovščina'), 'Sample invoice merges name, street, postcode and city');
    foreach (['Ship to', 'Same as billing address', 'Order status', 'Order date'] as $hidden) {
        check(!str_contains($sample, $hidden), 'Sample invoice hides Modern field: ' . $hidden);
    }
    file_put_contents($previews . '/pack-example-invoice.html', $sample);
    $pdf = makePdf();
    check($pdf->getFontFamily() === 'vmsans', 'Example PDF uses bundled Unicode font');
    $pdf->writeHTML($sample);
    $samplePath = $previews . '/pack-example-invoice.pdf';
    $pdf->Output($samplePath, 'F');
    $text = pdfText($samplePath);
    foreach (['North & Pine <Studio>', 'Črt Žagar', 'Ajdovščina', 'ČŠŽ čšž', '122.00 EUR', 'VAT (sample 22%)'] as $value) {
        check(str_contains($text, $value), 'Rendered sample PDF preserves ' . $value);
    }

    $context = ['omit'=>['Order date', 'Order status'], 'name'=>'Name', 'address'=>'Address',
        'fields'=>['first_name'=>'First name', 'last_name'=>'Last name', 'address_1'=>'Address 1', 'address_2'=>'Address 2', 'zip'=>'Postcode', 'city'=>'City'],
        'history'=>'Order history'];
    $body = '<h1>Invoice EXAMPLE-LONG-001</h1><table width="100%" cellpadding="0" cellspacing="0">'
        . '<tr><td>Invoice date</td><td>2026-11-12</td></tr><tr><td>Order number</td><td>EXAMPLE-123</td></tr>'
        . '<tr><td>Order date</td><td>2026-11-11</td></tr><tr><td>Order status</td><td>Confirmed</td></tr>'
        . '<tr><td>Payment method</td><td>Bank transfer</td></tr><tr><td>Total</td><td>878.40 EUR</td></tr>'
        . '<tr><td><strong>Billing details</strong><table>'
        . '<tr><td>First name</td><td>Črt</td></tr><tr><td>Last name</td><td>Žagar</td></tr>'
        . '<tr><td>Address 1</td><td>Street 15</td></tr><tr><td>Address 2</td><td>VAT SI-KEEP-123</td></tr>'
        . '<tr><td>Postcode</td><td>5270</td></tr><tr><td>City</td><td>Ajdovščina</td></tr></table></td>'
        . '<td><strong>Ship to</strong><br />HIDDEN-SHIPPING-ADDRESS</td></tr></table>'
        . '<div id="tab-1" class="tabs" title="Items"><table class="html-email" width="100%" cellspacing="0" cellpadding="0" border="0">'
        . '<tr class="sectiontableheader"><td id="sku" width="15%">SKU</td><td id="product" width="55%">Product name</td>'
        . '<td id="qty" width="10%">Qty</td><td id="total" width="20%">Total</td></tr>';
    for ($i = 1; $i <= 72; ++$i) {
        $number = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
        $body .= '<tr class="sectiontableentry1"><td>SKU-' . $number . '</td>'
            . '<td>Native item ' . $number . '<br />Product option: ČŠŽ čšž</td><td>1</td><td>10.00 EUR</td></tr>';
    }
    $body .= '<tr><td colspan="3">Products subtotal</td><td>720.00 EUR</td></tr>'
        . '<tr><td colspan="3">VAT 22%</td><td>158.40 EUR</td></tr>'
        . '<tr><td colspan="3"><strong>Total</strong></td><td><strong>878.40 EUR</strong></td></tr></table></div>'
        . '<div id="tab-2" class="tabs" title="Order history"><p>HIDDEN-HISTORY-COMMENT</p></div>'
        . '<p>Payment plugin reference: KEEP-PAYMENT-001.</p>'
        . '<p>Terms: KEEP-TERMS-001. Payment due within 14 days.</p>'
        . '<p>Footnote: KEEP-FOOTNOTE-001. These fictional amounts are supplied by the native fixture.</p>';
    $long = Design::frame($body, 'invoice', $settings, $settings['brand'], true, $context);
    check(str_contains($long, 'Črt Žagar') && str_contains($long, 'Street 15, 5270 Ajdovščina'), 'Real plugin renderer retains merged native customer details');
    foreach (['HIDDEN-SHIPPING-ADDRESS', 'HIDDEN-HISTORY-COMMENT', 'Order date', 'Order status'] as $hidden) {
        check(!str_contains($long, $hidden), 'Long invoice omits Modern-only field or panel: ' . $hidden);
    }
    foreach (['VAT SI-KEEP-123', 'Bank transfer', '720.00 EUR', 'VAT 22%', '158.40 EUR', '878.40 EUR', 'KEEP-PAYMENT-001', 'KEEP-TERMS-001', 'KEEP-FOOTNOTE-001'] as $value) {
        check(str_contains($long, $value), 'Long invoice HTML preserves native value: ' . $value);
    }
    file_put_contents($previews . '/pack-example-long-invoice.html', $long);
    $pdf = makePdf();
    $headers = [];
    foreach ($pdf->parsed($long) as $node) {
        if (!empty($node['opening']) && isset($node['attribute']['id'])) { $headers[$node['attribute']['id']] = $node; }
    }
    foreach (['sku', 'product', 'qty', 'total'] as $id) {
        check(($headers[$id]['bgcolor'] ?? null) === ['R'=>36, 'G'=>85, 'B'=>154], 'Actual example PDF parser gives native header saved blue: ' . $id);
        check(($headers[$id]['fgcolor'] ?? null) === ['R'=>255, 'G'=>255, 'B'=>255], 'Actual example PDF header has white text: ' . $id);
    }
    $pdf->writeHTML($long);
    $pages = $pdf->getNumPages();
    check($pages > 1, 'Native item table paginates through actual plugin renderer');
    $longPath = $previews . '/pack-example-long-invoice.pdf';
    $pdf->Output($longPath, 'F');
    $text = pdfText($longPath);
    for ($i = 1; $i <= 72; ++$i) {
        $item = 'Native item ' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
        check(substr_count($text, $item) === 1, 'Paginated PDF contains native row exactly once: ' . $item);
    }
    foreach (['Črt Žagar', 'Street 15, 5270 Ajdovščina', 'ČŠŽ čšž', 'VAT SI-KEEP-123', 'Invoice EXAMPLE-LONG-001', '720.00 EUR', 'VAT 22%', '158.40 EUR', '878.40 EUR', 'KEEP-PAYMENT-001', 'KEEP-TERMS-001', 'KEEP-FOOTNOTE-001'] as $value) {
        check(str_contains($text, $value), 'Paginated PDF text preserves ' . $value);
    }
    foreach (['HIDDEN-SHIPPING-ADDRESS', 'HIDDEN-HISTORY-COMMENT', 'Order date', 'Order status'] as $hidden) {
        check(!str_contains($text, $hidden), 'Paginated PDF has no hidden Modern field: ' . $hidden);
    }
    check($catalog->issues() === [], 'Actual example renders without activating fallback');
    echo "PASS: $count actual example-pack assertions; long invoice renders $pages pages with all 72 items, Unicode, totals and terms preserved\n";
}
