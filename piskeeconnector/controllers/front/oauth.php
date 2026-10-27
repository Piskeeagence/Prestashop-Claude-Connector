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
 * Token, registration and revocation endpoints: ?a=token, ?a=register, ?a=revoke
 */
class PiskeeconnectorOauthModuleFrontController extends ModuleFrontController
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
        $method = PiskeeConnectorHttp::method();
        if ($method === 'OPTIONS') {
            PiskeeConnectorHttp::send(204, [], '');
        }
        if ($method !== 'POST') {
            PiskeeConnectorHttp::send(405, ['Allow' => 'POST, OPTIONS'], '');
        }
        if (!Configuration::get('PISKEECONNECTOR_ENABLED')) {
            PiskeeConnectorHttp::json(['error' => 'temporarily_unavailable'], 503);
        }

        $action = (string) Tools::getValue('a', '');
        if ($action === 'register') {
            $metadata = json_decode(PiskeeConnectorHttp::body(65536), true);
            if (!is_array($metadata)) {
                PiskeeConnectorHttp::json(['error' => 'invalid_client_metadata', 'error_description' => 'The body must be a JSON object.'], 400);
            }
            $result = PiskeeConnectorOAuth::register($metadata);
            PiskeeConnectorHttp::json($result['body'], $result['status']);
        }

        $post = [];
        parse_str(PiskeeConnectorHttp::body(65536), $post);
        $post = array_map(static function ($value) {
            return is_string($value) ? $value : '';
        }, $post);

        if ($action === 'token') {
            [$user, $password] = PiskeeConnectorHttp::basic();
            $result = PiskeeConnectorOAuth::token($post, $user, $password);
            $headers = $result['status'] === 401 ? ['WWW-Authenticate' => 'Basic realm="Piskee"'] : [];
            PiskeeConnectorHttp::json($result['body'], $result['status'], $headers);
        }
        if ($action === 'revoke') {
            PiskeeConnectorOAuth::revoke($post);
            PiskeeConnectorHttp::send(200, [], '');
        }

        PiskeeConnectorHttp::json(['error' => 'invalid_request', 'error_description' => 'Unknown action.'], 404);
    }
}
