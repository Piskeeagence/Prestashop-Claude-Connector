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
 * New versions of Piskee and Piskee Pro, shown on the module page.
 *
 * Once a day at most, the module asks Piskee's server for the latest version
 * numbers (nothing about the shop is sent). Piskee Pro is downloaded with the
 * shop's licence key: the server answers only for an active subscription and
 * returns a copy marked with its owner's name.
 */
class PiskeeConnectorUpdate
{
    private const KEY_CACHE = 'PISKEECONNECTOR_UPDATES';

    /**
     * @return array{free:array<string, string>|null, pro:array<string, string>|null}
     */
    public static function available(string $freeVersion, int $idShop): array
    {
        $latest = self::latest();
        $out = ['free' => null, 'pro' => null];

        $free = $latest['piskeeconnector'] ?? null;
        if (is_array($free) && !empty($free['version']) && version_compare((string) $free['version'], $freeVersion, '>')) {
            $out['free'] = [
                'version' => (string) $free['version'],
                'current' => $freeVersion,
                'download' => self::safeUrl((string) ($free['download'] ?? '')),
                'page' => self::safeUrl((string) ($free['page'] ?? '')),
                'notes' => self::notes((string) ($free['notes'] ?? ''), (string) $free['version']),
            ];
        }

        $pro = $latest['piskeeconnectorpro'] ?? null;
        $proVersion = self::proVersion();
        if (is_array($pro) && !empty($pro['version']) && $proVersion !== '' && PiskeeConnectorLicense::isPro($idShop)
            && version_compare((string) $pro['version'], $proVersion, '>')) {
            $out['pro'] = ['version' => (string) $pro['version'], 'current' => $proVersion];
        }

        return $out;
    }

    /**
     * Sends the newest Piskee Pro to the browser, or returns an error code.
     */
    public static function downloadPro(int $idShop): string
    {
        $key = PiskeeConnectorLicense::key($idShop);
        if ($key === '' || !PiskeeConnectorLicense::isPro($idShop)) {
            return 'licence';
        }
        [$status, $body, $type] = self::http(self::url(), ['key' => $key], 30);
        if ($status !== 200 || strpos($type, 'zip') === false || substr($body, 0, 2) !== 'PK') {
            $data = json_decode($body, true);

            return is_array($data) && isset($data['error']) ? (string) $data['error'] : 'server';
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="piskeeconnectorpro.zip"');
        header('Content-Length: ' . strlen($body));
        echo $body;
        exit;
    }

    /**
     * @return array<string, mixed>
     */
    private static function latest(): array
    {
        $cache = json_decode((string) Configuration::getGlobalValue(self::KEY_CACHE), true);
        if (is_array($cache) && (int) ($cache['at'] ?? 0) > time() - 86400) {
            return (array) ($cache['data'] ?? []);
        }
        // Remember the attempt first: a slow or unreachable server is asked again tomorrow only.
        Configuration::updateGlobalValue(self::KEY_CACHE, (string) json_encode(['at' => time(), 'data' => is_array($cache) ? ($cache['data'] ?? []) : []]));
        [$status, $body] = self::http(self::url(), null, 4);
        $data = $status === 200 ? json_decode($body, true) : null;
        if (!is_array($data)) {
            return is_array($cache) ? (array) ($cache['data'] ?? []) : [];
        }
        Configuration::updateGlobalValue(self::KEY_CACHE, (string) json_encode(['at' => time(), 'data' => $data]));

        return $data;
    }

    public static function proVersion(): string
    {
        if (!Module::isInstalled('piskeeconnectorpro')) {
            return '';
        }

        return (string) Db::getInstance()->getValue('SELECT `version` FROM `' . _DB_PREFIX_ . "module` WHERE `name` = 'piskeeconnectorpro'");
    }

    private static function url(): string
    {
        return (string) (Configuration::get('PISKEECONNECTOR_UPDATE_URL') ?: PiskeeConnectorBrand::UPDATE_URL);
    }

    private static function safeUrl(string $url): string
    {
        return preg_match('#^https://(github\.com|objects\.githubusercontent\.com|www\.piskee\.com)/#', $url) ? $url : 'https://github.com/Piskeeagence/Prestashop-Claude-Connector/releases/latest';
    }

    /**
     * The lines of the release notes about this version, as plain text.
     */
    private static function notes(string $markdown, string $version): string
    {
        $markdown = str_replace("\r", '', $markdown);
        $start = strpos($markdown, '### ' . $version);
        $part = $start !== false ? substr($markdown, $start + strlen('### ' . $version)) : $markdown;
        $next = strpos($part, "\n### ");
        if ($next !== false) {
            $part = substr($part, 0, $next);
        }
        $lines = [];
        foreach (explode("\n", $part) as $line) {
            $line = trim(preg_replace('/^[-*]\s*/', '', $line) ?? '');
            $line = trim(str_replace(['**', '`'], '', $line));
            if ($line !== '' && stripos($line, 'Install:') !== 0) {
                $lines[] = Tools::strlen($line) > 220 ? Tools::substr($line, 0, 219) . '…' : $line;
            }
        }

        return implode("\n", array_slice($lines, 0, 4));
    }

    /**
     * @param array<string, string>|null $post
     *
     * @return array{0:int, 1:string, 2:string}
     */
    private static function http(string $url, ?array $post, int $timeout): array
    {
        try {
            if (function_exists('curl_init')) {
                $curl = curl_init($url);
                $options = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_HTTPHEADER => ['Accept: application/json, application/zip']];
                if ($post !== null) {
                    $options[CURLOPT_POST] = true;
                    $options[CURLOPT_POSTFIELDS] = http_build_query($post);
                }
                curl_setopt_array($curl, $options);
                $body = (string) curl_exec($curl);
                $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
                $type = (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
                curl_close($curl);

                return [$status, $body, $type];
            }
            $context = stream_context_create(['http' => [
                'method' => $post !== null ? 'POST' : 'GET', 'timeout' => $timeout, 'ignore_errors' => true,
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json, application/zip\r\n",
                'content' => $post !== null ? http_build_query($post) : '',
            ]]);
            $body = (string) @file_get_contents($url, false, $context);
            $status = 0;
            $type = '';
            foreach ((array) ($http_response_header ?? []) as $header) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $m)) {
                    $status = (int) $m[1];
                } elseif (stripos($header, 'Content-Type:') === 0) {
                    $type = trim(substr($header, 13));
                }
            }

            return [$status, $body, $type];
        } catch (Throwable $e) {
            return [0, '', ''];
        }
    }
}
