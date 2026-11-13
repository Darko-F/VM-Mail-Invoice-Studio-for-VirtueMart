<?php
/**
 * @package VM Mail & Invoice Studio
 * @copyright Copyright (C) 2026 Darko Fatur / ToPowerYou
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Service;

defined('_JEXEC') or die;

final class Invoice
{
    public static function preparePdf(object $pdf): void
    {
        // Core PDF fonts lack č and Č. Bundle a Unicode sans-serif font for readable invoices.
        $core = ['helvetica', 'times', 'courier'];
        foreach ([''=>'dejavusans', 'B'=>'dejavusansb', 'I'=>'dejavusansi', 'BI'=>'dejavusansbi'] as $style => $file) {
            $pdf->AddFont('vmsans', $style, dirname(__DIR__, 2) . '/fonts/' . $file . '.php');
        }
        if (in_array(strtolower($pdf->getFontFamily()), $core, true)) {
            $pdf->SetFont('vmsans', $pdf->getFontStyle(), $pdf->getFontSizePt());
        }
        foreach (['Header', 'Footer'] as $part) {
            $font = $pdf->{'get' . $part . 'Font'}();
            if (in_array(strtolower($font[0]), $core, true)) {
                $font[0] = 'vmsans';
                $pdf->{'set' . $part . 'Font'}($font);
            }
        }
    }

    public static function context(object $view): array
    {
        $labels = [];
        foreach ($view->userfields['fields'] ?? [] as $key => $field) {
            $name = is_string($key) ? $key : ($field['name'] ?? '');
            if ($name !== '') {
                $labels[$name] = (string) ($field['title'] ?? '');
            }
        }
        $address = \vmText::_('COM_VIRTUEMART_ADDRESS');
        return [
            'omit' => [\vmText::_('COM_VIRTUEMART_ORDER_PRINT_PO_DATE'), \vmText::_('COM_VIRTUEMART_ORDER_PRINT_PO_STATUS')],
            'fields' => $labels,
            'name' => \vmText::_('COM_VIRTUEMART_USER_FORM_NAME'),
            'address' => $address === 'COM_VIRTUEMART_ADDRESS' ? ($labels['address_1'] ?? '') : $address,
            'invoice_heading' => \vmText::_('COM_VIRTUEMART_INVOICE'),
            'history' => \vmText::_('COM_VIRTUEMART_ORDER_HISTORY'),
        ];
    }

    public static function withoutHistory(string $html, string $title): string
    {
        // VM4.8 buildTabs wraps history in tab-2. Remove only that translated panel,
        // retaining the items tab, plugin tables and other surrounding native markup.
        preg_match_all('~</?div\b[^>]*>~i', $html, $tags, PREG_OFFSET_CAPTURE);
        $depth = 0;
        $start = null;
        foreach ($tags[0] as [$tag, $offset]) {
            if ($start === null) {
                if (str_starts_with($tag, '</')) {
                    continue;
                }
                $attributes = [];
                preg_match_all('~\b(id|class|title)\s*=\s*([\'\"])(.*?)\2~is', $tag, $matches, PREG_SET_ORDER);
                foreach ($matches as $match) {
                    $attributes[strtolower($match[1])] = html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
                if (($attributes['id'] ?? '') !== 'tab-2' || ($attributes['title'] ?? '') !== $title
                    || !in_array('tabs', preg_split('/\s+/', trim($attributes['class'] ?? '')), true)) {
                    continue;
                }
                $start = $offset;
                $depth = 1;
            } else {
                $depth += str_starts_with($tag, '</') ? -1 : 1;
                if ($depth === 0) {
                    return substr_replace($html, '', $start, $offset + strlen($tag) - $start);
                }
            }
        }
        return $html;
    }

    public static function compact(string $html, bool $modern = false, array $context = []): string
    {
        // Only replace VM's order-details table immediately following its invoice title.
        // Leave item tables, plugin output outside this table, terms and history intact.
        if (!preg_match('~</h1>\s*(<table\b[^>]*>)~i', $html, $start, PREG_OFFSET_CAPTURE)) {
            return $html;
        }
        $offset = $start[1][1];
        preg_match_all('~</?table\b[^>]*>~i', substr($html, $offset), $tags, PREG_OFFSET_CAPTURE);
        $depth = 0;
        foreach ($tags[0] as [$tag, $position]) {
            $depth += str_starts_with($tag, '</') ? -1 : 1;
            if ($depth !== 0) {
                continue;
            }
            $length = $position + strlen($tag);
            $original = substr($html, $offset, $length);
            $replacement = self::details($original, $modern, $context);
            return substr_replace($html, $replacement, $offset, $length);
        }
        return $html;
    }

    private static function content(\DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument->saveHTML($child);
        }
        return $html;
    }

    private static function cell(string $html, bool $label = false): string
    {
        return '<td width="' . ($label ? '20' : '30') . '%" style="font-size:8.5pt;vertical-align:top;">' . $html . '</td>';
    }

    private static function text(string $html): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private static function billing(array $pairs, array $context): array
    {
        // Identify fields by VM field names and their translated titles, not English text.
        $labels = array_map([self::class, 'text'], $context['fields'] ?? []);
        $ids = [];
        foreach ($pairs as $i => [$label, $value]) {
            $matches = array_keys($labels, self::text($label), true);
            if (count($matches) === 1) {
                $ids[$matches[0]][] = $i;
            }
        }
        $merge = static function (array $names, string $label, callable $join) use (&$pairs, $ids): void {
            $found = [];
            foreach ($names as $name) {
                if (isset($ids[$name]) && count($ids[$name]) === 1) {
                    $index = $ids[$name][0];
                    $found[$name] = [$index, $pairs[$index][1]];
                }
            }
            if (!$found || $label === '') {
                return;
            }
            $first = min(array_column($found, 0));
            $values = array_map(static fn(array $entry): string => $entry[1], $found);
            foreach ($found as [$index]) {
                unset($pairs[$index]);
            }
            $pairs[$first] = [htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ':', $join($values)];
        };
        $merge(['first_name', 'middle_name', 'last_name'], $context['name'] ?? '', static fn(array $values): string => implode(' ', $values));
        // Keep address_2 as its own field: shops may use it for additional identifiers.
        $merge(['address_1', 'zip', 'city'], $context['address'] ?? '', static function (array $values): string {
            $city = implode(' ', array_filter([$values['zip'] ?? '', $values['city'] ?? ''], static fn(string $value): bool => $value !== ''));
            return implode(', ', array_filter([$values['address_1'] ?? '', $city], static fn(string $value): bool => $value !== ''));
        });
        ksort($pairs);
        return array_values($pairs);
    }

    private static function details(string $html, bool $modern, array $context): string
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (!$loaded) {
            return $html;
        }
        $xpath = new \DOMXPath($document);
        $table = $xpath->query('//table')->item(0);
        $pairs = [];
        $addresses = null;
        foreach ($xpath->query('./tr | ./tbody/tr', $table) as $row) {
            $cells = $xpath->query('./td', $row);
            if ($cells->length === 1 && trim($cells->item(0)->textContent) === '') {
                continue;
            }
            if ($cells->length !== 2) {
                return $html; // Preserve unrecognised third-party order layouts.
            }
            if ($xpath->query('./table', $cells->item(0))->length) {
                if ($addresses !== null) {
                    return $html;
                }
                $addresses = [$cells->item(0), $cells->item(1)];
            } else {
                $label = self::content($cells->item(0));
                if (!$modern || !in_array(self::text($label), array_map([self::class, 'text'], $context['omit'] ?? []), true)) {
                    $pairs[] = [$label, self::content($cells->item(1))];
                }
            }
        }
        if (!$pairs || !$addresses) {
            return $html;
        }
        $result = '<table class="vms-details" width="100%" cellspacing="0" cellpadding="3" border="0">';
        foreach (array_chunk($pairs, 2) as $row) {
            $result .= '<tr>';
            foreach (array_pad($row, 2, ['', '']) as [$label, $value]) {
                $result .= self::cell($label, true) . self::cell($value);
            }
            $result .= '</tr>';
        }
        $result .= '</table><br /><table class="vms-addresses" width="100%" cellspacing="0" cellpadding="3" border="0"><tr>';
        $fields = [];
        if ($modern) {
            $addresses = [$addresses[0]];
        }
        foreach ($addresses as $side => $address) {
            $heading = $xpath->query('./strong', $address)->item(0);
            if (!$heading) {
                return $html;
            }
            $result .= '<td colspan="' . ($modern ? '4' : '2') . '" width="' . ($modern ? '100' : '50') . '%" style="font-size:9pt;font-weight:bold;">' . self::content($heading) . '</td>';
            $fields[$side] = [];
            $addressTable = $xpath->query('./table', $address)->item(0);
            if ($addressTable) {
                foreach ($xpath->query('./tr | ./tbody/tr', $addressTable) as $row) {
                    $cells = $xpath->query('./td', $row);
                    if ($cells->length !== 2) {
                        return $html;
                    }
                    $fields[$side][] = [self::content($cells->item(0)), self::content($cells->item(1))];
                }
            } else {
                $message = $address->cloneNode(true);
                foreach (iterator_to_array($message->childNodes) as $child) {
                    if ($child instanceof \DOMElement && in_array($child->tagName, ['strong', 'br'], true)) {
                        $message->removeChild($child);
                    }
                }
                $fields[$side][] = [null, self::content($message)];
            }
        }
        $result .= '</tr>';
        if ($modern) {
            // Keep billing on the left and invoice metadata on the right, one pair per side.
            $billing = self::billing($fields[0], $context);
            $billingHeading = self::content($xpath->query('./strong', $addresses[0])->item(0));
            $invoiceHeading = htmlspecialchars($context['invoice_heading'] ?? 'Invoice details', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $result = '<table class="vms-details" width="100%" cellspacing="0" cellpadding="3" border="0">'
                . '<tr class="vms-addresses"><td colspan="2" width="50%" style="font-size:9pt;font-weight:bold;">' . $billingHeading . '</td>'
                . '<td colspan="2" width="50%" style="font-size:9pt;font-weight:bold;">' . $invoiceHeading . '</td></tr>';
            for ($i = 0, $count = max(count($billing), count($pairs)); $i < $count; ++$i) {
                $result .= '<tr>';
                foreach ([$billing[$i] ?? ['', ''], $pairs[$i] ?? ['', '']] as [$label, $value]) {
                    $result .= self::cell($label, true) . self::cell($value);
                }
                $result .= '</tr>';
            }
            return $result . '</table>';
        }
        for ($i = 0, $count = max(count($fields[0]), count($fields[1])); $i < $count; ++$i) {
            $result .= '<tr>';
            foreach ([0, 1] as $side) {
                [$label, $value] = $fields[$side][$i] ?? ['', ''];
                $result .= $label === null
                    ? '<td colspan="2" width="50%" style="font-size:8.5pt;vertical-align:top;">' . $value . '</td>'
                    : self::cell($label, true) . self::cell($value);
            }
            $result .= '</tr>';
        }
        return $result . '</table>';
    }
}
