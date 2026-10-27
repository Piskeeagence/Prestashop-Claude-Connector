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
 * OAuth 2.1 authorization server for the MCP endpoint.
 *
 * Implements, from the public specifications:
 * - RFC 6749 / OAuth 2.1 authorization code grant with PKCE S256 only (RFC 7636),
 * - RFC 7591 dynamic client registration,
 * - RFC 7009 token revocation,
 * - RFC 8414 authorization server metadata,
 * - RFC 9728 protected resource metadata,
 * - RFC 8707 resource indicators.
 *
 * Secrets are never stored: codes and tokens are kept as SHA-256 hashes.
 * Refresh tokens rotate on each use; presenting a used one revokes the whole
 * grant, as OAuth 2.1 recommends for public clients.
 */
class PiskeeConnectorOAuth
{
    public const ACCESS_TTL = 3600;
    public const REFRESH_TTL = 2592000;
    public const CODE_TTL = 120;
    public const REQUEST_TTL = 900;

    /**
     * Permissions an assistant can be granted, with the label shown on the
     * consent screen.
     *
     * @return array<int, string>
     */
    public static function scopes(): array
    {
        return PiskeeConnectorTools::scopeNames();
    }

    public static function baseUrl(?Shop $shop = null): string
    {
        $shop = $shop ?: Context::getContext()->shop;

        return 'https://' . ($shop->domain_ssl ?: $shop->domain) . rtrim($shop->getBaseURI(), '/');
    }

    public static function issuer(): string
    {
        return self::baseUrl() . '/module/piskeeconnector';
    }

    public static function resource(): string
    {
        return self::issuer() . '/mcp';
    }

    public static function endpoint(string $controller, array $params = []): string
    {
        return self::issuer() . '/' . $controller . ($params !== [] ? '?' . http_build_query($params) : '');
    }

    /**
     * RFC 9728 document.
     *
     * @return array<string, mixed>
     */
    public static function protectedResourceMetadata(): array
    {
        return [
            'resource' => self::resource(),
            'authorization_servers' => [self::issuer()],
            'scopes_supported' => self::scopes(),
            'bearer_methods_supported' => ['header'],
            'resource_name' => (string) Configuration::get('PS_SHOP_NAME') . ' (Piskee for PrestaShop)',
            'resource_documentation' => PiskeeConnectorBrand::REPOSITORY_URL,
        ];
    }

