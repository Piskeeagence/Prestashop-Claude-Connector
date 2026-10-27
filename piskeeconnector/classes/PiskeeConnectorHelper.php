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
 * Shared plumbing for every tool: the shop and employee the call runs for,
 * languages by ISO code, and the two shapes a handler returns.
 */
class PiskeeConnectorHelper
{
    /** Hard ceiling on list sizes, whatever the assistant asks for. */
    public const MAX_LIMIT = 100;

    /**
     * Puts the PrestaShop context on the shop, language and employee that the
     * MCP server decided from the token. Never from the arguments.
     *
     * @param array<string, mixed> $context
     *
     * @return array{id_shop:int, id_lang:int, id_employee:int}
     */
    public static function boot(array $context): array
    {
        $idShop = (int) ($context['id_shop'] ?? 0);
        $idLang = (int) ($context['id_lang'] ?? 0);
        $idEmployee = (int) ($context['id_employee'] ?? 0);

        if ($idShop <= 0) {
            $idShop = (int) Configuration::get('PS_SHOP_DEFAULT');
        }
        if ($idLang <= 0) {
            $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
        }

        $ctx = Context::getContext();
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_SHOP, $idShop);
        }
        $ctx->shop = new Shop($idShop);
        $ctx->language = new Language($idLang);
        if ($idEmployee > 0 && (!isset($ctx->employee) || (int) $ctx->employee->id !== $idEmployee)) {
            $ctx->employee = new Employee($idEmployee);
        }
        if (!isset($ctx->currency) || !Validate::isLoadedObject($ctx->currency)) {
            $ctx->currency = new Currency((int) Configuration::get('PS_CURRENCY_DEFAULT', null, null, $idShop));
        }

        return ['id_shop' => $idShop, 'id_lang' => $idLang, 'id_employee' => $idEmployee];
    }

    /**
     * Active languages of the shop, keyed by ISO code.
     *
     * @return array<string, int>
     */
    public static function languages(int $idShop): array
    {
        $out = [];
        foreach (Language::getLanguages(true, $idShop) as $lang) {
            $out[Tools::strtolower((string) $lang['iso_code'])] = (int) $lang['id_lang'];
        }

        return $out;
    }

    /**
     * ISO code of a language id, for readable answers.
     */
    public static function iso(int $idLang): string
    {
        return Tools::strtolower((string) Language::getIsoById($idLang));
    }

    /**
     * Turns {"fr": {...}, "en": {...}} into id_lang => fields, refusing
     * unknown codes rather than silently writing to the wrong language.
     *
     * @param mixed $translations
     *
     * @return array{ok:bool, map:array<int, array<string, mixed>>, error:string}
     */
    public static function translations($translations, int $idShop): array
    {
        if ($translations === null || $translations === []) {
            return ['ok' => true, 'map' => [], 'error' => ''];
        }
        if (!is_array($translations)) {
            return ['ok' => false, 'map' => [], 'error' => 'translations must be an object keyed by language ISO code.'];
        }

        $known = self::languages($idShop);
        $map = [];
        foreach ($translations as $iso => $fields) {
            $iso = Tools::strtolower((string) $iso);
            if (!isset($known[$iso])) {
                return [
                    'ok' => false,
                    'map' => [],
                    'error' => 'Unknown language "' . $iso . '". Active languages: ' . implode(', ', array_keys($known))
                        . '. Call shop_info to read them.',
                ];
            }
            if (!is_array($fields)) {
                return ['ok' => false, 'map' => [], 'error' => 'translations.' . $iso . ' must be an object.'];
            }
            $map[$known[$iso]] = $fields;
        }

        return ['ok' => true, 'map' => $map, 'error' => ''];
    }

    /**
     * @param mixed $value
     */
    public static function limit($value, int $default = 20): int
    {
        $limit = (int) ($value ?? $default);
        if ($limit <= 0) {
            $limit = $default;
        }

        return min($limit, self::MAX_LIMIT);
    }

    /**
     * @param mixed $value
     */
    public static function offset($value): int
    {
        return max(0, (int) ($value ?? 0));
    }

    /**
     * @param mixed $data
     *
     * @return array<string, mixed>
     */
    public static function json($data): array
    {
        return ['json' => $data];
    }

    /**
     * A business failure the assistant reports instead of retrying.
     *
     * @return array<string, mixed>
     */
    /**
     * True when the object exists and belongs to the shop of the connection.
     * PrestaShop loads objects of other shops too (and would attach them on
     * save), so every tool checks this before reading or writing.
     */
    public static function inShop(?ObjectModel $object, int $idShop): bool
    {
        if ($object === null || !Validate::isLoadedObject($object)) {
            return false;
        }
        if (!Shop::isFeatureActive()) {
            return true;
        }
        $definition = ObjectModel::getDefinition($object);
        if (!Shop::isTableAssociated((string) $definition['table'])) {
            // Objects with a single shop column (customers) or shared by all.
            return !property_exists($object, 'id_shop') || (int) $object->id_shop === 0 || (int) $object->id_shop === $idShop;
        }
        try {
            return (bool) $object->isAssociatedToShop($idShop);
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function error(string $message): array
    {
        return ['text' => $message, 'error' => true];
    }

    /**
     * Validation errors of an ObjectModel, as one readable line.
     */
    public static function validate(ObjectModel $object): string
    {
        $fields = $object->validateFields(false, true);
        if ($fields !== true) {
            return is_string($fields) ? $fields : 'invalid field';
        }
        $lang = $object->validateFieldsLang(false, true);
        if ($lang !== true) {
            return is_string($lang) ? $lang : 'invalid translated field';
        }

        return '';
    }

    /**
     * Price in the shop's default currency, rounded for display.
     */
    public static function money(float $amount): float
    {
        return round($amount, 2);
    }

    /**
     * Cuts long HTML down to plain text for list views.
     */
    public static function excerpt(?string $html, int $length = 160): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $html)) ?? '');

        return Tools::strlen($text) > $length ? Tools::substr($text, 0, $length - 1) . '…' : $text;
    }

    /**
     * @param mixed $value
     */
    public static function isDate($value): bool
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && Validate::isDate($value);
    }
}
