<?php
/** Copyright (C) 2026 ToPowerYou. @license GNU General Public License version 2 or later */
namespace Joomla\CMS\Plugin {
    class PluginHelper {
        public static array $enabled = [];
        public static array $groups = [];
        public static bool $fail = false;
        public static function getPlugin($group) {
            self::$groups[] = $group;
            if (self::$fail) { throw new \RuntimeException('Sensitive customer data'); }
            return array_map(static fn($name) => (object) ['name'=>$name], self::$enabled);
        }
    }
}
namespace Joomla\CMS\Log {
    class Log {
        public const WARNING = 4;
        public static array $messages = [];
        public static function add($message, $level, $category) { self::$messages[] = $message; }
    }
}
namespace Joomla\CMS\Uri {
    class Uri { public static function root() { return 'https://example.test/shop/'; } }
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
    require dirname(__DIR__) . '/component/admin/src/Service/Design.php';
    use Joomla\CMS\Factory;
    use Joomla\CMS\Log\Log;
    use Joomla\CMS\Plugin\PluginHelper;
    use ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Contract\DesignPackInterface;
    use ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Service\Design;
    use ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Service\DesignCatalog;

    final class TestProvider implements DesignPackInterface {
        private \Closure $definition;
        public function __construct(callable $definition) { $this->definition = \Closure::fromCallable($definition); }
        public function getDesignPack(): array { return ($this->definition)(); }
    }
    final class TestApplication {
        public array $providers = [];
        public array $booted = [];
        public function bootPlugin($element, $group) {
            $this->booted[] = [$element, $group];
            $provider = $this->providers[$element] ?? null;
            return $provider instanceof \Closure ? $provider() : $provider;
        }
    }
    $count = 0;
    function check(bool $condition, string $message): void {
        global $count;
        ++$count;
        if (!$condition) { throw new \RuntimeException($message); }
    }
    function rejects(callable $action, string $message): void {
        try { $action(); } catch (\InvalidArgumentException $error) { check(true, $message); return; }
        check(false, $message);
    }
    function resetCatalog(): void {
        $property = new \ReflectionProperty(DesignCatalog::class, 'instance');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }
    function definition(array $changes = []): array {
        return array_replace(['name'=>'Sample design', 'base'=>'modern', 'kinds'=>['email', 'invoice']], $changes);
    }
    function designPack(array $designs, array $changes = []): array {
        return array_replace(['api'=>1, 'min_core'=>'1.0.0', 'name'=>'Sample pack', 'designs'=>$designs], $changes);
    }
    function provider(array $pack): TestProvider { return new TestProvider(static fn() => $pack); }
    function frame(string $body, string $kind, string $id, array $context = []): string {
        return Design::frame($body, $kind, array_replace(Design::defaults(), [
            $kind . '_preset'=>$id, 'accent'=>'#2455aa', $kind . '_intro'=>'Welcome <customer>',
            $kind . '_closing'=>'Thank you & goodbye',
        ]), 'Shop & Sons', $kind === 'invoice', $context);
    }
    // Warnings are failures too: malformed installed packs must not print into mail or PDF output.
    set_error_handler(static function ($severity, $message, $file, $line): bool {
        if (!(error_reporting() & $severity)) { return false; }
        throw new \ErrorException($message, 0, $severity, $file, $line);
    });
    Factory::$application = $application = new TestApplication;
    $base = Design::defaults();
    $catalog = DesignCatalog::current();
    check(array_keys($catalog->all()) === ['classic', 'modern'], 'Core always starts with Classic and Modern');
    foreach (['email', 'invoice'] as $kind) {
        check(array_keys($catalog->all($kind)) === ['classic', 'modern'], 'Both core designs support ' . $kind);
        check($catalog->resolve('missing.design', $kind)['id'] === DesignCatalog::fallback($kind), 'Missing pack gets bundled fallback for ' . $kind);
    }
    check(PluginHelper::$groups === ['vmstudiodesign'], 'Discovery uses only its own plugin group');
    $builtins = $catalog->all();
    $baseline = [];
    foreach (['email', 'invoice'] as $kind) {
        foreach (['classic', 'modern'] as $id) {
            $baseline[$kind][$id] = Design::sample($kind, array_replace($base, [$kind . '_preset'=>$id]));
        }
    }

