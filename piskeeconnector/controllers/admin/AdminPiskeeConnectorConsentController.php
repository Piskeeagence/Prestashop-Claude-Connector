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
 * Consent screen: the logged-in employee sees which assistant asks for which
 * rights, and allows or refuses. The assistant then acts with this
 * employee's own PrestaShop rights, never more.
 */
class AdminPiskeeConnectorConsentController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    /**
     * The address comes from an assistant, not from a back office link, so it
     * cannot carry the usual admin token. The request id (random, single use,
     * short-lived) and the nonce below protect the decision instead.
     */
    public function checkToken()
    {
        return true;
    }

    /**
     * Any employee may connect an assistant to their own account: what the
     * assistant can do is still limited by that employee's profile.
     */
    public function viewAccess($disable = false)
    {
        return true;
    }

    public function initContent()
    {
        parent::initContent();

        $id = (string) Tools::getValue('request', (string) ($_COOKIE[PiskeeConnectorConsent::COOKIE] ?? ''));
        $post = Tools::isSubmit('piskee_decision')
            ? ['piskee_decision' => (string) Tools::getValue('piskee_decision'), 'nonce' => (string) Tools::getValue('nonce')]
            : null;
        $action = $this->context->link->getAdminLink('AdminPiskeeConnectorConsent', false) . '&request=' . rawurlencode($id);
        $result = PiskeeConnectorConsent::process($this->context->employee, (int) $this->context->language->id, (string) $this->context->language->iso_code, $id, $post, $action);

        if (isset($result['redirect'])) {
            Tools::redirect($result['redirect']);

            return;
        }
        $this->context->smarty->assign(['pkc' => $result['vars']]);
        $this->addCSS($this->module->getPathUri() . 'views/css/admin.css');
        $this->content = $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'piskeeconnector/views/templates/admin/consent.tpl');
        $this->context->smarty->assign('content', $this->content);
    }
}
