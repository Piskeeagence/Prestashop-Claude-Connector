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
 * Authorization endpoint. Checks the request, keeps it, and sends the
 * merchant to the consent screen of the back office, where they log in as
 * an employee and decide.
 */
class PiskeeconnectorAuthorizeModuleFrontController extends ModuleFrontController
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
        if (!Configuration::get('PISKEECONNECTOR_ENABLED')) {
            PiskeeConnectorHttp::page(503, 'Piskee', 'This shop is not accepting new assistant connections right now.');
        }

        $q = [];
        foreach (['client_id', 'redirect_uri', 'response_type', 'scope', 'state', 'code_challenge', 'code_challenge_method', 'resource'] as $key) {
            $value = Tools::getValue($key, '');
            $q[$key] = is_string($value) ? Tools::substr($value, 0, 2000) : '';
        }

        $check = PiskeeConnectorOAuth::validateAuthorization($q);
        if (!$check['ok']) {
            if ($check['redirectable']) {
                $this->sendBack($q, ['error' => $check['error'], 'error_description' => $check['description']]);
            }
            // Unknown client or address: nothing is sent anywhere.
            PiskeeConnectorHttp::page(400, 'Piskee', 'This connection request is not valid (' . $check['description'] . '). Start the connection again from your assistant.');
        }

        $adminDir = (string) Configuration::get('PISKEECONNECTOR_ADMIN_DIR');
        if ($adminDir === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $adminDir)) {
            PiskeeConnectorHttp::page(503, 'Piskee', 'Open the Piskee module page once in your back office, then start the connection again.');
        }

        $id = PiskeeConnectorOAuth::storeRequest([
            'client_id' => $q['client_id'],
            'redirect_uri' => $q['redirect_uri'],
            'state' => $q['state'],
            'code_challenge' => $q['code_challenge'],
            'scopes' => $check['scopes'],
            'id_shop' => (int) $this->context->shop->id,
        ]);

        // The back office login may drop the query string: a short-lived
        // cookie keeps the request id across it.
        setcookie(PiskeeConnectorConsent::COOKIE, $id, [
            'expires' => time() + PiskeeConnectorOAuth::REQUEST_TTL,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        // PrestaShop 8 and 9: a Symfony back office route, served without the
        // admin URL token that an assistant's redirect cannot carry.
        // PrestaShop 1.7: the legacy admin controller, which skips that token.
        $url = version_compare(_PS_VERSION_, '8.0.0', '>=')
            ? PiskeeConnectorOAuth::baseUrl() . '/' . $adminDir . '/index.php/modules/piskeeconnector/consent?request=' . rawurlencode($id)
            : PiskeeConnectorOAuth::baseUrl() . '/' . $adminDir . '/index.php?controller=AdminPiskeeConnectorConsent&request=' . rawurlencode($id);
        Tools::redirect($url);
    }

    /**
     * @param array<string, string> $q
     * @param array<string, string> $params
     */
    private function sendBack(array $q, array $params): void
    {
        if ($q['state'] !== '') {
            $params['state'] = $q['state'];
        }
        $params['iss'] = PiskeeConnectorOAuth::issuer();
        Tools::redirect($q['redirect_uri'] . (strpos($q['redirect_uri'], '?') === false ? '?' : '&') . http_build_query($params));
    }
}
