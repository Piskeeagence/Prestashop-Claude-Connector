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
 * Raw HTTP answers for the public endpoints, outside the theme.
 */
class PiskeeConnectorHttp
{
    /**
     * @param mixed $data
     * @param array<string, string> $headers
     */
    public static function json($data, int $status = 200, array $headers = []): void
    {
        self::send($status, array_merge(['Content-Type' => 'application/json; charset=utf-8'], $headers), (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param array<string, string> $headers
     */
    public static function send(int $status, array $headers, string $body): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($status);
        header('Cache-Control: no-store');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, Mcp-Protocol-Version, Mcp-Session-Id');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Expose-Headers: WWW-Authenticate, Mcp-Protocol-Version');
        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $body;
        exit;
    }

    public static function method(): string
    {
        return Tools::strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public static function body(int $max = 1048576): string
    {
        $body = (string) file_get_contents('php://input', false, null, 0, $max + 1);

        return strlen($body) > $max ? '' : $body;
    }

    /**
     * The Authorization header, wherever the web server put it.
     */
    public static function authorization(): string
    {
        foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
            if (!empty($_SERVER[$key])) {
                return (string) $_SERVER[$key];
            }
        }
        if (function_exists('getallheaders')) {
            foreach ((array) getallheaders() as $name => $value) {
                if (Tools::strtolower((string) $name) === 'authorization') {
                    return (string) $value;
                }
            }
        }

        return '';
    }

    public static function bearer(): string
    {
        $header = self::authorization();

        return preg_match('/^Bearer\s+([A-Za-z0-9._~+\/=-]+)$/i', trim($header), $m) ? $m[1] : '';
    }

    /**
     * @return array{0:string, 1:string} client id and secret from HTTP Basic
     */
    public static function basic(): array
    {
        $header = self::authorization();
        if (!preg_match('/^Basic\s+(.+)$/i', trim($header), $m)) {
            return ['', ''];
        }
        $decoded = (string) base64_decode($m[1], true);
        $parts = explode(':', $decoded, 2);

        return [rawurldecode($parts[0]), rawurldecode($parts[1] ?? '')];
    }

    /**
     * A plain page for errors shown to a person in a browser.
     */
    public static function page(int $status, string $title, string $message): void
    {
        $html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'
            . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title><style>body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;background:#FFF5EE;color:#161616;display:grid;place-items:center;min-height:100vh;margin:0}'
            . 'main{background:#fff;border:1px solid #EAECF0;border-radius:16px;padding:32px;max-width:520px;margin:16px}h1{font-size:20px;margin:0 0 12px}p{line-height:1.5}a{color:#50543f}</style></head><body><main><h1>'
            . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p><p><small>Piskee for PrestaShop · <a href="'
            . htmlspecialchars(PiskeeConnectorBrand::CONTACT_URL, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars(PiskeeConnectorBrand::EMAIL, ENT_QUOTES, 'UTF-8') . '</a></small></p></main></body></html>';
        self::send($status, ['Content-Type' => 'text/html; charset=utf-8', 'Access-Control-Allow-Origin' => 'null'], $html);
    }
}
