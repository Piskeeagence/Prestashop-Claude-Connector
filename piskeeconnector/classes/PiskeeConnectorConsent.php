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
 * Consent logic shared by the Symfony page (PrestaShop 8 and 9) and the
 * legacy admin controller. The logged-in employee sees which assistant asks
 * for which rights, then allows or refuses. The assistant then acts with
 * this employee's own PrestaShop rights, never more.
 */
class PiskeeConnectorConsent
{
    public const COOKIE = 'piskee_oauth_request';

    /**
     * @param array<string, string>|null $post the submitted form, or null on display
     *
     * @return array{redirect?: string, vars?: array<string, mixed>}
     */
    public static function process(Employee $employee, int $idLang, string $isoLang, string $requestId, ?array $post, string $actionUrl): array
    {
        $lang = PiskeeConnectorI18n::lang($isoLang);
        $t = PiskeeConnectorI18n::consent($lang);
        $idEmployee = (int) $employee->id;

        $request = $requestId !== '' ? PiskeeConnectorOAuth::request($requestId) : null;
        $client = $request !== null ? PiskeeConnectorOAuth::client((string) $request['client_id']) : null;
        if ($request === null || $client === null || $idEmployee <= 0 || !$employee->active) {
            return ['vars' => self::vars($t, ['expired' => true])];
        }

        $nonce = hash_hmac('sha256', 'consent|' . $requestId . '|' . $idEmployee, _COOKIE_KEY_);

        if ($post !== null && isset($post['piskee_decision'])) {
            if (!hash_equals($nonce, (string) ($post['nonce'] ?? ''))) {
                return ['vars' => self::vars($t, ['expired' => true])];
            }
            PiskeeConnectorOAuth::forgetRequest($requestId);
            self::clearCookie();

            $params = ['iss' => PiskeeConnectorOAuth::issuer()];
            if ((string) $request['state'] !== '') {
                $params['state'] = (string) $request['state'];
            }
            if ($post['piskee_decision'] === 'allow') {
                $params['code'] = PiskeeConnectorOAuth::issueCode($request, $idEmployee, (int) $request['id_shop']);
            } else {
                $params['error'] = 'access_denied';
                $params['error_description'] = 'The merchant refused the connection.';
            }
            $target = (string) $request['redirect_uri'];

            return ['redirect' => $target . (strpos($target, '?') === false ? '?' : '&') . http_build_query($params)];
        }

        $scopes = [];
        foreach ((array) $request['scopes'] as $scope) {
            $scopes[] = [
                'label' => PiskeeConnectorI18n::scopeLabel((string) $scope, $lang),
                'writes' => (bool) preg_match('/\.write$/', (string) $scope),
            ];
        }
        $redirect = parse_url((string) $request['redirect_uri']);
        $shop = new Shop((int) $request['id_shop']);

        return ['vars' => self::vars($t, [
            'expired' => false,
            'client' => (string) $client['client_name'],
            'redirect_host' => (string) ($redirect['host'] ?? $redirect['scheme'] ?? ''),
            'employee' => trim($employee->firstname . ' ' . $employee->lastname) . ' (' . $employee->email . ')',
            'profile' => (string) (new Profile((int) $employee->id_profile, $idLang))->name,
            'shop' => (string) $shop->name,
            'scopes' => $scopes,
            'nonce' => $nonce,
            'request' => $requestId,
            'action' => $actionUrl,
        ])];
    }

    /**
     * Renders the consent screen as a complete page (used outside the
     * legacy back office layout).
     *
     * @param array<string, mixed> $vars
     */
    public static function page(array $vars, string $isoLang): string
    {
        $smarty = Context::getContext()->smarty;
        $smarty->assign(['pkc' => $vars]);
        $body = $smarty->fetch(_PS_MODULE_DIR_ . 'piskeeconnector/views/templates/admin/consent.tpl');
        $css = PiskeeConnectorOAuth::baseUrl() . '/modules/piskeeconnector/views/css/admin.css?v=' . '2.9.0';
        $title = htmlspecialchars((string) $vars['t']['title'], ENT_QUOTES, 'UTF-8');

        return '<!doctype html><html lang="' . htmlspecialchars(PiskeeConnectorI18n::lang($isoLang), ENT_QUOTES, 'UTF-8') . '"><head>'
            . '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer">'
            . '<title>' . $title . '</title>'
            . '<link rel="stylesheet" href="' . htmlspecialchars($css, ENT_QUOTES, 'UTF-8') . '">'
            . '<style>body{margin:0;background:#FDF6F1;font-family:Satoshi,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:#161616}'
            . '.pkc-consent{max-width:640px;margin:32px auto;padding:0 16px}</style>'
            . '</head><body>' . $body . '</body></html>';
    }

    public static function clearCookie(): void
    {
        setcookie(self::COOKIE, '', ['expires' => time() - 3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
    }

    /**
     * @param array<string, string> $t
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private static function vars(array $t, array $data): array
    {
        return array_merge($data, [
            't' => $t,
            'logo' => PiskeeConnectorOAuth::baseUrl() . '/modules/piskeeconnector/views/img/piskee-white.png',
            'contact' => PiskeeConnectorBrand::link(PiskeeConnectorBrand::CONTACT_URL, 'consent'),
            'email' => PiskeeConnectorBrand::EMAIL,
        ]);
    }
}