    // Plugin-local names are namespaced, including names that match a bundled design.
    $catalog->register('alpha', designPack(['classic'=>definition(), 'modern'=>definition()]));
    $catalog->register('beta', designPack(['classic'=>definition(['kinds'=>['invoice']])]));
    check(isset($catalog->all()['alpha.classic'], $catalog->all()['beta.classic']), 'Two providers may use the same local name');
    check($catalog->all()['classic'] === $builtins['classic'] && $catalog->all()['modern'] === $builtins['modern'], 'Plugin local names cannot replace core');
    check(!isset(Design::presets('email')['beta.classic']) && isset(Design::presets('invoice')['beta.classic']), 'Selectors filter by supported document kind');
    check($catalog->resolve('beta.classic', 'email')['id'] === 'modern', 'Unsupported kind falls back to core');
    rejects(fn() => $catalog->register('alpha', designPack(['classic'=>definition()])), 'Duplicate namespaced IDs rejected');
    check($catalog->all()['alpha.classic']['provider'] === 'alpha', 'Duplicate registration leaves existing provider intact');

    $invalidPacks = [
        designPack(['test'=>definition(['content_renderer'=>'missing_function'])]),
        designPack(['test'=>definition()], ['api'=>'1']),
        designPack(['test'=>definition()], ['api'=>2]),
        designPack(['test'=>definition()], ['min_core'=>'1.0.2']),
        designPack(['test'=>definition()], ['min_core'=>'anything']),
        designPack(['test'=>definition()], ['name'=>'']),
        designPack([]),
        designPack(['../test'=>definition()]),
        designPack(['test'=>definition(['name'=>''])]),
        designPack(['test'=>definition(['base'=>'alpha.classic'])]),
        designPack(['test'=>definition(['kinds'=>[]])]),
        designPack(['test'=>definition(['kinds'=>['deliverynote']])]),
        designPack(['test'=>definition(['kinds'=>[['email']]])]),
        designPack(['test'=>definition(['styles'=>['unexpected'=>'value']])]),
        designPack(['test'=>definition(['styles'=>['background'=>'red']])]),
        designPack(['test'=>definition(['styles'=>['border'=>'#fff;display:none']])]),
        designPack(['test'=>definition(['styles'=>['padding'=>'20']])]),
        designPack(['test'=>definition(['styles'=>['padding'=>49]])]),
        designPack(['test'=>definition(['styles'=>['padding'=>null]])]),
        designPack(['test'=>definition(['styles'=>['heading_style'=>'arbitrary']])]),
        designPack(['test'=>definition(['styles'=>['invoice_layout'=>'arbitrary']])]),
        designPack(['test'=>definition(['renderers'=>['email'=>'missing_function']])]),
        designPack(['test'=>definition(['kinds'=>['email'], 'renderers'=>['invoice'=>static fn() => 'unused']])]),
    ];
    foreach ($invalidPacks as $index => $invalid) {
        $before = $catalog->all();
        rejects(fn() => $catalog->register('invalid', $invalid), 'Invalid pack rejected: ' . $index);
        check($catalog->all() === $before, 'Invalid pack leaves catalog unchanged: ' . $index);
    }
    rejects(fn() => $catalog->register('../invalid', designPack(['test'=>definition()])), 'Traversal in plugin element rejected');
    rejects(fn() => $catalog->register('atomic', designPack(['valid'=>definition(), 'broken'=>definition(['base'=>'absent'])])), 'Whole pack is rejected on invalid second design');
    check(!isset($catalog->all()['atomic.valid']), 'No partial design survives failed registration');

