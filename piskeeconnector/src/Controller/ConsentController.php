<?php
/**
 * Claude Connector by Piskee
 *
 * @author    Piskee <louisalexandre@piskee.com>
 * @copyright 2026 Piskee
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License v3.0 (AFL-3.0)
 */
declare(strict_types=1);

namespace Piskee\Connector\Controller;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Consent screen served as a back office Symfony route, so it works on
 * PrestaShop 8 and 9 without an admin URL token.
 */
class ConsentController
{
    public function index(Request $request): Response
    {
        $module = \Module::getInstanceByName('piskeeconnector');
        if (!$module || !$module->active || !\Configuration::get('PISKEECONNECTOR_ENABLED')) {
            return new Response('Piskee is not accepting new assistant connections right now.', 503, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $context = \Context::getContext();
        $employee = $context->employee;
        if (!$employee instanceof \Employee || !\Validate::isLoadedObject($employee)) {
            return new Response('Please log in to your back office, then start the connection again from your assistant.', 401, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $id = (string) $request->query->get('request', (string) $request->request->get('request', (string) $request->cookies->get(\PiskeeConnectorConsent::COOKIE, '')));
        $id = preg_match('/^[A-Za-z0-9_-]{1,128}$/', $id) ? $id : '';

        $post = null;
        if ($request->isMethod('POST')) {
            $post = [
                'piskee_decision' => (string) $request->request->get('piskee_decision', ''),
                'nonce' => (string) $request->request->get('nonce', ''),
            ];
        }

        $iso = (string) $context->language->iso_code;
        $action = $request->getSchemeAndHttpHost() . $request->getBaseUrl() . $request->getPathInfo() . '?request=' . rawurlencode($id);
        $result = \PiskeeConnectorConsent::process($employee, (int) $context->language->id, $iso, $id, $post, $action);

        if (isset($result['redirect'])) {
            return new RedirectResponse($result['redirect'], 302);
        }

        return new Response(\PiskeeConnectorConsent::page($result['vars'], $iso), 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-store',
            'X-Frame-Options' => 'DENY',
            'Content-Security-Policy' => "frame-ancestors 'none'",
        ]);
    }
}
