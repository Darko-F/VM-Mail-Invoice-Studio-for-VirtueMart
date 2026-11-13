<?php
/**
 * @package VM Mail & Invoice Studio - Invoice Collection
 * @copyright Copyright (C) 2026 Darko Fatur / ToPowerYou
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace ToPowerYou\Plugin\Vmstudiodesign\Studioinvoicecollection\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Contract\DesignPackInterface;

final class Studioinvoicecollection extends CMSPlugin implements DesignPackInterface
{
    private const NAMES = [
        'monochrome' => 'Monochrome Professional', 'blue' => 'Blue Professional',
        'minimal' => 'Minimal Signature', 'pink-teal' => 'Pink & Teal',
    ];

    public function getDesignPack(): array
    {
        $designs = [];
        foreach (self::NAMES as $id => $name) {
            $designs[$id] = [
                'name' => $name, 'base' => 'modern', 'kinds' => ['email', 'invoice'],
                'styles' => ['background' => '#ffffff', 'border' => '#dddddd',
                    'padding' => 32, 'heading_style' => 'plain', 'invoice_layout' => 'modern'],
                'content_renderer' => [$this, 'content'],
                'renderers' => ['invoice' => [$this, 'invoice'], 'email' => [$this, 'email']],
            ];
        }
        return ['api' => 1, 'min_core' => '1.0.1', 'name' => 'Invoice & Email Collection', 'designs' => $designs];
    }

    /** Style native document fragments without rebuilding prices or dropping fields. */
    public function content(array $context): string
    {
        $id = $this->id($context);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML('<?xml encoding="UTF-8"><html><body>' . $context['body_html'] . '</body></html>', LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (!$loaded) { throw new \UnexpectedValueException('Cannot parse document content.'); }
        $xpath = new \DOMXPath($document);
        $hasClass = static fn(string $name): string => 'contains(concat(" ",normalize-space(@class)," ")," ' . $name . ' ")';
        // Native mail addresses and items both use html-email. Identify items by
        // their sectiontableheader row; do not apply item grids to addresses.
        foreach ($xpath->query('//table[' . $hasClass('html-email') . ' or ' . $hasClass('vms-details') . ']') as $table) {
            $headers = $xpath->query('./tr[' . $hasClass('sectiontableheader') . '] | ./thead/tr[' . $hasClass('sectiontableheader') . '] | ./tbody/tr[' . $hasClass('sectiontableheader') . ']', $table);
            $items = $headers->length > 0;
            $table->setAttribute('class', $table->getAttribute('class') . ($items ? ' collection-items' : ' collection-details'));
            $table->setAttribute('border', '0');
            $table->setAttribute('cellspacing', '0');
            $table->setAttribute('cellpadding', $items ? '6' : '3');
            $table->setAttribute('width', '100%');
            $table->setAttribute('style', 'border-collapse:collapse;font-family:Arial,Helvetica,sans-serif;');
            foreach ($xpath->query('./tr | ./tbody/tr | ./thead/tr | ./tfoot/tr', $table) as $row) {
                $header = str_contains(' ' . $row->getAttribute('class') . ' ', ' sectiontableheader ');
                $row->removeAttribute('bgcolor');
                $row->setAttribute('style', 'vertical-align:top;');
                foreach ($xpath->query('./td | ./th', $row) as $cell) {
                    // Preserve widths, alignment, spans and plugin HTML inside cells.
                    $kept = [];
                    foreach (explode(';', $cell->getAttribute('style')) as $declaration) {
                        $parts = explode(':', $declaration, 2);
                        if (count($parts) === 2 && in_array(strtolower(trim($parts[0])), ['width','text-align','vertical-align'], true)) {
                            $kept[] = $declaration;
                        }
                    }
                    $cell->removeAttribute('bgcolor');
                    $style = implode(';', $kept) . ';font-size:' . ($header ? '8' : '9') . 'pt;color:#333333;';
                    if ($items) {
                        $style .= $id === 'pink-teal' ? 'border:0.5pt solid #999999;' : 'border-bottom:0.5pt solid ' . ($id === 'minimal' ? '#ffffff' : '#e3e3e3') . ';';
                        if ($header) {
                            $bg = $id === 'blue' ? '#124780' : ($id === 'pink-teal' ? '#eeeeee' : '#ffffff');
                            $fg = $id === 'blue' ? '#ffffff' : '#222222';
                            $cell->setAttribute('bgcolor', $bg);
                            $style .= 'background-color:' . $bg . ';color:' . $fg . ';font-weight:bold;border-bottom:0.7pt solid #888888;';
                        }
                    } else {
                        $style .= 'background-color:#ffffff;';
                    }
                    $cell->setAttribute('style', ltrim($style, ';'));
                }
            }
            // Separate the native summary tail visually, retaining every original
            // cell (including tax, discount and alternate-currency amounts).
            if ($items) {
                $tail = [];
                $started = false;
                foreach ($xpath->query('./tr | ./tbody/tr | ./tfoot/tr', $table) as $row) {
                    $first = $xpath->query('./td', $row)->item(0);
                    if (!$started && $first && (
                        str_contains(' ' . $row->getAttribute('class') . ' ', ' vms-total ')
                        || ((int) $first->getAttribute('colspan') > 1 && str_contains(' ' . $first->getAttribute('class') . ' ', ' pricePad '))
                    )) { $started = true; }
                    if ($started) { $tail[] = $row; }
                }
                if ($tail && !$xpath->query('.//*[@rowspan]', $table)->length) {
                    $totals = $document->createElement('table');
                    foreach (['class'=>'collection-totals','width'=>'100%','cellspacing'=>'0','cellpadding'=>'5','border'=>'0'] as $key=>$value) { $totals->setAttribute($key,$value); }
                    foreach ($tail as $row) {
                        $cells = $xpath->query('./td | ./th', $row);
                        foreach ($cells as $index=>$cell) {
                            $width = $cells->length === 1 ? 100 : ($index === 0 ? 60 : 40 / ($cells->length-1));
                            $cell->removeAttribute('colspan');
                            $cell->setAttribute('width', (string)$width . '%');
                            $cell->setAttribute('align','right');
                            $cell->setAttribute('style','font-size:9pt;color:#333333;text-align:right;');
                        }
                        $totals->appendChild($row);
                    }
                    $table->parentNode->insertBefore($totals,$table->nextSibling);
                    $table->parentNode->insertBefore($document->createElement('br'),$totals);
                }
            }
        }
        // Native h1 and paragraph styles must not override the pack typography.
        foreach ($xpath->query('//h1') as $heading) {
            $heading->setAttribute('style', 'font-family:Arial,Helvetica,sans-serif;font-size:22pt;font-weight:bold;color:' . ($id === 'blue' ? '#124780' : '#333333') . ';');
        }
        $html = '';
        foreach ($document->getElementsByTagName('body')->item(0)->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }
        if (!empty($context['pdf'])) {
            $html = str_replace('font-family:Arial,Helvetica,sans-serif;', 'font-family:vmsans;', $html);
        }
        return $html;
    }

    private function id(array $context): string
    {
        $id = substr($context['design_id'], strlen('studioinvoicecollection.'));
        return isset(self::NAMES[$id]) ? $id : 'monochrome';
    }

    private function image(string $name, bool $pdf, int $width): string
    {
        $source = $pdf ? dirname(__DIR__, 2) . '/assets/' . $name . '.png'
            : rtrim(Uri::root(), '/') . '/plugins/vmstudiodesign/studioinvoicecollection/assets/' . $name . '.png';
        return '<img src="' . htmlspecialchars($source, ENT_QUOTES, 'UTF-8')
            . '" width="' . $width . '" alt="" style="display:block;width:100%;height:auto" />';
    }

    private function css(string $id): string
    {
        $blue = $id === 'blue';
        $ink = $blue ? '#124780' : '#333333';
        $head = $blue ? '#124780' : ($id === 'pink-teal' ? '#eeeeee' : '#ffffff');
        $fg = $blue ? '#ffffff' : '#222222';
        $rule = $id === 'minimal' ? '#ffffff' : '#e3e3e3';
        $grid = $id === 'pink-teal' ? 'border:0.5pt solid #999999;' : 'border-bottom:0.5pt solid ' . $rule . ';';
        return 'h1{font-family:helvetica,Arial,sans-serif;font-size:22pt;font-weight:bold;color:' . $ink . ';}'
            . 'p{line-height:1.5;}table{border-collapse:collapse;}'
            . 'table.collection-items td{font-size:9pt;padding:6px;' . $grid . '}'
            . 'table.collection-items th,table.collection-items tr.sectiontableheader td{background-color:' . $head
            . ';color:' . $fg . ';font-size:8pt;font-weight:bold;border-bottom:0.7pt solid #888888;}'
            . 'table.collection-items tr.sectiontableentry1 td{background-color:#ffffff;}';
    }

    private function header(array $context, int $width): string
    {
        $id = $this->id($context);
        $pdf = !empty($context['pdf']);
        $heading = $context['heading_html'];
        if ($id === 'blue') {
            return '<table width="100%" cellspacing="0" cellpadding="18"><tr><td bgcolor="#094c8f" style="color:#ffffff;font-size:19pt;font-weight:bold">'
                . $heading . '</td></tr></table>' . $this->image('blue-wave', $pdf, $width);
        }
        if ($id === 'pink-teal') {
            return $this->image('pink-top', $pdf, $width) . '<br />'
                . '<table width="100%" cellpadding="10"><tr><td style="color:#444444;font-size:18pt;font-weight:bold">' . $heading . '</td></tr></table>';
        }
        $align = $id === 'minimal' ? 'right' : 'left';
        return '<table width="100%" cellpadding="10"><tr><td align="' . $align
            . '" style="font-size:' . ($id === 'minimal' ? '16' : '12') . 'pt;color:#333333">'
            . $heading . '</td></tr></table><br /><br />';
    }

    public function invoice(array $context): string
    {
        $id = $this->id($context);
        // Native invoice is outside wrapper tables to preserve multipage output.
        return '<style>' . $this->css($id) . '</style>' . $this->header($context, 640)
            . '<br />' . $context['content_token']
            . ($id === 'pink-teal' ? '<br />' . $this->image('pink-bottom', !empty($context['pdf']), 640) : '');
    }

    public function email(array $context): string
    {
        $id = $this->id($context);
        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<style>' . $this->css($id) . '@media(max-width:640px){.studio-content{padding:20px!important;}}</style></head>'
            . '<body style="margin:0;padding:24px 8px;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#333333">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center">'
            . '<table role="presentation" width="640" cellspacing="0" cellpadding="0" style="width:100%;max-width:640px;background:#ffffff;border:1px solid #e5e5e5">'
            . '<tr><td>' . $this->header($context, 640) . '</td></tr>'
            . '<tr><td class="studio-content" style="padding:24px 36px 48px;font-size:14px;line-height:1.6">'
            . $context['content_token'] . '</td></tr>'
            . ($id === 'pink-teal' ? '<tr><td>' . $this->image('pink-bottom', false, 640) . '</td></tr>' : '')
            . '</table></td></tr></table></body></html>';
    }
}
