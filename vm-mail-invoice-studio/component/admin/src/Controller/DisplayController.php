<?php
/**
 * @package VM Mail & Invoice Studio
 * @copyright Copyright (C) 2026 Darko Fatur / ToPowerYou
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Controller;

defined('_JEXEC') or die;


use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
use ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Service\Design;
use ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Service\DesignCatalog;
use ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Service\Invoice;
use ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Service\Overrides;

final class DisplayController extends BaseController
{
    private function access(bool $write = false): void
    {
        if (!$this->app->isClient('administrator') || !$this->app->getIdentity()->authorise($write ? 'core.admin' : 'core.manage', 'com_vmmailinvoicestudio')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    private function mutation(): void
    {
        $this->access(true);
        if (!Session::checkToken('post')) {
            throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }
    }

    public function display($cachable = false, $urlparams = []): self
    {
        $this->access();
        ToolbarHelper::title(Text::_('COM_VMMAILINVOICESTUDIO'), 'envelope');
        if ($this->app->getIdentity()->authorise('core.admin', 'com_vmmailinvoicestudio')) {
            ToolbarHelper::preferences('com_vmmailinvoicestudio');
        }
        $settings = Design::settings();
        $form = Form::getInstance('com_vmmailinvoicestudio.design', JPATH_COMPONENT_ADMINISTRATOR . '/forms/design.xml', ['control'=>'jform']);
        // Core designs are always listed; enabled packs add designs for each supported document type.
        $packNotices = [];
        foreach (['email_preset', 'invoice_preset'] as $name) {
            $kind = str_starts_with($name, 'email') ? 'email' : 'invoice';
            $presets = Design::presets($kind);
            $options = new \SimpleXMLElement('<field name="' . $name . '" type="list" label="COM_VMMAILINVOICESTUDIO_PRESET"/>');
            foreach ($presets as $id => $preset) {
                $label = $preset['name'] . ($preset['provider'] === 'core' ? '' : ' — ' . $preset['pack']);
                $options->addChild('option', htmlspecialchars($label, ENT_XML1))->addAttribute('value', $id);
            }
            if (!isset($presets[$settings[$name]])) {
                $label = Text::sprintf('COM_VMMAILINVOICESTUDIO_PACK_UNAVAILABLE', $settings[$name]);
                $options->addChild('option', htmlspecialchars($label, ENT_XML1))->addAttribute('value', $settings[$name]);
                $packNotices[] = Text::sprintf('COM_VMMAILINVOICESTUDIO_PACK_FALLBACK',
                    Text::_('COM_VMMAILINVOICESTUDIO_' . strtoupper($kind)), $settings[$name], ucfirst(DesignCatalog::fallback($kind)));
            }
            $form->setField($options, null, true, str_starts_with($name, 'email') ? 'email' : 'invoice');
        }
        if (DesignCatalog::current()->issues()) {
            $packNotices[] = Text::_('COM_VMMAILINVOICESTUDIO_PACK_ERROR');
        }
        $form->bind($settings);
        $templates = Overrides::templates();
        $canEdit = $this->app->getIdentity()->authorise('core.admin', 'com_vmmailinvoicestudio');
        $wa = $this->app->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('vmstudio', 'media/com_vmmailinvoicestudio/studio.css', ['version'=>'auto']);
        $wa->registerAndUseScript('vmstudio', 'media/com_vmmailinvoicestudio/studio.js', ['version'=>'auto'], ['defer'=>true]);
        include JPATH_COMPONENT_ADMINISTRATOR . '/tmpl/studio.php';
        return $this;
    }

    public function save(): void
    {
        $this->mutation();
        try {
            $saved = Design::settings();
            $data = Design::clean($this->input->post->get('jform', [], 'array'), $saved);
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $params = new Registry($saved);
            foreach ($data as $key => $value) {
                $params->set($key, $value);
            }
            $query = $db->getQuery(true)->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('params') . ' = ' . $db->quote($params->toString()))
                ->where($db->quoteName('element') . ' = ' . $db->quote('com_vmmailinvoicestudio'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'));
            $db->setQuery($query)->execute();
            // ComponentHelper caches extension parameters across requests in _system.
            // Invalidate that cache so both the designer and outgoing mail read the save.
            Factory::getContainer()->get(CacheControllerFactoryInterface::class)
                ->createCacheController('callback', ['defaultgroup' => '_system'])->clean();
            ComponentHelper::getComponent('com_vmmailinvoicestudio')->setParams($params);
            $this->app->enqueueMessage(Text::_('COM_VMMAILINVOICESTUDIO_SAVED'));
        } catch (\InvalidArgumentException $error) {
            $this->app->enqueueMessage(Text::_($error->getMessage()), 'error');
        }
        $this->setRedirect(Route::_('index.php?option=com_vmmailinvoicestudio', false));
    }

    public function apply(): void
    {
        $this->mutation();
        try {
            Overrides::apply($this->input->post->getString('template'));
            $this->app->enqueueMessage(Text::_('COM_VMMAILINVOICESTUDIO_APPLIED'));
        } catch (\RuntimeException $error) {
            $this->app->enqueueMessage(Text::_($error->getMessage()), 'error');
        }
        $this->setRedirect(Route::_('index.php?option=com_vmmailinvoicestudio', false));
    }

    public function remove(): void
    {
        $this->mutation();
        try {
            $ok = Overrides::remove($this->input->post->getString('template'));
            $this->app->enqueueMessage(Text::_($ok ? 'COM_VMMAILINVOICESTUDIO_REMOVED' : 'COM_VMMAILINVOICESTUDIO_PRESERVED'), $ok ? 'message' : 'warning');
        } catch (\RuntimeException $error) {
            $this->app->enqueueMessage(Text::_($error->getMessage()), 'error');
        }
        $this->setRedirect(Route::_('index.php?option=com_vmmailinvoicestudio', false));
    }

    public function preview(): void
    {
        $this->access();
        $kind = $this->input->getCmd('kind') === 'invoice' ? 'invoice' : 'email';
        $settings = Design::settings();
        $this->app->setHeader('Cache-Control', 'no-store', true);
        if ($this->input->getCmd('output') === 'pdf') {
            require_once JPATH_ROOT . '/administrator/components/com_virtuemart/helpers/config.php';
            \VirtueMart\VmConfig::loadConfig();
            require_once JPATH_ROOT . '/components/com_virtuemart/helpers/vmpdf.php';
            $pdf = new \VirtueMart\VmVendorPDF();
            Invoice::preparePdf($pdf);
            $pdf->SetTitle('VM Mail & Invoice Studio — SAMPLE');
            $pdf->AddPage();
            $pdf->PrintContents(Design::sample('invoice', $settings, true));
            $pdf->Output('vm-studio-sample.pdf', 'I');
        } else {
            $this->app->setHeader('Content-Type', 'text/html; charset=utf-8', true);
            $this->app->sendHeaders();
            echo Design::sample($kind, $settings);
        }
        $this->app->close();
    }
}
