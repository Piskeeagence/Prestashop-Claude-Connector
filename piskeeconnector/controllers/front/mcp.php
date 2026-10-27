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
 * The MCP endpoint: https://shop/module/piskeeconnector/mcp
 */
class PiskeeconnectorMcpModuleFrontController extends ModuleFrontController
{
    /** @var bool */
    public $ssl = true;

    /** @var bool */
    public $ajax = true;

    /**
     * Assistants must keep working while the shop is in maintenance.
     */
    protected function displayMaintenancePage()
    {
    }

    public function initContent()
    {
        $method = PiskeeConnectorHttp::method();
        if ($method === 'OPTIONS') {
            PiskeeConnectorHttp::send(204, [], '');
        }
        if (!Configuration::get('PISKEECONNECTOR_ENABLED')) {
            PiskeeConnectorHttp::json(['error' => 'server_disabled', 'error_description' => 'The merchant switched the Piskee server off.'], 503);
        }

        $grant = PiskeeConnectorOAuth::authenticate(PiskeeConnectorHttp::bearer());
        if ($grant === null) {
            PiskeeConnectorHttp::json(
                ['error' => 'invalid_token', 'error_description' => 'Authentication required.'],
                401,
                ['WWW-Authenticate' => PiskeeConnectorOAuth::wwwAuthenticate(PiskeeConnectorHttp::bearer() !== '' ? 'invalid_token' : '')]
            );
        }

        if ($method !== 'POST') {
            // Stateless server: no server-sent event stream to open.
            PiskeeConnectorHttp::send(405, ['Allow' => 'POST, OPTIONS'], '');
        }

        $message = json_decode(PiskeeConnectorHttp::body(), true);
        if (!is_array($message)) {
            PiskeeConnectorHttp::json(PiskeeConnectorMcp::error(null, -32700, 'Parse error'), 400);
        }
        if (array_is_list($message)) {
            PiskeeConnectorHttp::json(PiskeeConnectorMcp::error(null, -32600, 'Batches are not supported'), 400);
        }

        PiskeeConnectorHelper::boot(['id_shop' => $grant['id_shop'], 'id_employee' => $grant['id_employee'], 'id_lang' => (int) (new Employee($grant['id_employee']))->id_lang]);

        $answer = (new PiskeeConnectorMcp($grant))->handle($message);
        if ($answer === null) {
            PiskeeConnectorHttp::send(202, [], '');
        }
        $version = (string) ($message['params']['protocolVersion'] ?? '');
        PiskeeConnectorHttp::json($answer, 200, ['MCP-Protocol-Version' => in_array($version, PiskeeConnectorMcp::SUPPORTED_VERSIONS, true) ? $version : PiskeeConnectorMcp::SUPPORTED_VERSIONS[1]]);
    }
}
