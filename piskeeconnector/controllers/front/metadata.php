<?php
/**
 * Claude Connector by Piskee
 *
 * @author    Piskee <louisalexandre@piskee.com>
 * @copyright 2026 Piskee
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License v3.0 (AFL-3.0)
 */
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * OAuth discovery documents, also served at the .well-known addresses
 * declared in the module routes.
 */
class PiskeeconnectorMetadataModuleFrontController extends ModuleFrontController
{
    /** @var bool */
    public $ssl = true;

    /** @var bool */
    public $ajax = true;

    protected function displayMaintenancePage()
    {
    }

    public function initContent()
    {
        if (PiskeeConnectorHttp::method() === 'OPTIONS') {
            PiskeeConnectorHttp::send(204, [], '');
        }
        $type = (string) Tools::getValue('type', 'server');
        if ($type === 'resource') {
            PiskeeConnectorHttp::json(PiskeeConnectorOAuth::protectedResourceMetadata(), 200, ['Cache-Control' => 'public, max-age=3600']);
        }
        PiskeeConnectorHttp::json(PiskeeConnectorOAuth::authorizationServerMetadata(), 200, ['Cache-Control' => 'public, max-age=3600']);
    }
}
