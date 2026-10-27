<?php
/**
 * Claude Connector by Piskee
 *
 * @author    Piskee <louisalexandre@piskee.com>
 * @copyright 2026 Piskee
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License v3.0 (AFL-3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * 2.0.1 adds the consent page as a back office route: rebuild the Symfony
 * cache so PrestaShop sees it.
 *
 * @param Piskeeconnector $module
 */
function upgrade_module_2_0_1($module)
{
    Piskeeconnector::refreshRoutes();

    return true;
}