    // Simulate Joomla's enabled provider list and each independent failure boundary.
    resetCatalog();
    PluginHelper::$fail = true;
    check(array_keys(DesignCatalog::current()->all()) === ['classic', 'modern'], 'Failed plugin discovery still supplies core designs');
    check(isset(DesignCatalog::current()->issues()['discovery']), 'Discovery problem recorded');
    PluginHelper::$fail = false;
    $application->providers = [
        'invalidtype'=>new \stdClass,
        'throwboot'=>static function () { throw new \RuntimeException('Sensitive customer data'); },
        'throwdefinition'=>new TestProvider(static function (): array { throw new \RuntimeException('Sensitive customer data'); }),
        'printboot'=>static function () { echo 'BOOT LEAK'; return provider(designPack(['test'=>definition()])); },
        'printdefinition'=>new TestProvider(static function (): array { echo 'DEFINITION LEAK'; return designPack(['test'=>definition()]); }),
        'nestedprint'=>new TestProvider(static function (): array { echo 'OUTER LEAK'; ob_start(); return designPack(['test'=>definition()]); }),
        'invalid'=>provider(designPack(['test'=>definition()], ['api'=>2])),
        'atlas'=>provider(designPack(['ribbon'=>definition()])),
        'beta'=>provider(designPack(['ribbon'=>definition(['kinds'=>['invoice']])])),
        'disabled'=>provider(designPack(['hidden'=>definition()])),
        'recursive'=>static function () {
            check(isset(DesignCatalog::current()->all()['classic']), 'Reentrant plugin boot sees seeded core designs');
            return provider(designPack(['test'=>definition()]));
        },
    ];
    PluginHelper::$enabled = ['../invalid', 'invalidtype', 'throwboot', 'throwdefinition', 'printboot', 'printdefinition', 'nestedprint', 'invalid', 'atlas', 'beta', 'recursive'];
    resetCatalog();
    ob_start();
    $catalog = DesignCatalog::current();
    $leak = ob_get_clean();
    check($leak === '', 'Plugin discovery never emits printed output');
    foreach (['../invalid', 'invalidtype', 'throwboot', 'throwdefinition', 'printboot', 'printdefinition', 'nestedprint', 'invalid'] as $id) {
        check(isset($catalog->issues()[$id]), 'Broken provider recorded independently: ' . $id);
        check(!isset($catalog->all()[$id . '.test']), 'Broken provider cannot register design: ' . $id);
    }
    check(isset($catalog->all()['atlas.ribbon'], $catalog->all()['beta.ribbon'], $catalog->all()['recursive.test']), 'Healthy providers after failures remain available');
    check(!isset($catalog->all()['disabled.hidden']), 'Disabled plugins never appear');
    check(!in_array(['../invalid', 'vmstudiodesign'], $application->booted, true), 'Invalid element never reaches Joomla boot');
    check(!str_contains(implode('\n', Log::$messages), 'Sensitive customer data'), 'Failure logs do not contain exception payloads');
    foreach ($application->booted as $boot) { check($boot[1] === 'vmstudiodesign', 'Each provider boots from the correct group'); }
    foreach ($baseline as $kind => $designs) {
        foreach ($designs as $id => $expected) {
            check(Design::sample($kind, array_replace($base, [$kind . '_preset'=>$id])) === $expected, 'Installed plugins do not change bundled ' . $kind . '/' . $id);
        }
    }

    // Saved pack IDs survive disabling the pack; installing or re-enabling restores them.
    $saved = array_replace($base, ['email_preset'=>'atlas.ribbon', 'invoice_preset'=>'atlas.ribbon']);
    check(Design::clean($saved) === $saved, 'Installed pack can be selected and saved');
    rejects(fn() => Design::clean(array_replace($base, ['email_preset'=>'beta.ribbon'])), 'New selection of unsupported kind rejected');
    PluginHelper::$enabled = [];
    resetCatalog();
    check(Design::clean($saved, $saved) === $saved, 'Disabled pack selection preserved through unrelated save');
    rejects(fn() => Design::clean($saved), 'New unknown selection is rejected');
    $unsafe = array_replace($saved, ['invoice_preset'=>'../arbitrary']);
    rejects(fn() => Design::clean($unsafe, $unsafe), 'Unsafe saved ID is not an exception to validation');
    foreach (['email', 'invoice'] as $kind) {
        check(Design::sample($kind, $saved) === Design::sample($kind, array_replace($saved, [$kind . '_preset'=>DesignCatalog::fallback($kind)])), 'Disabled pack falls back for ' . $kind);
    }
    PluginHelper::$enabled = ['atlas'];
    resetCatalog();
    check(DesignCatalog::current()->resolve($saved['invoice_preset'], 'invoice')['id'] === 'atlas.ribbon', 'Re-enabling pack restores saved selection');
    check(Design::clean($saved) === $saved, 'Re-enabled selection remains valid without resaving');

