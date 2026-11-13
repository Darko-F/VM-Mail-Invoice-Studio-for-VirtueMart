<?php
/** Copyright (C) 2026 ToPowerYou. GNU General Public License version 2 or later. */
define('_JEXEC',1);
define('JPATH_ROOT',sys_get_temp_dir());
require $argv[1];
require dirname(__DIR__).'/component/admin/src/Service/Design.php';
use ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Service\Design;
foreach (['classic','modern'] as $preset) {
    $settings=array_replace(Design::defaults(),['invoice_preset'=>$preset,'brand'=>'North & Pine','invoice_closing'=>'Thank you for your business.']);
    $pdf=new TCPDF();
    $pdf->setPrintHeader(false);$pdf->setPrintFooter(false);
    $pdf->SetCreator('VM Mail & Invoice Studio');$pdf->SetTitle('Studio sample invoice');
    $pdf->SetFont('helvetica','',10);
    \ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Service\Invoice::preparePdf($pdf);
    $pdf->AddPage();
    $pdf->writeHTML(Design::sample('invoice',$settings,true));
    $pdf->Output(dirname(__DIR__).'/previews/invoice-'.$preset.'.pdf','F');
}
echo "PASS: both invoice presets rendered through bundled TCPDF\n";
