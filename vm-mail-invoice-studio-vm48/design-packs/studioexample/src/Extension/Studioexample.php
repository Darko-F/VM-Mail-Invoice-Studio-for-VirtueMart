<?php
/**
 * @package VM Mail & Invoice Studio - Example Design Pack
 * @copyright Copyright (C) 2026 Darko Fatur / ToPowerYou
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace ToPowerYou\Plugin\Vmstudiodesign\Studioexample\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Contract\DesignPackInterface;

final class Studioexample extends CMSPlugin implements DesignPackInterface
{
    public function getDesignPack(): array
    {
        return [
            'api'=>1,
            'min_core'=>'1.0.0',
            'name'=>'Studio Example Pack',
            'designs'=>[
                'editorial'=>[
                    'name'=>'Editorial',
                    'base'=>'modern',
                    'kinds'=>['email', 'invoice'],
                    'styles'=>['background'=>'#f4f5f7', 'border'=>'#d9dee5', 'padding'=>28],
                    'renderers'=>['email'=>[$this, 'email'], 'invoice'=>[$this, 'invoice']],
                ],
            ],
        ];
    }

    public function email(array $context): string
    {
        // Studio supplies validated colors, an escaped heading and one content token.
        // The token preserves the native order details and Studio's intro/closing.
        $accent = $context['accent'];
        $border = $context['border'];
        $background = $context['background'];
        $padding = (int) $context['padding'];
        return '<!doctype html><html><head><meta charset="utf-8"></head>'
            . '<body style="margin:0;padding:24px 8px;background:' . $background . ';font-family:Arial,Helvetica,sans-serif;color:#26353d">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center">'
            . '<table role="presentation" width="640" cellspacing="0" cellpadding="' . $padding . '" style="width:100%;max-width:640px;background:#ffffff;border:1px solid ' . $border . '">'
            . '<tr><td style="border-left:8px solid ' . $accent . ';border-bottom:1px solid ' . $border . ';color:' . $accent . ';font-size:27px;font-weight:bold">'
            . $context['heading_html'] . '</td></tr><tr><td style="font-size:14px;line-height:1.5">'
            . $context['content_token'] . '</td></tr></table></td></tr></table></body></html>';
    }

    public function invoice(array $context): string
    {
        $accent = $context['accent'];
        $border = $context['border'];
        // Keep the native invoice outside a wrapper table, so TCPDF can paginate it.
        return '<style>h1{color:' . $accent . ';font-size:20pt}th{background-color:' . $accent . ';color:#ffffff}td{font-size:10pt}'
            . 'table.html-email td{border:0.5pt solid ' . $border . ';padding:4px}'
            . 'table.html-email tr.sectiontableheader td{background-color:' . $accent . ';color:#ffffff;font-weight:bold}'
            . 'table.html-email tr.sectiontableentry1 td{background-color:#ffffff}</style>'
            . '<table width="100%" cellspacing="0" cellpadding="10"><tr><td style="border-left:5pt solid ' . $accent . ';border-bottom:1pt solid ' . $border . ';color:' . $accent . ';font-size:18pt">'
            . $context['heading_html'] . '</td></tr></table><br />'
            . $context['content_token'];
    }
}