    $catalog = DesignCatalog::current();
    $received = [];
    $renderer = static function (array $context) use (&$received): string {
        $received[] = $context;
        return '<section data-pack="custom" data-kind="' . $context['kind'] . '" style="color:' . $context['accent'] . '">'
            . $context['heading_html'] . $context['content_token'] . '</section>';
    };
    $catalog->register('custom', designPack(['ribbon'=>definition(['renderers'=>['email'=>$renderer, 'invoice'=>$renderer]])]));
    $native = '<p>Invoice NATIVE-54321</p><p>Ajdovščina Črt Žagar</p><strong>Tax 22%: 22.00 EUR; total 122.00 EUR</strong>';
    foreach (['email', 'invoice'] as $kind) {
        $output = frame($native, $kind, 'custom.ribbon');
        check(str_contains($output, 'data-pack="custom"'), 'Custom renderer called for ' . $kind);
        check(substr_count($output, $native) === 1, 'Original native content included exactly once for ' . $kind);
        check(!str_contains($output, DesignCatalog::CONTENT_TOKEN), 'Content token replaced for ' . $kind);
        check(str_contains($output, 'Shop &amp; Sons') && str_contains($output, 'Welcome &lt;customer&gt;'), 'Core escapes heading and intro for ' . $kind);
        check(str_contains($output, 'Thank you &amp; goodbye'), 'Core preserves escaped closing for ' . $kind);
        check(str_contains($output, '#2455aa'), 'Renderer receives current saved accent for ' . $kind);
        $sample = Design::sample($kind, array_replace($base, [$kind . '_preset'=>'custom.ribbon']));
        check(str_contains($sample, 'data-pack="custom"') && str_contains($sample, 'Example product') && str_contains($sample, '122.00 EUR'), 'Preview uses custom renderer and native sample values for ' . $kind);
    }
    check($received[0]['api'] === 1 && $received[0]['design_id'] === 'custom.ribbon', 'Renderer receives API and resolved design ID');
    check($received[0]['pdf'] === false && $received[2]['pdf'] === true, 'Renderer receives correct PDF mode');

    foreach (['empty'=>static fn() => '', 'throws'=>static function () { throw new \RuntimeException('Failure'); },
        'prints'=>static function ($context) { echo 'unexpected'; return $context['body_html']; }] as $id=>$transform) {
        $catalog->register('content' . $id, designPack(['test'=>definition([
            'content_renderer'=>$transform, 'renderers'=>['email'=>$renderer, 'invoice'=>$renderer],
        ])]));
        foreach (['email','invoice'] as $kind) {
            check(frame($native,$kind,'content'.$id.'.test') === frame($native,$kind,DesignCatalog::fallback($kind)), 'Content renderer failure safely restores native body: '.$id.' '.$kind);
        }
    }

