<?php
/**
 * Claude Connector by Piskee
 *
 * Minimal PSR-4 autoloader for the module's Symfony classes. PrestaShop
 * includes modules/<module>/vendor/autoload.php for every active module.
 *
 * @author    Piskee <louisalexandre@piskee.com>
 * @copyright 2026 Piskee
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License v3.0 (AFL-3.0)
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'Piskee\\Connector\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
