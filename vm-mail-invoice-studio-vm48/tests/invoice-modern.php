<?php
/** Copyright (C) 2026 ToPowerYou. @license GNU General Public License version 2 or later */
define('_JEXEC', 1);
require dirname(__DIR__) . '/component/admin/src/Service/Invoice.php';
use ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Service\Invoice;
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
$context = ['omit'=>['Datum naročila', 'Stanje naročila'], 'name'=>'Ime', 'address'=>'Naslov', 'fields'=>[
    'first_name'=>'Ime', 'middle_name'=>'Drugo ime', 'last_name'=>'Priimek', 'address_1'=>'Ulica', 'address_2'=>'Dodatek', 'zip'=>'Pošta', 'city'=>'Mesto',
]];
$fixture = '<h1>Račun TEST</h1><table><tr><td>Datum računa</td><td>2026-11-12</td></tr>'
    . '<tr><td>Datum naročila</td><td>2026-11-11</td></tr><tr><td>Stanje naročila</td><td>Potrjeno</td></tr>'
    . '<tr><td>Skupaj</td><td>79,55 €</td></tr><tr><td><strong>Plačnik</strong><table>'
    . '<tr><td>Priimek</td><td>Žagar</td></tr><tr><td>Ime</td><td><em>Črt</em></td></tr>'
    . '<tr><td>Drugo ime</td><td>Janez</td></tr><tr><td>Ulica</td><td>Primer 15</td></tr>'
    . '<tr><td>Dodatek</td><td>SI-TEST</td></tr><tr><td>Pošta</td><td>5270</td></tr><tr><td>Mesto</td><td>Ajdovščina</td></tr>'
    . '<tr><td>Davčna št.</td><td><strong>KEEP-123</strong></td></tr></table></td>'
    . '<td><strong>Dostava</strong><br />Enako kot plačnik</td></tr></table><p>Other content</p>';
$modern = Invoice::compact($fixture, true, $context);
foreach (['<em>Črt</em> Janez Žagar', 'Primer 15, 5270 Ajdovščina', 'SI-TEST', '<strong>KEEP-123</strong>', 'Datum računa', '79,55 €', 'Other content'] as $value) {
    check(str_contains($modern, $value), 'Modern preserves ' . $value);
}
foreach (['Datum naročila', 'Stanje naročila', 'Enako kot plačnik', 'Dostava'] as $value) {
    check(!str_contains($modern, $value), 'Modern omits ' . $value);
}
$classic = Invoice::compact($fixture, false, $context);
foreach (['Datum naročila', 'Stanje naročila', 'Enako kot plačnik', 'Dostava', 'Priimek', 'Drugo ime', 'Mesto', 'Pošta'] as $value) {
    check(str_contains($classic, $value), 'Classic preserves ' . $value);
}
$partial = str_replace(['<tr><td>Priimek</td><td>Žagar</td></tr>', '<tr><td>Drugo ime</td><td>Janez</td></tr>', '<tr><td>Pošta</td><td>5270</td></tr>'], '', $fixture);
$partial = Invoice::compact($partial, true, $context);
check(str_contains($partial, '<em>Črt</em>'), 'Single name retained');
check(str_contains($partial, 'Primer 15, Ajdovščina'), 'Address retained without postcode');
check(str_contains($partial, 'SI-TEST'), 'Additional address line preserved');
echo "PASS: localized Modern omissions, full/partial names, postcode/city, native HTML and custom fields; Classic unchanged\n";

$plugin = '<table><tr><th>Date</th><th>Status</th><th>Comment</th></tr><tr><td>Plugin data</td></tr></table>';
$history = '<div id="tab-2" class="tabs" title="Zgodovina naročila"><table><tr><td><div>History comment</div></td></tr></table><div class="clear"></div></div>';
$before = '<div id="ui-tabs"><div id="tab-1" class="tabs" title="Items">' . $plugin . '</div>';
$html = $before . $history . '</div><p>Terms</p>';
check(Invoice::withoutHistory($html, 'Zgodovina naročila') === $before . '</div><p>Terms</p>', 'Only nested native history panel removed');
check(Invoice::withoutHistory($html, 'Other panel') === $html, 'Unrelated panel preserved');
check(Invoice::withoutHistory($plugin, 'Zgodovina naročila') === $plugin, 'Plugin table with matching headings preserved');
echo "PASS: localized history panel removal and unrelated tables preserved\n";
