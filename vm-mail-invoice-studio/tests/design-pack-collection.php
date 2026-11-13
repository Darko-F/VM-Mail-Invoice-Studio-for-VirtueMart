<?php
/** Copyright (C) 2026 ToPowerYou. @license GNU General Public License version 2 or later */
namespace Joomla\CMS\Plugin {
    class CMSPlugin {}
    class PluginHelper { public static function getPlugin($group) { return [(object)['name'=>'studioinvoicecollection']]; } }
}
namespace Joomla\CMS\Uri { class Uri { public static function root() { return 'http://localhost/'; } } }
namespace Joomla\CMS { class Factory { public static function getApplication() { return new class {
    public function bootPlugin($element, $group) { return new \ToPowerYou\Plugin\Vmstudiodesign\Studioinvoicecollection\Extension\Studioinvoicecollection(); }
}; } } }
namespace {
    define('_JEXEC', 1);
    $root = dirname(__DIR__);
    if (empty($argv[1]) || !is_file($argv[1])) { throw new \InvalidArgumentException('Pass the path to tcpdf.php'); }
    require $root . '/component/admin/src/Service/Design.php';
    require $root . '/design-packs/studioinvoicecollection/src/Extension/Studioinvoicecollection.php';
    require $argv[1];
    use ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Service\Design;
    use ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Service\DesignCatalog;
    use ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Service\Invoice;
    class CollectionPDF extends \TCPDF { public function parsed($html) { return $this->getHtmlDomArray($html); } }
    $count = 0;
    function check($ok, $message) { global $count; ++$count; if (!$ok) { throw new \RuntimeException($message); } }
    function pdf($html, $path) {
        $pdf = new CollectionPDF(); $pdf->setPrintHeader(false); $pdf->setPrintFooter(false);
        $pdf->SetMargins(18,15,18); $pdf->SetAutoPageBreak(true,15); $pdf->SetFont('helvetica','',9);
        Invoice::preparePdf($pdf); $pdf->AddPage(); $pdf->writeHTML($html); $result = ['pages'=>$pdf->getNumPages(), 'parsed'=>$pdf->parsed($html)]; $pdf->Output($path,'F');
        return $result;
    }
    $out = $root . '/dist/design-packs/previews';
    if (!is_dir($out)) { mkdir($out,0755,true); }
    $plugin = new \ToPowerYou\Plugin\Vmstudiodesign\Studioinvoicecollection\Extension\Studioinvoicecollection();
    foreach ($plugin->getDesignPack()['designs'] as $id=>$definition) {
        $settings = array_replace(Design::defaults(), ['brand'=>'NORTH STUDIO','invoice_preset'=>'studioinvoicecollection.'.$id,'email_preset'=>'studioinvoicecollection.'.$id]);
        check(DesignCatalog::current()->resolve($settings['invoice_preset'], 'invoice')['provider']==='studioinvoicecollection', 'Pack must not silently fall back');
        foreach (['invoice','email'] as $kind) {
            $html = Design::sample($kind,$settings,$kind==='invoice');
            check(str_contains($html,'collection-items'),'Production sample uses pack content formatter');
            check(!str_contains($html,'border="1"'),'No hardcoded sample grid');
            foreach (['Example product','100.00 EUR','22.00 EUR','122.00 EUR','SAMPLE ONLY'] as $value) {
                check(str_contains($html,$value), 'Sample data preserved: '.$value);
            }
            if ($id==='blue') {
                check(str_contains($html,'bgcolor="#124780"'),'Blue heading is inline for PDF and email');
            }
            if ($kind==='invoice') {
                pdf($html,$out.'/'.$id.'.pdf');
            } else {
                $html=str_replace('http://localhost/plugins/vmstudiodesign/studioinvoicecollection/assets/','../../../design-packs/studioinvoicecollection/assets/',$html);
            }
            file_put_contents($out.'/'.$id.($kind==='email'?'-email':'-invoice').'.html',$html);
        }
        // Native email styles previously overrode pack CSS. Exercise both document
        // paths with the same styled cells, nested plugin output and long orders.
        $body='<h1>Invoice TEST-001</h1><table class="html-email"><tr><th bgcolor="#EEEEEE" style="border:1px solid #CCCCCC">Billing</th><th>Shipping</th></tr><tr><td>Črt Žagar</td><td>Ajdovščina</td></tr></table><br />'
            .'<table class="html-email" border="1" cellpadding="0"><tr class="sectiontableheader"><th width="70%" bgcolor="#EEEEEE" style="border:1px solid #CCCCCC">Item</th><th width="30%">Total</th></tr>';
        for($i=1;$i<=72;++$i) { $body.='<tr><td style="border:1px solid #CCCCCC">Line-'.$i.'-END <em>custom field</em></td><td>10.00 EUR</td></tr>'; }
        $body.='<tr><td colspan="1"><b>Total</b></td><td>720.00 EUR</td></tr></table><p>Payment REFERENCE-123</p><table id="plugin"><tr><td style="border:2px solid #ff0000">Plugin content</td></tr></table><p>Terms sentinel</p>';
        foreach(['invoice','email'] as $kind) {
            $html=Design::frame($body,$kind,$settings,'NORTH STUDIO',$kind==='invoice');
            check(!str_contains($html,'#CCCCCC'),'Native cell grid removed');
            check(str_contains($html,'border:2px solid #ff0000'),'Unrelated plugin markup preserved');
            check(str_contains($html,'REFERENCE-123')&&str_contains($html,'Terms sentinel'),'Payment and terms preserved');
            for($i=1;$i<=72;++$i) { check(substr_count($html,'Line-'.$i.'-END')===1,'Each order line preserved once'); }
            if($kind==='invoice') {
                $pdf=pdf($html,$out.'/'.$id.'-long.pdf');
                check($pdf['pages']>1,'Long invoice paginates');
                if($id==='blue') {
                    $found=false;
                    foreach($pdf['parsed'] as $node) {
                        if(($node['value']??'')==='th' && ($node['bgcolor']??null)===['R'=>18, 'G'=>71, 'B'=>128]){$found=true;}
                    }
                    check($found,'TCPDF parses the exact blue heading color');
                }
            }
        }
    }
    check(DesignCatalog::current()->issues()===[], 'No renderer failures');
    echo "Collection production-renderer checks passed: $count assertions.\n";
}
