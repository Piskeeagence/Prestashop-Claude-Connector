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
 * Files made for the merchant (invoice, delivery slip, exports): kept a short
 * time in PrestaShop's cache folder, out of reach of the web, and handed out
 * by a download link whose key is random (128 bits) and expires.
 */
class PiskeeConnectorFiles
{
    public const TTL = 1800;

    private static function dir(): string
    {
        $dir = _PS_CACHE_DIR_ . 'piskeefiles/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
            @file_put_contents($dir . '.htaccess', "Require all denied\nDeny from all\n");
            @file_put_contents($dir . 'index.php', "<?php\nexit;\n");
        }

        return $dir;
    }

    /**
     * Stores a file and returns its download link.
     *
     * @return array{url:string, expires:string, filename:string}
     */
    public static function put(string $content, string $filename, string $mime, int $idShop): array
    {
        self::clean();
        $key = bin2hex(random_bytes(16));
        $dir = self::dir();
        file_put_contents($dir . $key . '.bin', $content, LOCK_EX);
        $expires = time() + self::TTL;
        file_put_contents($dir . $key . '.json', (string) json_encode([
            'filename' => preg_replace('/[^A-Za-z0-9._-]/', '-', $filename),
            'mime' => $mime,
            'expires' => $expires,
            'id_shop' => $idShop,
        ]), LOCK_EX);

        return [
            'url' => Context::getContext()->link->getModuleLink('piskeeconnector', 'file', ['k' => $key], true, null, $idShop),
            'expires' => date('Y-m-d H:i', $expires),
            'filename' => $filename,
        ];
    }

    /**
     * @return array{path:string, filename:string, mime:string}|null
     */
    public static function get(string $key): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $key)) {
            return null;
        }
        $dir = self::dir();
        $meta = json_decode((string) @file_get_contents($dir . $key . '.json'), true);
        if (!is_array($meta) || (int) ($meta['expires'] ?? 0) < time() || !is_file($dir . $key . '.bin')) {
            return null;
        }

        return ['path' => $dir . $key . '.bin', 'filename' => (string) $meta['filename'], 'mime' => (string) $meta['mime']];
    }

    /**
     * Removes expired files.
     */
    public static function clean(): void
    {
        $dir = self::dir();
        foreach (glob($dir . '*.json') ?: [] as $file) {
            $meta = json_decode((string) @file_get_contents($file), true);
            if (!is_array($meta) || (int) ($meta['expires'] ?? 0) < time()) {
                @unlink($file);
                @unlink(substr($file, 0, -5) . '.bin');
            }
        }
    }
}
