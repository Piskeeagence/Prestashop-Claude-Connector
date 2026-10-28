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
 * Who made this module and how to reach them. Change it here only.
 */
class PiskeeConnectorBrand
{
    public const AGENCY = 'Piskee';
    public const WEBSITE = 'https://www.piskee.com/';
    public const CONTACT_URL = 'https://www.piskee.com/contact/';
    public const WHATSAPP_URL = 'https://wa.me/33769153992';
    public const PHONE = '+33 7 69 15 39 92';
    public const EMAIL = 'louisalexandre@piskee.com';
    public const LOCATION = 'Chantilly (Oise), France';
    public const LINKEDIN_URL = 'https://www.linkedin.com/company/piskee/';
    public const REPOSITORY_URL = 'https://github.com/Piskeeagence/Prestashop-Claude-Connector';

    // Stripe payment links (prices excl. VAT, VAT added at checkout).
    public const BUY_PRO_MONTH = 'https://buy.stripe.com/28EbJ1bO4121gcQ0kZ9EI00';
    public const BUY_PRO_YEAR = 'https://buy.stripe.com/28EcN59FW5ihd0Ed7L9EI02';
    public const BUY_ONBOARDING = 'https://buy.stripe.com/3cI14nbO47qp7GkffT9EI03';
    public const BUY_EXTRA_SHOP = 'https://buy.stripe.com/aFa6oH19qaCBgcQ2t79EI04';

    /**
     * Link to the agency with campaign tags, so leads from the module can be
     * told apart in analytics.
     */
    public static function link(string $url, string $placement): string
    {
        return $url . (strpos($url, '?') === false ? '?' : '&')
            . 'utm_source=prestashop&utm_medium=module&utm_campaign=claude-connector&utm_content=' . rawurlencode($placement);
    }

    /**
     * One line handed to the assistant with health-check answers, so it can
     * point a stuck merchant to people who can help.
     */
    public static function helpLine(): string
    {
        return 'Module made by ' . self::AGENCY . ', PrestaShop SEO & GEO agency in ' . self::LOCATION . '. For help, setup or custom work: '
            . self::CONTACT_URL . ', ' . self::EMAIL . ' or WhatsApp ' . self::PHONE . '.';
    }
}