    // Failed Modern renderers must restart from the original body, otherwise Classic would lose fields/history.
    $context = ['omit'=>['Order date', 'Order status'], 'name'=>'Name', 'address'=>'Address',
        'fields'=>['first_name'=>'First name', 'last_name'=>'Last name', 'address_1'=>'Address 1', 'zip'=>'Postcode', 'city'=>'City'],
        'history'=>'Order history'];
    $invoice = '<h1>Invoice NATIVE-54321</h1><table><tr><td>Order date</td><td>2026-11-12</td></tr>'
        . '<tr><td>Order status</td><td>Confirmed</td></tr><tr><td>Total</td><td>122.00 EUR</td></tr>'
        . '<tr><td><strong>Billing details</strong><table><tr><td>First name</td><td>Črt</td></tr>'
        . '<tr><td>Last name</td><td>Žagar</td></tr><tr><td>Address 1</td><td>Street 15</td></tr>'
        . '<tr><td>Postcode</td><td>5270</td></tr><tr><td>City</td><td>Ajdovščina</td></tr></table></td>'
        . '<td><strong>Ship to</strong><br />Shipping retained</td></tr></table>'
        . '<div id="tab-2" class="tabs" title="Order history"><p>History retained</p></div>'
        . '<table class="html-email" cellpadding="0"><tr><td>Native item + tax + price</td></tr></table>';
    $modern = frame($invoice, 'invoice', 'modern', $context);
    check(!str_contains($modern, 'History retained') && !str_contains($modern, 'Shipping retained') && !str_contains($modern, 'Order status'), 'Fixture exercises Modern-only removal before renderer');
    $catalog->register('tokens', designPack(['plain'=>definition(['styles'=>[
        'heading_style'=>'plain', 'invoice_layout'=>'classic', 'padding'=>18, 'background'=>'#eeeeee', 'border'=>'#123456',
    ]])]));
    $styledEmail = frame($native, 'email', 'tokens.plain');
    check(str_contains($styledEmail, 'background:#eeeeee') && str_contains($styledEmail, 'cellpadding="18"') && str_contains($styledEmail, 'solid #123456'), 'A pack can use validated style tokens without a custom renderer');
    $styledInvoice = frame($invoice, 'invoice', 'tokens.plain', $context);
    check(str_contains($styledInvoice, 'History retained') && str_contains($styledInvoice, 'Shipping retained') && str_contains($styledInvoice, 'Order status'), 'Pack invoice layout token controls native field policy independently of base');
    $brokenRenderers = [
        'throws'=>static function (): string { throw new \RuntimeException('Sensitive customer data'); },
        'missing'=>static fn() => '<p>No content marker</p>',
        'duplicate'=>static fn() => DesignCatalog::CONTENT_TOKEN . DesignCatalog::CONTENT_TOKEN,
        'notstring'=>static fn() => ['html'=>DesignCatalog::CONTENT_TOKEN],
        'prints'=>static function (): string { echo 'RENDER LEAK'; return DesignCatalog::CONTENT_TOKEN; },
        'nestedprints'=>static function (): string { echo 'OUTER RENDER LEAK'; ob_start(); return DesignCatalog::CONTENT_TOKEN; },
        'printthrows'=>static function (): string { echo 'THROW LEAK'; throw new \RuntimeException('Sensitive customer data'); },
    ];
    foreach ($brokenRenderers as $id => $broken) {
        $catalog->register('broken_' . $id, designPack(['ribbon'=>definition(['renderers'=>['email'=>$broken, 'invoice'=>$broken]])]));
        foreach (['email', 'invoice'] as $kind) {
            $body = $kind === 'invoice' ? $invoice : $native;
            $beforeLevel = ob_get_level();
            ob_start();
            $actual = frame($body, $kind, 'broken_' . $id . '.ribbon', $context);
            $printed = ob_get_clean();
            check($printed === '' && ob_get_level() === $beforeLevel, 'Failed renderer output discarded without leaking buffer: ' . $id . '/' . $kind);
            check($actual === frame($body, $kind, DesignCatalog::fallback($kind), $context), 'Failed renderer matches bundled fallback byte for byte: ' . $id . '/' . $kind);
        }
        check(isset($catalog->issues()['broken_' . $id]), 'Renderer failure recorded: ' . $id);
    }
    check(!str_contains(implode('\n', Log::$messages), 'Sensitive customer data'), 'Renderer exception payloads are not logged');
    $classic = frame($invoice, 'invoice', 'classic', $context);
    foreach (['Order date', 'Order status', 'Shipping retained', 'History retained', 'Črt', 'Žagar', 'Ajdovščina', '122.00 EUR'] as $value) {
        check(str_contains($classic, $value), 'Classic fallback preserves ' . $value);
    }
    restore_error_handler();
    echo "PASS: $count design-pack assertions (discovery, isolation, validation, selection lifecycle, native content and fallback)\n";
}
