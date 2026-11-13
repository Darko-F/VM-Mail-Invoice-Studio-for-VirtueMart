<?php
/**
 * @package VM Mail & Invoice Studio
 * @copyright Copyright (C) 2026 Darko Fatur / ToPowerYou
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Service;

defined('_JEXEC') or die;


use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

final class Overrides
{
    public static function templates(): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)->select($db->quoteName('element'))->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('template'))
            ->where($db->quoteName('client_id') . ' = 0')->order($db->quoteName('element'));
        return $db->setQuery($query)->loadColumn();
    }

    private static function directory(string $template): string
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/D', $template) || !in_array($template, self::templates(), true)) {
            throw new \RuntimeException('COM_VMMAILINVOICESTUDIO_INVALID_TEMPLATE');
        }
        $base = realpath(JPATH_ROOT . '/templates');
        $path = realpath(JPATH_ROOT . '/templates/' . $template);
        if (!$base || !$path || !str_starts_with($path, $base . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('COM_VMMAILINVOICESTUDIO_INVALID_TEMPLATE');
        }
        // Refuse symlinked destination directories, including links within the template.
        foreach (['html', 'html/com_virtuemart', 'html/com_virtuemart/invoice'] as $part) {
            if (is_link($path . '/' . $part)) {
                throw new \RuntimeException('COM_VMMAILINVOICESTUDIO_OVERRIDE_CONFLICT');
            }
        }
        return $path . '/html/com_virtuemart/invoice';
    }

    public static function wrapper(string $layout): string
    {
        $kind = $layout === 'mail_html' ? 'email' : 'invoice';
        return "<?php\n/**\n * VM Mail & Invoice Studio managed wrapper v1\n * @copyright Copyright (C) 2026 Darko Fatur / ToPowerYou\n * @license GNU General Public License version 2 or later; see LICENSE.txt\n */\ndefined('_JEXEC') or die;\n"
            . "\$studio = JPATH_ADMINISTRATOR . '/components/com_vmmailinvoicestudio/src/Service/Design.php';\n"
            . "if (is_file(\$studio)) {\n    require_once \$studio;\n    \ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Service\Design::render(\$this, '$kind');\n} else {\n"
            . "    include JPATH_ROOT . '/components/com_virtuemart/views/invoice/tmpl/$layout.php';\n}\n";
    }

    public static function status(string $template): string
    {
        $dir = self::directory($template);
        $owned = 0;
        foreach (['mail_html', 'invoice'] as $layout) {
            $file = $dir . '/' . $layout . '.php';
            foreach (['bs2-', 'bs3-', 'bs4-', 'bs5-'] as $prefix) {
                if (file_exists($dir . '/' . $prefix . $layout . '.php')) {
                    return 'COM_VMMAILINVOICESTUDIO_CONFLICT';
                }
            }
            if (is_link($file) || (file_exists($file) && file_get_contents($file) !== self::wrapper($layout))) {
                return 'COM_VMMAILINVOICESTUDIO_CONFLICT';
            }
            $owned += is_file($file) ? 1 : 0;
        }
        return $owned === 2 ? 'COM_VMMAILINVOICESTUDIO_ACTIVE' : ($owned ? 'COM_VMMAILINVOICESTUDIO_PARTIAL' : 'COM_VMMAILINVOICESTUDIO_NOT_APPLIED');
    }

    public static function apply(string $template): void
    {
        $dir = self::directory($template);
        if (self::status($template) === 'COM_VMMAILINVOICESTUDIO_CONFLICT') {
            throw new \RuntimeException('COM_VMMAILINVOICESTUDIO_OVERRIDE_CONFLICT');
        }
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('COM_VMMAILINVOICESTUDIO_WRITE_FAILED');
        }
        $created = [];
        try {
            foreach (['mail_html', 'invoice'] as $layout) {
                $file = $dir . '/' . $layout . '.php';
                if (is_file($file)) {
                    continue;
                }
                $handle = fopen($file, 'x');
                if (!$handle) {
                    throw new \RuntimeException('COM_VMMAILINVOICESTUDIO_WRITE_FAILED');
                }
                $created[] = $file;
                $content = self::wrapper($layout);
                try {
                    if (fwrite($handle, $content) !== strlen($content)) {
                        throw new \RuntimeException('COM_VMMAILINVOICESTUDIO_WRITE_FAILED');
                    }
                } finally {
                    fclose($handle);
                }
            }
        } catch (\Throwable $error) {
            foreach ($created as $file) {
                unlink($file);
            }
            throw $error;
        }
    }

    public static function remove(string $template): bool
    {
        $dir = self::directory($template);
        $preserved = false;
        foreach (['mail_html', 'invoice'] as $layout) {
            $file = $dir . '/' . $layout . '.php';
            if (!file_exists($file) && !is_link($file)) {
                continue;
            }
            if (!is_link($file) && file_get_contents($file) === self::wrapper($layout)) {
                if (!unlink($file)) {
                    throw new \RuntimeException('COM_VMMAILINVOICESTUDIO_WRITE_FAILED');
                }
            } else {
                $preserved = true;
            }
        }
        return !$preserved;
    }
}
