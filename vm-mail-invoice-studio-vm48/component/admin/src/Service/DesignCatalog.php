<?php
/**
 * @package VM Mail & Invoice Studio
 * @copyright Copyright (C) 2026 Darko Fatur / ToPowerYou
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Service;

defined('_JEXEC') or die;

require_once dirname(__DIR__) . '/Contract/DesignPackInterface.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\PluginHelper;
use ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Contract\DesignPackInterface;

final class DesignCatalog
{
    public const CORE_VERSION = '1.0.1';
    public const CONTENT_TOKEN = '{{vmstudio_content}}';
    private static ?self $instance = null;
    private array $designs = [];
    private array $issues = [];

    public function __construct()
    {
        // These two IDs are owned by core, even when every pack is removed.
        foreach (['classic', 'modern'] as $id) {
            $modern = $id === 'modern';
            $defaults = ['name'=>ucfirst($id), 'background'=>$modern ? '#edf4f2' : '#f2f4f5',
                'border'=>$modern ? '#d1e2df' : '#d4dce1', 'padding'=>$modern ? 32 : 24];
            $path = dirname(__DIR__, 2) . '/presets/' . $id . '.json';
            $preset = is_file($path) ? json_decode(file_get_contents($path), true) : [];
            $preset = is_array($preset) ? $preset : [];
            $this->designs[$id] = array_replace($defaults, $preset, [
                'id'=>$id, 'name'=>ucfirst($id), 'provider'=>'core', 'pack'=>'',
                'kinds'=>['email', 'invoice'], 'base'=>$id, 'heading_style'=>$modern ? 'filled' : 'plain',
                'invoice_layout'=>$id, 'renderers'=>[],
            ]);
        }
    }

    public static function current(): self
    {
        if (self::$instance !== null) {
            return self::$instance;
        }
        // Assign before booting plugins to avoid recursive catalogue discovery.
        $catalog = self::$instance = new self;
        if (!class_exists(PluginHelper::class)) {
            return $catalog; // Also permits the standalone PDF/test tools.
        }
        try {
            $plugins = PluginHelper::getPlugin('vmstudiodesign');
        } catch (\Throwable $error) {
            $catalog->report('discovery', $error);
            return $catalog;
        }
        foreach ($plugins as $plugin) {
            $element = (string) ($plugin->name ?? '');
            try {
                if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $element)) {
                    throw new \InvalidArgumentException('Invalid plugin element.');
                }
                // Boot each enabled provider separately: one broken pack cannot hide the others.
                // Accidental output from a provider must never enter an email or PDF stream.
                $bufferLevel = ob_get_level();
                $output = '';
                ob_start();
                try {
                    $provider = Factory::getApplication()->bootPlugin($element, 'vmstudiodesign');
                    if (!$provider instanceof DesignPackInterface) {
                        throw new \InvalidArgumentException('Design pack API is not implemented.');
                    }
                    $pack = $provider->getDesignPack();
                } finally {
                    while (ob_get_level() > $bufferLevel) {
                        $output .= ob_get_clean();
                    }
                }
                if ($output !== '') {
                    throw new \UnexpectedValueException('Design pack provider printed output.');
                }
                $catalog->register($element, $pack);
            } catch (\Throwable $error) {
                $catalog->report($element, $error);
            }
        }
        return $catalog;
    }

    public function register(string $element, array $pack): void
    {
        if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $element)
            || ($pack['api'] ?? null) !== DesignPackInterface::API_VERSION
            || !is_string($pack['name'] ?? null) || trim($pack['name']) === '' || strlen($pack['name']) > 120
            || !is_string($pack['min_core'] ?? null) || !preg_match('/^\d+\.\d+\.\d+$/D', $pack['min_core'])
            || version_compare(self::CORE_VERSION, $pack['min_core'], '<')
            || !is_array($pack['designs'] ?? null) || !$pack['designs']) {
            throw new \InvalidArgumentException('Invalid or incompatible design pack.');
        }
        $pending = [];
        foreach ($pack['designs'] as $localId => $design) {
            if (!is_string($localId) || !preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $localId)
                || !is_array($design) || !is_string($design['name'] ?? null) || trim($design['name']) === ''
                || strlen($design['name']) > 120 || !in_array($design['base'] ?? null, ['classic', 'modern'], true)
                || !is_array($design['kinds'] ?? null) || !$design['kinds']) {
                throw new \InvalidArgumentException('Invalid design definition.');
            }
            foreach ($design['kinds'] as $kind) {
                if (!in_array($kind, ['email', 'invoice'], true)) {
                    throw new \InvalidArgumentException('Invalid design document kind.');
                }
            }
            $id = $element . '.' . $localId;
            if (isset($this->designs[$id])) {
                throw new \InvalidArgumentException('Duplicate design ID.');
            }
            $definition = $this->designs[$design['base']];
            $styles = array_key_exists('styles', $design) ? $design['styles'] : [];
            if (!is_array($styles) || array_diff(array_keys($styles), ['background', 'border', 'padding', 'heading_style', 'invoice_layout'])) {
                throw new \InvalidArgumentException('Unsupported design styles.');
            }
            foreach (['background', 'border'] as $key) {
                if (array_key_exists($key, $styles) && (!is_string($styles[$key]) || !preg_match('/^#[a-fA-F0-9]{6}$/D', $styles[$key]))) {
                    throw new \InvalidArgumentException('Invalid design color.');
                }
            }
            if ((array_key_exists('padding', $styles) && (!is_int($styles['padding']) || $styles['padding'] < 12 || $styles['padding'] > 48))
                || (array_key_exists('heading_style', $styles) && !in_array($styles['heading_style'], ['plain', 'filled'], true))
                || (array_key_exists('invoice_layout', $styles) && !in_array($styles['invoice_layout'], ['classic', 'modern'], true))) {
                throw new \InvalidArgumentException('Invalid design layout.');
            }
            $renderers = array_key_exists('renderers', $design) ? $design['renderers'] : [];
            if (!is_array($renderers) || array_diff(array_keys($renderers), $design['kinds'])) {
                throw new \InvalidArgumentException('Invalid design renderers.');
            }
            foreach ($renderers as $renderer) {
                if (!is_callable($renderer)) {
                    throw new \InvalidArgumentException('Design renderer is not callable.');
                }
            }
            $contentRenderer = $design['content_renderer'] ?? null;
            if ($contentRenderer !== null && !is_callable($contentRenderer)) {
                throw new \InvalidArgumentException('Invalid content renderer.');
            }
            $pending[$id] = array_replace($definition, $styles, [
                'id'=>$id, 'name'=>$design['name'], 'provider'=>$element, 'pack'=>$pack['name'],
                'content_renderer'=>$contentRenderer,
                'base'=>$design['base'], 'kinds'=>array_values(array_unique($design['kinds'])), 'renderers'=>$renderers,
            ]);
        }
        // Publish only complete, valid packs. Registration cannot overwrite core IDs.
        $this->designs += $pending;
    }

    public function all(?string $kind = null): array
    {
        return $kind === null ? $this->designs : array_filter($this->designs, static fn(array $design): bool => in_array($kind, $design['kinds'], true));
    }

    public static function fallback(string $kind): string
    {
        return $kind === 'email' ? 'modern' : 'classic';
    }

    public function resolve(string $id, string $kind): array
    {
        $design = $this->designs[$id] ?? null;
        return $design && in_array($kind, $design['kinds'], true) ? $design : $this->designs[self::fallback($kind)];
    }

    public function issues(): array
    {
        return $this->issues;
    }

    public function report(string $provider, \Throwable $error): void
    {
        $this->issues[$provider] = $provider;
        // Never log document contents or customer data, nor emit errors into a PDF/email.
        if (class_exists(Log::class)) {
            try {
                Log::add('Studio design pack ' . $provider . ' failed (' . get_class($error) . ').', Log::WARNING, 'vmstudiodesign');
            } catch (\Throwable $ignored) {
                // A logging failure must not interrupt document generation.
            }
        }
    }
}
