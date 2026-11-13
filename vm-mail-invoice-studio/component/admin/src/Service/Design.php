<?php
/**
 * @package VM Mail & Invoice Studio
 * @copyright Copyright (C) 2026 Darko Fatur / ToPowerYou
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Service;

defined('_JEXEC') or die;

require_once __DIR__ . '/Invoice.php';
require_once __DIR__ . '/DesignCatalog.php';


use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

final class Design
{
    public static function defaults(): array
    {
        return ['email_enabled'=>0, 'invoice_enabled'=>0, 'email_preset'=>'modern', 'invoice_preset'=>'classic',
            'accent'=>'#147d73', 'brand'=>'', 'logo'=>'', 'email_intro'=>'', 'email_closing'=>'',
            'invoice_intro'=>'', 'invoice_closing'=>''];
    }

    public static function settings(): array
    {
        // PDFs and emails can run in different Joomla clients with separate caches.
        // Read the saved design directly so every new document uses the same settings.
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)->select($db->quoteName('params'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_vmmailinvoicestudio'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'));
        $params = new Registry((string) $db->setQuery($query)->loadResult());
        return array_replace(self::defaults(), $params->toArray());
    }

    public static function presets(?string $kind = null): array
    {
        return DesignCatalog::current()->all($kind);
    }

    public static function clean(array $input, array $saved = []): array
    {
        $data = self::defaults();
        foreach ($data as $key => $default) {
            if (isset($input[$key]) && !is_scalar($input[$key])) {
                throw new \InvalidArgumentException('COM_VMMAILINVOICESTUDIO_INVALID_INPUT');
            }
        }
        foreach (['email_enabled', 'invoice_enabled'] as $key) {
            $data[$key] = !empty($input[$key]) ? 1 : 0;
        }
        foreach (['email_preset', 'invoice_preset'] as $key) {
            $value = (string) ($input[$key] ?? $data[$key]);
            $kind = str_starts_with($key, 'email') ? 'email' : 'invoice';
            // Preserve an unavailable saved pack through unrelated edits. Never accept a new unknown ID.
            $preserved = ($saved[$key] ?? null) === $value
                && preg_match('/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_-]{0,63}$/D', $value);
            if (!isset(self::presets($kind)[$value]) && !$preserved) {
                throw new \InvalidArgumentException('COM_VMMAILINVOICESTUDIO_INVALID_PRESET');
            }
            $data[$key] = $value;
        }
        $data['accent'] = (string) ($input['accent'] ?? $data['accent']);
        if (!preg_match('/^#[0-9a-fA-F]{6}$/D', $data['accent'])) {
            throw new \InvalidArgumentException('COM_VMMAILINVOICESTUDIO_INVALID_COLOUR');
        }
        foreach (['brand', 'email_intro', 'email_closing', 'invoice_intro', 'invoice_closing'] as $key) {
            $data[$key] = trim(strip_tags((string) ($input[$key] ?? '')));
            if (strlen($data[$key]) > ($key === 'brand' ? 200 : 4000)) {
                throw new \InvalidArgumentException('COM_VMMAILINVOICESTUDIO_TEXT_TOO_LONG');
            }
        }
        $data['logo'] = trim(explode('#', (string) ($input['logo'] ?? ''))[0]);
        if ($data['logo'] !== '' && self::logoPath($data['logo']) === null) {
            throw new \InvalidArgumentException('COM_VMMAILINVOICESTUDIO_INVALID_LOGO');
        }
        return $data;
    }

    public static function logoPath(string $relative): ?string
    {
        if (!preg_match('~^images/[a-zA-Z0-9_ /.-]+\.(png|jpe?g|gif)$~iD', $relative)) {
            return null;
        }
        $base = realpath(JPATH_ROOT . '/images');
        $path = realpath(JPATH_ROOT . '/' . $relative);
        if (!$base || !$path || !str_starts_with($path, $base . DIRECTORY_SEPARATOR) || !is_file($path)) {
            return null;
        }
        // An invalid selected image must be rejected without emitting PHP notices into email output.
        $image = @getimagesize($path);
        return $image && in_array($image[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF], true) ? $path : null;
    }

    public static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function render(object $view, string $kind): void
    {
        $settings = self::settings();
        if ($kind === 'invoice' && ($view->doctype ?? 'invoice') !== 'invoice') {
            self::native($view, 'invoice');
            return;
        }
        if (empty($settings[$kind . '_enabled'])) {
            self::native($view, $kind === 'email' ? 'mail_html' : 'invoice');
            return;
        }
        if ($kind === 'invoice' && !empty($view->isPdf) && isset($view->pdf_formatter)) {
            Invoice::preparePdf($view->pdf_formatter);
        }
        ob_start();
        try {
            if ($kind === 'email') {
                $recipient = ($view->recipient ?? 'shopper') === 'vendor' ? 'vendor' : 'shopper';
                if ($recipient === 'shopper') {
                    echo $view->loadTemplate('header');
                }
                echo $view->loadTemplate($recipient);
                echo $view->loadTemplate('shopperaddresses');
                echo $view->loadTemplate('pricelist');
                echo $view->loadTemplate($recipient . '_more');
                echo $view->loadTemplate('footer');
            } else {
                self::native($view, 'invoice');
            }
            $body = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $brand = $settings['brand'] ?: (string) ($view->vendor->vendor_store_name ?? '');
        $context = $kind === 'invoice'
            && DesignCatalog::current()->resolve((string) $settings['invoice_preset'], 'invoice')['invoice_layout'] === 'modern'
            ? Invoice::context($view) : [];
        echo self::frame($body, $kind, $settings, $brand, !empty($view->isPdf), $context);
    }

    private static function native(object $view, string $layout): void
    {
        $file = JPATH_ROOT . '/components/com_virtuemart/views/invoice/tmpl/' . $layout . '.php';
        // Bind to the original view: native templates depend on $this and its protected layout.
        (function (string $template): void { include $template; })->call($view, $file);
    }

    public static function frame(string $body, string $kind, array $settings, string $brand, bool $pdf = false, array $invoiceContext = []): string
    {
        $catalog = DesignCatalog::current();
        $preset = $catalog->resolve((string) ($settings[$kind . '_preset'] ?? ''), $kind);
        $originalBody = $body;
        $colour = static fn($v, $fallback) => preg_match('/^#[0-9a-fA-F]{6}$/D', (string) $v) ? $v : $fallback;
        $accent = $colour($settings['accent'] ?? '', '#147d73');
        $dark = $preset['heading_style'] === 'filled';
        $modernInvoice = $preset['invoice_layout'] === 'modern';
        $headingBg = $dark ? $accent : '#ffffff';
        $headingColour = $dark ? '#ffffff' : $accent;
        $background = $colour($preset['background'] ?? '', '#f2f4f5');
        $border = $colour($preset['border'] ?? '', '#d4dce1');
        $padding = min(48, max(12, (int) ($preset['padding'] ?? 24)));
        $logo = '';
        $relative = (string) ($settings['logo'] ?? '');
        if ($relative !== '' && ($path = self::logoPath($relative))) {
            $source = $pdf ? $path : rtrim(Uri::root(), '/') . '/' . implode('/', array_map('rawurlencode', explode('/', $relative)));
            $logo = '<img src="' . self::escape($source) . '" width="120" alt="' . self::escape($brand) . '" /><br />';
        }
        $intro = nl2br(self::escape((string) ($settings[$kind . '_intro'] ?? '')));
        $closing = nl2br(self::escape((string) ($settings[$kind . '_closing'] ?? '')));
        $heading = $logo . self::escape($brand);
        if ($kind === 'email' && !isset($preset['renderers'][$kind])) {
            return '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
                . '<body style="margin:0;padding:24px 8px;background:' . $background . ';font-family:Arial,Helvetica,sans-serif;color:#26353d">'
                . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center">'
                . '<table role="presentation" width="640" cellspacing="0" cellpadding="' . $padding . '" style="width:100%;max-width:640px;background:#ffffff;border:1px solid ' . $border . ';border-top:5px solid ' . $accent . '">'
                . '<tr><td style="background:' . $headingBg . ';color:' . $headingColour . ';font-size:24px;font-weight:bold">' . $heading . '</td></tr>'
                . '<tr><td style="font-size:14px;line-height:1.6">' . ($intro ? '<p>' . $intro . '</p>' : '') . $body
                . ($closing ? '<p style="border-top:1px solid ' . $border . ';padding-top:18px">' . $closing . '</p>' : '')
                . '</td></tr></table></td></tr></table></body></html>';
        }
        if ($kind === 'invoice') {
            if ($modernInvoice && !empty($invoiceContext['history'])) {
                $body = Invoice::withoutHistory($body, $invoiceContext['history']);
            }
            $body = Invoice::compact($body, $modernInvoice, $invoiceContext);
            // TCPDF gives the native table's cellpadding="0" precedence over CSS padding.
            // Adjust only that attribute on VM's item table; keep its content and widths.
            $body = preg_replace_callback('~<table\b[^>]*>~i', static function (array $match): string {
                $tag = $match[0];
                if (preg_match('~\bclass\s*=\s*([\'\"])(.*?)\1~is', $tag, $classes)
                    && in_array('html-email', preg_split('/\s+/', trim($classes[2])), true)) {
                    $tag = preg_replace('~\bcellpadding\s*=\s*([\'\"])0\1~i', 'cellpadding="4"', $tag);
                }
                return $tag;
            }, $body);
        }
        if (isset($preset['renderers'][$kind])) {
            try {
                // Installed plugins are trusted PHP; the token contract prevents accidental loss of native content.
                $level = ob_get_level();
                $printed = '';
                ob_start();
                try {
                    $output = ($preset['renderers'][$kind])([
                        'api'=>1, 'kind'=>$kind, 'pdf'=>$pdf, 'design_id'=>$preset['id'],
                        'heading_html'=>$heading, 'accent'=>$accent, 'background'=>$background,
                        'border'=>$border, 'padding'=>$padding, 'content_token'=>DesignCatalog::CONTENT_TOKEN,
                    ]);
                } finally {
                    while (ob_get_level() > $level) { $printed = ob_get_clean() . $printed; }
                }
                if ($printed !== '') {
                    throw new \UnexpectedValueException('A design renderer must return HTML without printing.');
                }
                if (!is_string($output) || substr_count($output, DesignCatalog::CONTENT_TOKEN) !== 1) {
                    throw new \UnexpectedValueException('A design renderer must include the content token exactly once.');
                }
                $content = ($intro ? '<p>' . $intro . '</p>' : '') . $body
                    . ($closing ? '<p>' . $closing . '</p>' : '');
                return str_replace(DesignCatalog::CONTENT_TOKEN, $content, $output);
            } catch (\Throwable $error) {
                $catalog->report($preset['provider'], $error);
                $settings[$kind . '_preset'] = DesignCatalog::fallback($kind);
                return self::frame($originalBody, $kind, $settings, $brand, $pdf, $invoiceContext);
            }
        }
        // Do not wrap the complete invoice in a table: TCPDF must paginate item tables naturally.
        // VM's item headings are td cells in .sectiontableheader, not th elements.
        // Target its item table so address and plugin tables keep their own layout.
        return '<style>h1{color:' . $accent . ';font-size:20pt}th{background-color:' . $accent . ';color:#ffffff}td{font-size:10pt}'
            . 'table.html-email td{border:0.5pt solid ' . $border . ';padding:4px}'
            . 'table.html-email tr.sectiontableheader td{background-color:' . $accent . ';color:#ffffff;font-weight:bold}'
            . '</style>'
            . '<table width="100%" cellpadding="10" cellspacing="0"><tr><td style="background-color:' . $headingBg . ';color:' . $headingColour . ';border-bottom:2px solid ' . $accent . ';font-size:18pt">' . $heading . '</td></tr></table><br />'
            . ($intro ? '<p>' . $intro . '</p><br />' : '') . $body
            . ($closing ? '<br /><p>' . $closing . '</p>' : '');
    }

    public static function sample(string $kind, array $settings, bool $pdf = false): string
    {
        $context = [];
        $details = '<p>SAMPLE ONLY — fictional order, not a tax invoice.</p><p>Order STUDIO-001 · Confirmed</p>'
            . '<table width="100%" cellpadding="8"><tr><td><strong>Example customer</strong><br />Sample Street 12<br />1000 Ljubljana</td><td><strong>Example shop</strong><br />Vendor details from VirtueMart appear in real documents.</td></tr></table><br />';
        if ($kind === 'invoice') {
            $context = ['omit'=>['Order date', 'Order status'], 'name'=>'Name', 'address'=>'Address',
                'fields'=>['first_name'=>'First name', 'last_name'=>'Last name', 'address_1'=>'Address 1', 'zip'=>'Postcode', 'city'=>'City']];
            $details = '<table width="100%" cellspacing="0" cellpadding="0">'
                . '<tr><td>Invoice date</td><td>2026-11-12</td></tr><tr><td>Order number</td><td>STUDIO-001</td></tr>'
                . '<tr><td>Order date</td><td>2026-11-12</td></tr>'
                . '<tr><td>Order status</td><td>Confirmed</td></tr><tr><td>Total</td><td>122.00 EUR</td></tr>'
                . '<tr><td><strong>Billing details</strong><br /><table><tr><td>First name</td><td>Example</td></tr><tr><td>Last name</td><td>Customer</td></tr>'
                . '<tr><td>Address 1</td><td>Sample Street 12</td></tr><tr><td>Postcode</td><td>5270</td></tr><tr><td>City</td><td>Ajdovščina</td></tr></table></td>'
                . '<td><strong>Ship to</strong><br />Same as billing address</td></tr></table>'
                . '<p>SAMPLE ONLY — fictional order, not a tax invoice.</p>';
        }
        $body = '<h1>' . ($kind === 'email' ? 'Order confirmation' : 'Invoice · SAMPLE-001') . '</h1>' . $details
            . '<table width="100%" border="1" cellspacing="0" cellpadding="8"><tr><th>Item</th><th>Qty</th><th>Total</th></tr><tr><td>Example product</td><td>2</td><td>100.00 EUR</td></tr><tr><td colspan="2">VAT (sample 22%)</td><td>22.00 EUR</td></tr><tr><td colspan="2"><strong>Total</strong></td><td><strong>122.00 EUR</strong></td></tr></table>'
            . '<p>Payment and shipment details, tax breakdowns and terms are provided by VirtueMart in actual documents.</p>';
        return self::frame($body, $kind, $settings, $settings['brand'] ?: 'Your shop', $pdf, $context);
    }
}
