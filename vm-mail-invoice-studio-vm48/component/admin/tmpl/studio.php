<?php
/**
 * @package VM Mail & Invoice Studio
 * @copyright Copyright (C) 2026 Darko Fatur / ToPowerYou
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die;


use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Service\Design;
use ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Service\Overrides;
$e = [Design::class, 'escape'];
$previewBase = 'index.php?option=com_vmmailinvoicestudio&task=preview&format=raw&kind=';
?>
<div class="vmstudio">
    <div class="vms-hero">
        <img src="<?php echo $e(Uri::root(true) . '/media/com_vmmailinvoicestudio/logo.svg'); ?>" width="64" height="64" alt="">
        <div><p class="vms-eyebrow">VIRTUEMART · DESIGN STUDIO</p><h2><?php echo Text::_('COM_VMMAILINVOICESTUDIO_HEADLINE'); ?></h2><p><?php echo Text::_('COM_VMMAILINVOICESTUDIO_SUBTITLE'); ?></p></div>
        <span class="vms-version">1.0.0</span>
    </div>
    <div class="alert alert-info"><?php echo Text::_('COM_VMMAILINVOICESTUDIO_NATIVE_NOTICE'); ?></div>
    <?php foreach ($packNotices as $notice) : ?><div class="alert alert-warning"><?php echo $e($notice); ?></div><?php endforeach; ?>
    <div class="vms-workspace">
        <section class="vms-panel">
            <h3><?php echo Text::_('COM_VMMAILINVOICESTUDIO_DESIGN'); ?></h3>
            <p class="text-muted"><?php echo Text::_('COM_VMMAILINVOICESTUDIO_PACKS_NOTE'); ?></p>
            <form action="<?php echo Route::_('index.php?option=com_vmmailinvoicestudio'); ?>" method="post" id="adminForm" name="adminForm">
                <fieldset <?php echo !$canEdit ? 'disabled' : ''; ?>>
                    <?php foreach (['branding','email','invoice'] as $section) : ?>
                    <details <?php echo $section === 'branding' ? 'open' : ''; ?>><summary><?php echo Text::_('COM_VMMAILINVOICESTUDIO_' . strtoupper($section)); ?></summary><div class="vms-fields"><?php echo $form->renderFieldset($section); ?></div></details>
                    <?php endforeach; ?>
                    <button type="submit" class="btn btn-primary vms-save"><?php echo Text::_('JSAVE'); ?></button>
                </fieldset>
                <input type="hidden" name="task" value="save">
                <?php echo HTMLHelper::_('form.token'); ?>
            </form>
        </section>
        <section class="vms-panel vms-preview">
            <div class="vms-preview-toolbar"><h3><?php echo Text::_('COM_VMMAILINVOICESTUDIO_PREVIEW'); ?></h3>
                <div><button type="button" class="btn btn-outline-secondary vms-preview-button" data-preview="<?php echo $e(Route::_($previewBase . 'email', false)); ?>"><?php echo Text::_('COM_VMMAILINVOICESTUDIO_EMAIL'); ?></button>
                <button type="button" class="btn btn-outline-secondary vms-preview-button" data-preview="<?php echo $e(Route::_($previewBase . 'invoice', false)); ?>"><?php echo Text::_('COM_VMMAILINVOICESTUDIO_INVOICE'); ?></button>
                <a class="btn btn-outline-primary" target="_blank" rel="noopener" href="<?php echo $e(Route::_($previewBase . 'invoice&output=pdf', false)); ?>"><?php echo Text::_('COM_VMMAILINVOICESTUDIO_PDF'); ?></a></div>
            </div>
            <p class="text-muted"><?php echo Text::_('COM_VMMAILINVOICESTUDIO_PREVIEW_NOTE'); ?></p>
            <iframe id="vms-preview" title="<?php echo Text::_('COM_VMMAILINVOICESTUDIO_PREVIEW'); ?>" sandbox="" src="<?php echo $e(Route::_($previewBase . 'email', false)); ?>"></iframe>
        </section>
    </div>
    <section class="vms-panel vms-activation">
        <h3><?php echo Text::_('COM_VMMAILINVOICESTUDIO_TEMPLATES'); ?></h3>
        <p><?php echo Text::_('COM_VMMAILINVOICESTUDIO_TEMPLATES_NOTE'); ?></p>
        <div class="table-responsive"><table class="table"><thead><tr><th><?php echo Text::_('COM_VMMAILINVOICESTUDIO_TEMPLATE'); ?></th><th><?php echo Text::_('JSTATUS'); ?></th><th><?php echo Text::_('JACTIONS'); ?></th></tr></thead><tbody>
        <?php foreach ($templates as $template) : ?>
            <tr><td><?php echo $e($template); ?></td><td><?php echo Text::_(Overrides::status($template)); ?></td><td>
            <?php if ($canEdit) : ?><form method="post" action="<?php echo Route::_('index.php?option=com_vmmailinvoicestudio'); ?>" class="vms-inline">
                <input type="hidden" name="template" value="<?php echo $e($template); ?>">
                <button class="btn btn-sm btn-outline-primary" name="task" value="apply"><?php echo Text::_('COM_VMMAILINVOICESTUDIO_APPLY'); ?></button>
                <button class="btn btn-sm btn-outline-danger" name="task" value="remove"><?php echo Text::_('COM_VMMAILINVOICESTUDIO_REMOVE'); ?></button>
                <?php echo HTMLHelper::_('form.token'); ?></form><?php endif; ?>
            </td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </section>
    <p class="vms-footnote"><?php echo Text::_('COM_VMMAILINVOICESTUDIO_FOOTNOTE'); ?></p>
</div>
