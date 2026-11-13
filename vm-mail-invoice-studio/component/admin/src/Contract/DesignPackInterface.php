<?php
/**
 * @package VM Mail & Invoice Studio
 * @copyright Copyright (C) 2026 Darko Fatur / ToPowerYou
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace ToPowerYou\Component\Vmmailinvoicestudio\Administrator\Contract;

defined('_JEXEC') or die;

interface DesignPackInterface
{
    public const API_VERSION = 1;

    /** Return the API version, minimum core version, pack name and design definitions. */
    public function getDesignPack(): array;
}
