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
 * Cache and maintenance mode. The debug mode is not changed from here: it
 * lives in a PHP file of the server, changed in Advanced Parameters >
 * Performance.
 */
class PiskeeConnectorSystem
{
    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function clearCache(array $args, array $context): array
    {
        PiskeeConnectorHelper::boot($context);
        $done = [];
        try {
            Tools::clearSmartyCache();
            $done[] = 'templates';
            Media::clearCache();
            $done[] = 'CSS and JS';
            if (method_exists('Tools', 'clearSf2Cache')) {
                Tools::clearSf2Cache();
                $done[] = 'PrestaShop cache';
            }
            Cache::getInstance()->flush();
            $done[] = 'data cache';
        } catch (Throwable $e) {
            return PiskeeConnectorHelper::error('The cache was only partly emptied (' . implode(', ', $done) . '): ' . Tools::substr($e->getMessage(), 0, 160));
        }
        Hook::exec('actionClearCache');

        return PiskeeConnectorHelper::json(['cleared' => $done, 'note' => 'The next page views are a little slower while PrestaShop rebuilds its cache.']);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function setMaintenance(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $idShop = $c['id_shop'];
        $group = (int) Shop::getGroupFromShop($idShop);
        $set = static function (string $key, $value, bool $html = false) use ($idShop, $group) {
            if (Shop::isFeatureActive()) {
                Configuration::updateValue($key, $value, $html, $group, $idShop);
            } else {
                Configuration::updateValue($key, $value, $html);
            }
        };
        $before = [
            'shop_open' => (bool) Configuration::get('PS_SHOP_ENABLE', null, $group, $idShop),
            'allowed_ips' => (string) Configuration::get('PS_MAINTENANCE_IP', null, $group, $idShop),
        ];
        if (!isset($args['shop_open'])) {
            return PiskeeConnectorHelper::json(['shop_open' => $before['shop_open'], 'allowed_ips' => $before['allowed_ips'], 'note' => 'Give shop_open to change it.']);
        }
        $open = (bool) $args['shop_open'];
        if (isset($args['allowed_ips'])) {
            $ips = array_values(array_filter(array_map('trim', is_array($args['allowed_ips']) ? $args['allowed_ips'] : explode(',', (string) $args['allowed_ips']))));
            foreach ($ips as $ip) {
                if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                    return PiskeeConnectorHelper::error('"' . $ip . '" is not an IP address. Nothing was changed.');
                }
            }
            $set('PS_MAINTENANCE_IP', implode(',', $ips));
        }
        if (isset($args['message'])) {
            $translations = PiskeeConnectorHelper::translations($args['message'], $idShop);
            if (!$translations['ok']) {
                return PiskeeConnectorHelper::error($translations['error']);
            }
            $text = [];
            foreach ($translations['map'] as $idLang => $fields) {
                $html = (string) ($fields['text'] ?? '');
                if (!Validate::isCleanHtml($html)) {
                    return PiskeeConnectorHelper::error('The maintenance message contains unsafe HTML.');
                }
                $text[$idLang] = $html;
            }
            if ($text !== []) {
                $current = [];
                foreach (PiskeeConnectorHelper::languages($idShop) as $idLang) {
                    $current[$idLang] = $text[$idLang] ?? (string) Configuration::get('PS_MAINTENANCE_TEXT', $idLang, $group, $idShop);
                }
                $set('PS_MAINTENANCE_TEXT', $current, true);
            }
        }
        $set('PS_SHOP_ENABLE', $open ? 1 : 0);

        return PiskeeConnectorHelper::json([
            'shop_open' => $open,
            'previous' => $before,
            'allowed_ips' => (string) Configuration::get('PS_MAINTENANCE_IP', null, $group, $idShop),
            'note' => $open ? 'The shop is open to visitors.' : 'Maintenance mode: visitors see the maintenance page, except from the allowed IP addresses. Claude keeps working.',
        ]);
    }
}
