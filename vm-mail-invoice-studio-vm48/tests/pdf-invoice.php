<?php
/** Copyright (C) 2026 ToPowerYou. @license GNU General Public License version 2 or later */
define('_JEXEC', 1);
require $argv[1];
require dirname(__DIR__) . '/component/admin/src/Service/Design.php';
use ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Service\Design;
class InvoiceTestPDF extends TCPDF {
    public function parsed(string $html): array { return $this->getHtmlDomArray($html); }
}
function check(bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } }
// VM4.8 invoice_items.php uses this table/row class and td headings with inline widths.
$body = '<h1>Invoice · TEST-001</h1><table><tr><td id="address">Billing address</td><td>Shipping address</td></tr></table><br />'
    . '<table class="html-email" width="100%" cellspacing="0" cellpadding="0" border="0">'
    . '<tr style="text-align:left" class="sectiontableheader">'
    . '<td id="sku" style="text-align:left;width:10%"><strong>SKU</strong></td>'
    . '<td id="product" colspan="2" style="text-align:left;width:55%"><strong>Product name</strong></td>'
    . '<td id="qty" style="text-align:right;width:10%"><strong>Qty</strong></td>'
    . '<td id="total" style="text-align:right;width:25%"><strong>Total</strong></td></tr>'
    . '<tr><td id="item">TEST-01</td><td colspan="2">Test product<br />Custom field: blue</td><td>2</td><td>100.00 EUR</td></tr>'
    . '<tr><td colspan="4">Tax</td><td>22.00 EUR</td></tr>'
    . '<tr><td colspan="4"><strong>Total</strong></td><td><strong>122.00 EUR</strong></td></tr></table>'
    . '<p>Payment plugin output preserved.</p>';
foreach (['classic', 'modern'] as $preset) {
    $settings = array_replace(Design::defaults(), ['invoice_preset'=>$preset, 'accent'=>'#24559a']);
    $html = Design::frame($body, 'invoice', $settings, 'Test shop', true);
    check(str_contains($html, str_replace('cellpadding="0"', 'cellpadding="4"', $body)), 'Native content preserved except item table padding');
    $pdf = new InvoiceTestPDF();
    $pdf->setPrintHeader(false); $pdf->setPrintFooter(false);
    $pdf->SetFont('helvetica', '', 10); $pdf->AddPage();
    $cells = [];
    foreach ($pdf->parsed($html) as $node) {
        if (!empty($node['opening']) && isset($node['attribute']['id'])) { $cells[$node['attribute']['id']] = $node; }
    }
    foreach (['sku', 'product', 'qty', 'total'] as $id) {
        check($cells[$id]['bgcolor'] === ['R'=>36, 'G'=>85, 'B'=>154], 'VM td header gets chosen blue: ' . $id);
        check($cells[$id]['fgcolor'] === ['R'=>255, 'G'=>255, 'B'=>255], 'VM td header has white text: ' . $id);
    }
    check(!empty($cells['item']['border']), 'Item cells have borders');
    check(($cells['address']['bgcolor'] ?? []) !== ['R'=>36, 'G'=>85, 'B'=>154], 'Address table is not recolored');
    $pdf->writeHTML($html);
    $pdf->Output(dirname(__DIR__) . '/previews/invoice-vm5-' . $preset . '.pdf', 'F');
}
echo "PASS: VM4.8 td headers, blue/white colors, item borders, unchanged native content and unstyled address table\n";