    /**
     * RFC 8414 document.
     *
     * @return array<string, mixed>
     */
    public static function authorizationServerMetadata(): array
    {
        return [
            'issuer' => self::issuer(),
            'authorization_endpoint' => self::endpoint('authorize'),
            'token_endpoint' => self::endpoint('oauth', ['a' => 'token']),
            'registration_endpoint' => self::endpoint('oauth', ['a' => 'register']),
            'revocation_endpoint' => self::endpoint('oauth', ['a' => 'revoke']),
            'scopes_supported' => self::scopes(),
            'response_types_supported' => ['code'],
            'response_modes_supported' => ['query'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post', 'client_secret_basic'],
            'revocation_endpoint_auth_methods_supported' => ['none', 'client_secret_post', 'client_secret_basic'],
            'code_challenge_methods_supported' => ['S256'],
            'authorization_response_iss_parameter_supported' => true,
            'service_documentation' => PiskeeConnectorBrand::REPOSITORY_URL,
        ];
    }

    public static function wwwAuthenticate(string $error = ''): string
    {
        return 'Bearer resource_metadata="' . self::endpoint('metadata', ['type' => 'resource']) . '"'
            . ', scope="' . implode(' ', self::scopes()) . '"'
            . ($error !== '' ? ', error="' . $error . '"' : '');
    }

    // ------------------------------------------------------------------
    // Clients
    // ------------------------------------------------------------------

    /**
     * RFC 7591 registration.
     *
     * @param array<string, mixed> $metadata
     *
     * @return array{status:int, body:array<string, mixed>}
     */
    public static function register(array $metadata): array
    {
        $uris = is_array($metadata['redirect_uris'] ?? null) ? $metadata['redirect_uris'] : [];
        $clean = [];
        foreach ($uris as $uri) {
            if (is_string($uri) && self::validRedirectUri($uri)) {
                $clean[] = $uri;
            }
        }
        $clean = array_values(array_unique($clean));
        if ($clean === [] || count($clean) > 10) {
            return ['status' => 400, 'body' => ['error' => 'invalid_redirect_uri', 'error_description' => 'redirect_uris must list 1 to 10 https addresses, or loopback addresses for native apps.']];
        }

        $method = (string) ($metadata['token_endpoint_auth_method'] ?? 'none');
        if (!in_array($method, ['none', 'client_secret_post', 'client_secret_basic'], true)) {
            return ['status' => 400, 'body' => ['error' => 'invalid_client_metadata', 'error_description' => 'Unsupported token_endpoint_auth_method.']];
        }

        $name = Tools::substr(trim(strip_tags((string) ($metadata['client_name'] ?? ''))), 0, 120);
        $clientId = 'pkc_' . self::random(24);
        $secret = $method === 'none' ? '' : self::random(32);

        Db::getInstance()->insert('piskeeconnector_client', [
            'client_id' => pSQL($clientId),
            'client_secret_hash' => $secret === '' ? '' : hash('sha256', $secret),
            'client_name' => pSQL($name !== '' ? $name : 'MCP client'),
            'client_uri' => pSQL(Tools::substr((string) ($metadata['client_uri'] ?? ''), 0, 255)),
            'redirect_uris' => pSQL((string) json_encode($clean)),
            'auth_method' => pSQL($method),
            'date_add' => date('Y-m-d H:i:s'),
        ]);

        $body = [
            'client_id' => $clientId,
            'client_id_issued_at' => time(),
            'client_name' => $name !== '' ? $name : 'MCP client',
            'redirect_uris' => $clean,
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => $method,
        ];
        if ($secret !== '') {
            $body['client_secret'] = $secret;
            $body['client_secret_expires_at'] = 0;
        }

        return ['status' => 201, 'body' => $body];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function client(string $clientId): ?array
    {
        if ($clientId === '' || Tools::strlen($clientId) > 64) {
            return null;
        }
        $row = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . "piskeeconnector_client` WHERE `client_id` = '" . pSQL($clientId) . "'");
        if (!is_array($row) || $row === []) {
            return null;
        }
        $row['redirect_uris'] = json_decode((string) $row['redirect_uris'], true) ?: [];

        return $row;
    }

    /**
     * Exact match, except that loopback addresses may use any port (RFC 8252).
     *
     * @param array<string, mixed> $client
     */
    public static function redirectAllowed(array $client, string $uri): bool
    {
        foreach ($client['redirect_uris'] as $registered) {
            if (hash_equals((string) $registered, $uri)) {
                return true;
            }
            $a = parse_url((string) $registered);
            $b = parse_url($uri);
            if (is_array($a) && is_array($b) && self::isLoopback((string) ($a['host'] ?? ''))
                && ($a['scheme'] ?? '') === ($b['scheme'] ?? '') && ($a['host'] ?? '') === ($b['host'] ?? '')
                && ($a['path'] ?? '') === ($b['path'] ?? '')) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------
    // Authorization requests and codes
    // ------------------------------------------------------------------

    /**
     * Checks an authorization request. When the client or redirect address is
     * unknown, nothing may be sent back to it (no open redirect).
     *
     * @param array<string, string> $q
     *
     * @return array{ok:bool, redirectable:bool, error:string, description:string, scopes:array<int, string>}
     */
    public static function validateAuthorization(array $q): array
    {
        $fail = static function (bool $redirectable, string $error, string $description): array {
            return ['ok' => false, 'redirectable' => $redirectable, 'error' => $error, 'description' => $description, 'scopes' => []];
        };

        $client = self::client($q['client_id']);
        if ($client === null) {
            return $fail(false, 'invalid_client', 'Unknown client_id.');
        }
        if ($q['redirect_uri'] === '' || !self::redirectAllowed($client, $q['redirect_uri'])) {
            return $fail(false, 'invalid_request', 'redirect_uri is not registered for this client.');
        }
        if ($q['response_type'] !== 'code') {
            return $fail(true, 'unsupported_response_type', 'Only response_type=code is supported.');
        }
        if ($q['code_challenge_method'] !== 'S256' || !preg_match('/^[A-Za-z0-9_-]{43,128}$/', $q['code_challenge'])) {
            return $fail(true, 'invalid_request', 'PKCE with code_challenge_method=S256 is required.');
        }
        if ($q['resource'] !== '' && rtrim($q['resource'], '/') !== self::resource()) {
            return $fail(true, 'invalid_target', 'resource must be ' . self::resource());
        }

        $requested = array_values(array_filter(preg_split('/\s+/', trim($q['scope'])) ?: []));
        $scopes = $requested === [] ? self::scopes() : array_values(array_intersect($requested, self::scopes()));
        if ($scopes === []) {
            return $fail(true, 'invalid_scope', 'None of the requested scopes exists.');
        }

        return ['ok' => true, 'redirectable' => true, 'error' => '', 'description' => '', 'scopes' => $scopes];
    }

    /**
     * Keeps a validated request while the merchant logs in and decides.
     *
     * @param array<string, mixed> $payload
     */
    public static function storeRequest(array $payload): string
    {
        $id = self::random(24);
        $db = Db::getInstance();
        $db->delete('piskeeconnector_request', "`expires` < '" . date('Y-m-d H:i:s') . "'");
        $db->insert('piskeeconnector_request', [
            'id_request' => pSQL($id),
            'payload' => pSQL((string) json_encode($payload)),
            'expires' => date('Y-m-d H:i:s', time() + self::REQUEST_TTL),
        ]);

        return $id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function request(string $id): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{20,64}$/', $id)) {
            return null;
        }
        $row = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . "piskeeconnector_request` WHERE `id_request` = '" . pSQL($id) . "' AND `expires` >= '" . date('Y-m-d H:i:s') . "'");
        if (!is_array($row) || $row === []) {
            return null;
        }
        $payload = json_decode((string) $row['payload'], true);

        return is_array($payload) ? $payload : null;
    }

    public static function forgetRequest(string $id): void
    {
        Db::getInstance()->delete('piskeeconnector_request', "`id_request` = '" . pSQL($id) . "'");
    }

    /**
     * @param array<string, mixed> $request
     */
    public static function issueCode(array $request, int $idEmployee, int $idShop): string
    {
        $code = self::random(32);
        $client = self::client((string) $request['client_id']);
        Db::getInstance()->insert('piskeeconnector_code', [
            'code_hash' => hash('sha256', $code),
            'id_client' => (int) ($client['id_client'] ?? 0),
            'id_employee' => $idEmployee,
            'id_shop' => $idShop,
            'redirect_uri' => pSQL((string) $request['redirect_uri']),
            'code_challenge' => pSQL((string) $request['code_challenge']),
            'scope' => pSQL(implode(' ', (array) $request['scopes'])),
            'expires' => date('Y-m-d H:i:s', time() + self::CODE_TTL),
            'used' => 0,
        ]);

        return $code;
    }

    // ------------------------------------------------------------------
    // Token endpoint
    // ------------------------------------------------------------------

    /**
     * @param array<string, string> $post
     *
     * @return array{status:int, body:array<string, mixed>}
     */
    public static function token(array $post, string $basicUser = '', string $basicPassword = ''): array
    {
        $clientId = $basicUser !== '' ? $basicUser : (string) ($post['client_id'] ?? '');
        $client = self::client($clientId);
        if ($client === null || !self::authenticateClient($client, $basicUser !== '' ? $basicPassword : (string) ($post['client_secret'] ?? ''))) {
            return ['status' => 401, 'body' => ['error' => 'invalid_client']];
        }

        $grant = (string) ($post['grant_type'] ?? '');
        if ($grant === 'authorization_code') {
            return self::exchangeCode($client, $post);
        }
        if ($grant === 'refresh_token') {
            return self::refresh($client, (string) ($post['refresh_token'] ?? ''));
        }

        return ['status' => 400, 'body' => ['error' => 'unsupported_grant_type']];
    }

    /**
     * @param array<string, mixed> $client
     * @param array<string, string> $post
     *
     * @return array{status:int, body:array<string, mixed>}
     */
    private static function exchangeCode(array $client, array $post): array
    {
        $db = Db::getInstance();
        $hash = hash('sha256', (string) ($post['code'] ?? ''));
        $row = $db->getRow('SELECT * FROM `' . _DB_PREFIX_ . "piskeeconnector_code` WHERE `code_hash` = '" . pSQL($hash) . "'");
        $invalid = ['status' => 400, 'body' => ['error' => 'invalid_grant']];
        if (!is_array($row) || $row === [] || (int) $row['id_client'] !== (int) $client['id_client']) {
            return $invalid;
        }
        if ((int) $row['used'] === 1) {
            // A code presented twice: revoke what was issued with it.
            $db->update('piskeeconnector_token', ['revoked' => 1], "`family` = '" . pSQL(substr($hash, 0, 32)) . "'");

            return $invalid;
        }
        $db->update('piskeeconnector_code', ['used' => 1], "`code_hash` = '" . pSQL($hash) . "'");

        if ($row['expires'] < date('Y-m-d H:i:s') || !hash_equals((string) $row['redirect_uri'], (string) ($post['redirect_uri'] ?? ''))) {
            return $invalid;
        }
        $verifier = (string) ($post['code_verifier'] ?? '');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        if (!preg_match('/^[A-Za-z0-9._~-]{43,128}$/', $verifier) || !hash_equals((string) $row['code_challenge'], $challenge)) {
            return $invalid;
        }

        return ['status' => 200, 'body' => self::issueTokens((int) $client['id_client'], (int) $row['id_employee'], (int) $row['id_shop'], (string) $row['scope'], substr($hash, 0, 32))];
    }

    /**
     * @param array<string, mixed> $client
     *
     * @return array{status:int, body:array<string, mixed>}
     */
    private static function refresh(array $client, string $refreshToken): array
    {
        $db = Db::getInstance();
        $row = $db->getRow('SELECT * FROM `' . _DB_PREFIX_ . "piskeeconnector_token` WHERE `token_hash` = '" . pSQL(hash('sha256', $refreshToken)) . "' AND `type` = 'refresh'");
        $invalid = ['status' => 400, 'body' => ['error' => 'invalid_grant']];
        if (!is_array($row) || $row === [] || (int) $row['id_client'] !== (int) $client['id_client']) {
            return $invalid;
        }
        if ((int) $row['revoked'] === 1) {
            // Reuse of a rotated refresh token: the grant is compromised.
            $db->update('piskeeconnector_token', ['revoked' => 1], "`family` = '" . pSQL((string) $row['family']) . "'");

            return $invalid;
        }
        if ($row['expires'] < date('Y-m-d H:i:s') || !self::employeeActive((int) $row['id_employee'])) {
            return $invalid;
        }
        $db->update('piskeeconnector_token', ['revoked' => 1], '`id_token` = ' . (int) $row['id_token']);

        return ['status' => 200, 'body' => self::issueTokens((int) $row['id_client'], (int) $row['id_employee'], (int) $row['id_shop'], (string) $row['scope'], (string) $row['family'])];
    }

    /**
     * @return array<string, mixed>
     */
    private static function issueTokens(int $idClient, int $idEmployee, int $idShop, string $scope, string $family): array
    {
        $access = self::random(32);
        $refresh = self::random(32);
        $now = time();
        foreach ([['access', $access, self::ACCESS_TTL], ['refresh', $refresh, self::REFRESH_TTL]] as [$type, $value, $ttl]) {
            Db::getInstance()->insert('piskeeconnector_token', [
                'token_hash' => hash('sha256', $value),
                'type' => $type,
                'family' => pSQL($family),
                'id_client' => $idClient,
                'id_employee' => $idEmployee,
                'id_shop' => $idShop,
                'scope' => pSQL($scope),
                'expires' => date('Y-m-d H:i:s', $now + $ttl),
                'revoked' => 0,
                'date_add' => date('Y-m-d H:i:s', $now),
            ]);
        }

        return [
            'access_token' => $access,
            'token_type' => 'Bearer',
            'expires_in' => self::ACCESS_TTL,
            'refresh_token' => $refresh,
            'scope' => $scope,
        ];
    }

    /**
     * @param array<string, string> $post
     */
    public static function revoke(array $post): void
    {
        $token = (string) ($post['token'] ?? '');
        if ($token === '') {
            return;
        }
        $row = Db::getInstance()->getRow('SELECT `family` FROM `' . _DB_PREFIX_ . "piskeeconnector_token` WHERE `token_hash` = '" . pSQL(hash('sha256', $token)) . "'");
        if (is_array($row) && $row !== []) {
            self::revokeFamily((string) $row['family']);
        }
    }

    public static function revokeFamily(string $family): void
    {
        Db::getInstance()->update('piskeeconnector_token', ['revoked' => 1], "`family` = '" . pSQL($family) . "'");
    }

    /**
     * The grant behind a bearer token, or null.
     *
     * @return array{id_employee:int, id_shop:int, scopes:array<int, string>, id_client:int}|null
     */
    public static function authenticate(string $bearer): ?array
    {
        if ($bearer === '' || Tools::strlen($bearer) > 200) {
            return null;
        }
        $db = Db::getInstance();
        $row = $db->getRow('SELECT * FROM `' . _DB_PREFIX_ . "piskeeconnector_token` WHERE `token_hash` = '" . pSQL(hash('sha256', $bearer)) . "' AND `type` = 'access' AND `revoked` = 0");
        if (!is_array($row) || $row === [] || $row['expires'] < date('Y-m-d H:i:s') || !self::employeeActive((int) $row['id_employee'])) {
            return null;
        }
        $db->update('piskeeconnector_token', ['last_used' => date('Y-m-d H:i:s')], '`id_token` = ' . (int) $row['id_token']);

        return [
            'id_employee' => (int) $row['id_employee'],
            'id_shop' => (int) $row['id_shop'],
            'scopes' => array_values(array_filter(explode(' ', (string) $row['scope']))),
            'id_client' => (int) $row['id_client'],
        ];
    }

    /**
     * Active grants, one line per connected assistant, for the module page.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function connections(): array
    {
        $p = _DB_PREFIX_;
        $rows = Db::getInstance()->executeS('SELECT t.`family`, c.`client_name`, e.`firstname`, e.`lastname`, e.`email`, s.`name` AS shop,
                MIN(t.`date_add`) AS since, MAX(t.`last_used`) AS last_used
            FROM `' . $p . 'piskeeconnector_token` t
            LEFT JOIN `' . $p . 'piskeeconnector_client` c ON c.`id_client` = t.`id_client`
            LEFT JOIN `' . $p . 'employee` e ON e.`id_employee` = t.`id_employee`
            LEFT JOIN `' . $p . 'shop` s ON s.`id_shop` = t.`id_shop`
            WHERE t.`family` IN (SELECT `family` FROM `' . $p . "piskeeconnector_token` WHERE `type` = 'refresh' AND `revoked` = 0 AND `expires` >= '" . date('Y-m-d H:i:s') . "')
            GROUP BY t.`family`, c.`client_name`, e.`firstname`, e.`lastname`, e.`email`, s.`name`
            ORDER BY last_used DESC LIMIT 50") ?: [];

        return array_map(static function (array $row) {
            return [
                'family' => (string) $row['family'],
                'client' => (string) $row['client_name'],
                'employee' => trim($row['firstname'] . ' ' . $row['lastname']) . ' (' . $row['email'] . ')',
                'shop' => (string) $row['shop'],
                'since' => (string) $row['since'],
                'last_used' => (string) ($row['last_used'] ?? ''),
            ];
        }, $rows);
    }

    public static function purge(): void
    {
        $now = date('Y-m-d H:i:s');
        $db = Db::getInstance();
        $db->delete('piskeeconnector_code', "`expires` < '" . date('Y-m-d H:i:s', time() - 86400) . "'");
        $db->delete('piskeeconnector_request', "`expires` < '" . $now . "'");
        $db->delete('piskeeconnector_token', "`expires` < '" . date('Y-m-d H:i:s', time() - 86400) . "'");
    }

    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $client
     */
    private static function authenticateClient(array $client, string $secret): bool
    {
        if ((string) $client['auth_method'] === 'none') {
            return true;
        }

        return $secret !== '' && hash_equals((string) $client['client_secret_hash'], hash('sha256', $secret));
    }

    private static function employeeActive(int $idEmployee): bool
    {
        return (bool) Db::getInstance()->getValue('SELECT `active` FROM `' . _DB_PREFIX_ . 'employee` WHERE `id_employee` = ' . $idEmployee);
    }

    private static function validRedirectUri(string $uri): bool
    {
        if (Tools::strlen($uri) > 500 || strpos($uri, '#') !== false) {
            return false;
        }
        $parts = parse_url($uri);
        if (!is_array($parts) || empty($parts['scheme'])) {
            return false;
        }
        if ($parts['scheme'] === 'https') {
            return !empty($parts['host']);
        }
        if ($parts['scheme'] === 'http') {
            return self::isLoopback((string) ($parts['host'] ?? ''));
        }

        // Private-use URI schemes of native apps (RFC 8252), never javascript: or data:.
        return preg_match('/^[a-z][a-z0-9+.-]*\.[a-z0-9+.-]+$/', (string) $parts['scheme']) === 1;
    }

    private static function isLoopback(string $host): bool
    {
        return in_array(Tools::strtolower(trim($host, '[]')), ['127.0.0.1', '::1', 'localhost'], true);
    }

    public static function random(int $bytes): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
}
