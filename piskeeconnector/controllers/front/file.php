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
 * Download of a file made for the merchant (?k=key), until its link expires.
 */
class PiskeeconnectorFileModuleFrontController extends ModuleFrontController
{
    /** @var bool */
    public $ssl = true;

    protected function displayMaintenancePage()
    {
    }

    public function initContent()
    {
        $file = PiskeeConnectorFiles::get((string) Tools::getValue('k'));
        if ($file === null) {
            PiskeeConnectorHttp::page(404, 'Link expired', 'This download link has expired or does not exist. Ask Claude for a new one.');
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . $file['mime']);
        header('Content-Disposition: attachment; filename="' . $file['filename'] . '"');
        header('Content-Length: ' . (string) filesize($file['path']));
        header('Cache-Control: no-store, private');
        header('X-Robots-Tag: noindex');
        readfile($file['path']);
        exit;
    }
}
