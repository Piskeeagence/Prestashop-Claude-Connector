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
 * CMS pages (delivery, terms, about...), mainly for SEO and translation work.
 */
class PiskeeConnectorCms
{
    private const LANG_FIELDS = ['meta_title', 'head_seo_title', 'meta_description', 'content', 'link_rewrite'];

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function listPages(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $rows = Db::getInstance()->executeS('SELECT c.`id_cms`, c.`active`, cl.`meta_title`, cl.`link_rewrite`, CHAR_LENGTH(cl.`content`) AS length
            FROM `' . _DB_PREFIX_ . 'cms` c
            INNER JOIN `' . _DB_PREFIX_ . 'cms_shop` cs ON cs.`id_cms` = c.`id_cms` AND cs.`id_shop` = ' . $c['id_shop'] . '
            LEFT JOIN `' . _DB_PREFIX_ . 'cms_lang` cl ON cl.`id_cms` = c.`id_cms` AND cl.`id_lang` = ' . $c['id_lang'] . ' AND cl.`id_shop` = ' . $c['id_shop'] . '
            ORDER BY c.`position` ASC LIMIT 200') ?: [];

        return PiskeeConnectorHelper::json(['pages' => array_map(static function (array $row) {
            return ['id_cms' => (int) $row['id_cms'], 'title' => (string) $row['meta_title'], 'slug' => (string) $row['link_rewrite'], 'active' => (bool) $row['active'], 'content_length' => (int) $row['length']];
        }, $rows)]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function getPage(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $id = (int) ($args['id_cms'] ?? 0);
        $page = new CMS($id, null, $c['id_shop']);
        if (!PiskeeConnectorHelper::inShop($page, $c['id_shop'])) {
            return PiskeeConnectorHelper::error('CMS page ' . $id . ' not found.');
        }
        $translations = [];
        foreach (PiskeeConnectorHelper::languages($c['id_shop']) as $iso => $idLang) {
            foreach (self::LANG_FIELDS as $field) {
                if (property_exists($page, $field)) {
                    $translations[$iso][$field] = (string) ($page->{$field}[$idLang] ?? '');
                }
            }
        }

        return PiskeeConnectorHelper::json(['id_cms' => $id, 'active' => (bool) $page->active, 'translations' => $translations]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function updatePage(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $id = (int) ($args['id_cms'] ?? 0);
        $page = new CMS($id, null, $c['id_shop']);
        if (!PiskeeConnectorHelper::inShop($page, $c['id_shop'])) {
            return PiskeeConnectorHelper::error('CMS page ' . $id . ' not found.');
        }
        $translations = PiskeeConnectorHelper::translations($args['translations'] ?? null, $c['id_shop']);
        if (!$translations['ok']) {
            return PiskeeConnectorHelper::error($translations['error']);
        }

        $changes = [];
        foreach ($translations['map'] as $idLang => $fields) {
            $iso = PiskeeConnectorHelper::iso($idLang);
            foreach ($fields as $field => $value) {
                if (!in_array($field, self::LANG_FIELDS, true) || !property_exists($page, $field)) {
                    return PiskeeConnectorHelper::error('Field "' . $field . '" cannot be written on a CMS page of this PrestaShop version.');
                }
                $value = $field === 'link_rewrite' ? Tools::str2url((string) $value) : (string) $value;
                $old = (string) ($page->{$field}[$idLang] ?? '');
                if ($old !== $value) {
                    $changes[$field . '.' . $iso] = ['from' => PiskeeConnectorHelper::excerpt($old, 120), 'to' => PiskeeConnectorHelper::excerpt($value, 120)];
                    $page->{$field}[$idLang] = $value;
                }
            }
        }
        if (array_key_exists('active', $args) && $args['active'] !== null && (bool) $page->active !== (bool) $args['active']) {
            $changes['active'] = ['from' => (bool) $page->active, 'to' => (bool) $args['active']];
            $page->active = (bool) $args['active'];
        }
        if ($changes === []) {
            return PiskeeConnectorHelper::json(['id_cms' => $id, 'updated' => false, 'message' => 'Nothing to change.']);
        }
        $error = PiskeeConnectorHelper::validate($page);
        if ($error !== '' || !$page->update()) {
            return PiskeeConnectorHelper::error('Not saved: ' . ($error !== '' ? $error : 'PrestaShop refused the page.'));
        }

        return PiskeeConnectorHelper::json(['id_cms' => $id, 'updated' => true, 'changes' => $changes]);
    }
}
