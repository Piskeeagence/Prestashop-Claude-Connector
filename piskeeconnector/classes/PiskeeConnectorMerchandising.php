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
 * Catalogue enrichment: features, brands, promotions, related products, and
 * the audit that finds what is worth fixing first.
 */
class PiskeeConnectorMerchandising
{
    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function listFeatures(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $features = [];
        foreach (Feature::getFeatures($c['id_lang']) as $feature) {
            $idFeature = (int) $feature['id_feature'];
            $values = [];
            foreach (FeatureValue::getFeatureValuesWithLang($c['id_lang'], $idFeature) as $value) {
                if ((int) ($value['custom'] ?? 0) === 1) {
                    continue;
                }
                $values[] = ['id_feature_value' => (int) $value['id_feature_value'], 'value' => (string) $value['value']];
            }
            $features[] = ['id_feature' => $idFeature, 'name' => (string) $feature['name'], 'predefined_values' => $values];
        }

        return PiskeeConnectorHelper::json(['features' => $features]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function createFeature(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $translations = PiskeeConnectorHelper::translations($args['translations'] ?? null, $c['id_shop']);
        if (!$translations['ok']) {
            return PiskeeConnectorHelper::error($translations['error']);
        }
        $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $defaultName = trim((string) ($translations['map'][$defaultLang]['name'] ?? ''));
        if ($defaultName === '') {
            return PiskeeConnectorHelper::error('A name in the default language ("' . PiskeeConnectorHelper::iso($defaultLang) . '") is required.');
        }

        foreach (Feature::getFeatures($defaultLang) as $existing) {
            if (Tools::strtolower(trim((string) $existing['name'])) === Tools::strtolower($defaultName)) {
                return PiskeeConnectorHelper::json(['id_feature' => (int) $existing['id_feature'], 'created' => false, 'message' => 'A feature with this name already exists; its id is returned.']);
            }
        }

        $feature = new Feature();
        foreach (PiskeeConnectorHelper::languages($c['id_shop']) as $idLang) {
            $feature->name[$idLang] = trim((string) ($translations['map'][$idLang]['name'] ?? $defaultName));
        }
        $error = PiskeeConnectorHelper::validate($feature);
        if ($error !== '' || !$feature->add()) {
            return PiskeeConnectorHelper::error('Not created: ' . ($error !== '' ? $error : 'PrestaShop refused the feature.'));
        }

        return PiskeeConnectorHelper::json(['id_feature' => (int) $feature->id, 'created' => true]);
    }

    /**
     * Sets one feature on a product, either to a predefined value or to a
     * free text written for this product only.
     *
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function setProductFeature(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $idProduct = (int) ($args['id_product'] ?? 0);
        $idFeature = (int) ($args['id_feature'] ?? 0);
        if (!PiskeeConnectorHelper::inShop(new Product($idProduct, false, null, $c['id_shop']), $c['id_shop'])) {
            return PiskeeConnectorHelper::error('Product ' . $idProduct . ' not found in this shop.');
        }
        if (!PiskeeConnectorHelper::inShop(new Feature($idFeature), $c['id_shop'])) {
            return PiskeeConnectorHelper::error('Feature ' . $idFeature . ' not found. Call list_features.');
        }

        $idValue = (int) ($args['id_feature_value'] ?? 0);
        $custom = $args['custom_value'] ?? null;
        if (($idValue > 0) === ($custom !== null && $custom !== [])) {
            return PiskeeConnectorHelper::error('Pass either id_feature_value (a predefined value) or custom_value (free text per language), not both.');
        }

        $db = Db::getInstance();
        // The previous value of this feature on this product goes, custom text included.
        $old = $db->executeS('SELECT fp.`id_feature_value`, fv.`custom` FROM `' . _DB_PREFIX_ . 'feature_product` fp
            LEFT JOIN `' . _DB_PREFIX_ . 'feature_value` fv ON fv.`id_feature_value` = fp.`id_feature_value`
            WHERE fp.`id_product` = ' . $idProduct . ' AND fp.`id_feature` = ' . $idFeature) ?: [];

        if ($idValue > 0) {
            $value = new FeatureValue($idValue);
            if (!Validate::isLoadedObject($value) || (int) $value->id_feature !== $idFeature) {
                return PiskeeConnectorHelper::error('Value ' . $idValue . ' does not belong to feature ' . $idFeature . '.');
            }
        } else {
            $translations = PiskeeConnectorHelper::translations($custom, $c['id_shop']);
            if (!$translations['ok']) {
                return PiskeeConnectorHelper::error($translations['error']);
            }
            $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');
            $defaultText = trim((string) ($translations['map'][$defaultLang]['value'] ?? ''));
            if ($defaultText === '') {
                return PiskeeConnectorHelper::error('custom_value needs a "value" in the default language ("' . PiskeeConnectorHelper::iso($defaultLang) . '").');
            }
            $value = new FeatureValue();
            $value->id_feature = $idFeature;
            $value->custom = 1;
            foreach (PiskeeConnectorHelper::languages($c['id_shop']) as $idLang) {
                $value->value[$idLang] = trim((string) ($translations['map'][$idLang]['value'] ?? $defaultText));
            }
            if (!$value->add()) {
                return PiskeeConnectorHelper::error('PrestaShop refused the custom value.');
            }
            $idValue = (int) $value->id;
        }

        $db->delete('feature_product', '`id_product` = ' . $idProduct . ' AND `id_feature` = ' . $idFeature);
        foreach ($old as $row) {
            if ((int) $row['custom'] === 1 && (int) $row['id_feature_value'] !== $idValue) {
                (new FeatureValue((int) $row['id_feature_value']))->delete();
            }
        }
        $db->insert('feature_product', ['id_feature' => $idFeature, 'id_product' => $idProduct, 'id_feature_value' => $idValue]);
        // Layered navigation and search read features: refresh them.
        Search::indexation(false, $idProduct);
        Hook::exec('actionProductUpdate', ['id_product' => $idProduct, 'product' => new Product($idProduct)]);

        return PiskeeConnectorHelper::json(['id_product' => $idProduct, 'id_feature' => $idFeature, 'id_feature_value' => $idValue, 'updated' => true]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function listBrands(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $brands = [];
        foreach (Manufacturer::getManufacturers(true, $c['id_lang'], false) as $brand) {
            $brands[] = ['id_manufacturer' => (int) $brand['id_manufacturer'], 'name' => (string) $brand['name'], 'active' => (bool) $brand['active'], 'products' => (int) ($brand['nb_products'] ?? 0)];
        }

        return PiskeeConnectorHelper::json(['brands' => $brands]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function listPromotions(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $idProduct = (int) ($args['id_product'] ?? 0);
        $rows = Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'specific_price`
            WHERE `id_product` = ' . $idProduct . ' AND `id_shop` IN (0, ' . $c['id_shop'] . ') AND `id_cart` = 0
            ORDER BY `id_specific_price` DESC LIMIT 50') ?: [];

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id_specific_price' => (int) $row['id_specific_price'],
                'id_product_attribute' => (int) $row['id_product_attribute'],
                'reduction' => $row['reduction_type'] === 'percentage' ? round((float) $row['reduction'] * 100, 2) . ' %' : (float) $row['reduction'] . ' (amount)',
                'reduction_tax_included' => (bool) $row['reduction_tax'],
                'from_quantity' => (int) $row['from_quantity'],
                'from' => (string) $row['from'],
                'to' => (string) $row['to'],
                'restricted_to' => [
                    'group' => (int) $row['id_group'], 'customer' => (int) $row['id_customer'],
                    'country' => (int) $row['id_country'], 'currency' => (int) $row['id_currency'],
                ],
            ];
        }

        return PiskeeConnectorHelper::json(['id_product' => $idProduct, 'promotions' => $out]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function createPromotion(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $idProduct = (int) ($args['id_product'] ?? 0);
        if (!PiskeeConnectorHelper::inShop(new Product($idProduct, false, null, $c['id_shop']), $c['id_shop'])) {
            return PiskeeConnectorHelper::error('Product ' . $idProduct . ' not found in this shop.');
        }

        $type = (string) ($args['type'] ?? '');
        $value = (float) ($args['value'] ?? 0);
        if (!in_array($type, ['percentage', 'amount'], true) || $value <= 0) {
            return PiskeeConnectorHelper::error('type must be percentage or amount, and value must be above zero.');
        }
        if ($type === 'percentage' && $value >= 100) {
            return PiskeeConnectorHelper::error('A percentage reduction must stay below 100.');
        }

        $from = (string) ($args['date_from'] ?? '');
        $to = (string) ($args['date_to'] ?? '');
        foreach (['date_from' => $from, 'date_to' => $to] as $key => $date) {
            if ($date !== '' && !PiskeeConnectorHelper::isDate($date)) {
                return PiskeeConnectorHelper::error($key . ' must be written YYYY-MM-DD.');
            }
        }

        $idCombination = (int) ($args['id_product_attribute'] ?? 0);
        if ($idCombination > 0 && (int) Db::getInstance()->getValue('SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product_attribute` WHERE `id_product_attribute` = ' . $idCombination) !== $idProduct) {
            return PiskeeConnectorHelper::error('Combination ' . $idCombination . ' does not belong to product ' . $idProduct . '.');
        }
        $idGroup = (int) ($args['id_group'] ?? 0);
        if ($idGroup > 0 && !PiskeeConnectorHelper::inShop(new Group($idGroup), $c['id_shop'])) {
            return PiskeeConnectorHelper::error('Customer group ' . $idGroup . ' not found in this shop.');
        }

        $price = new SpecificPrice();
        $price->id_product = $idProduct;
        $price->id_product_attribute = $idCombination;
        $price->id_shop = $c['id_shop'];
        $price->id_shop_group = 0;
        $price->id_currency = 0;
        $price->id_country = 0;
        $price->id_group = $idGroup;
        $price->id_customer = 0;
        $price->id_cart = 0;
        $price->from_quantity = max(1, (int) ($args['from_quantity'] ?? 1));
        $price->price = -1;
        $price->reduction_type = $type;
        $price->reduction = $type === 'percentage' ? $value / 100 : $value;
        $price->reduction_tax = (bool) ($args['tax_included'] ?? true) ? 1 : 0;
        $price->from = $from !== '' ? $from . ' 00:00:00' : '0000-00-00 00:00:00';
        $price->to = $to !== '' ? $to . ' 23:59:59' : '0000-00-00 00:00:00';

        if (!$price->add()) {
            return PiskeeConnectorHelper::error('PrestaShop refused the promotion.');
        }

        return PiskeeConnectorHelper::json([
            'id_specific_price' => (int) $price->id,
            'created' => true,
            'new_price_tax_incl' => PiskeeConnectorHelper::money((float) Product::getPriceStatic($idProduct, true, $price->id_product_attribute ?: null)),
        ]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function deletePromotion(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $id = (int) ($args['id_specific_price'] ?? 0);
        $price = new SpecificPrice($id);
        if (!Validate::isLoadedObject($price) || !in_array((int) $price->id_shop, [0, $c['id_shop']], true) || (int) $price->id_cart !== 0) {
            return PiskeeConnectorHelper::error('Promotion ' . $id . ' not found in this shop.');
        }
        if (!$price->delete()) {
            return PiskeeConnectorHelper::error('PrestaShop refused to remove promotion ' . $id . '.');
        }

        return PiskeeConnectorHelper::json(['id_specific_price' => $id, 'deleted' => true]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function setRelatedProducts(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $idProduct = (int) ($args['id_product'] ?? 0);
        $product = new Product($idProduct, false, null, $c['id_shop']);
        if (!PiskeeConnectorHelper::inShop($product, $c['id_shop'])) {
            return PiskeeConnectorHelper::error('Product ' . $idProduct . ' not found in this shop.');
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', is_array($args['related_ids'] ?? null) ? $args['related_ids'] : []), static function (int $id) use ($idProduct) {
            return $id > 0 && $id !== $idProduct;
        })));
        foreach ($ids as $id) {
            if (!PiskeeConnectorHelper::inShop(new Product($id, false, null, $c['id_shop']), $c['id_shop'])) {
                return PiskeeConnectorHelper::error('Product ' . $id . ' not found. Nothing was changed.');
            }
        }

        $product->deleteAccessories();
        if ($ids !== []) {
            $product->changeAccessories($ids);
        }

        return PiskeeConnectorHelper::json(['id_product' => $idProduct, 'related_ids' => $ids]);
    }

    /**
     * Finds what is worth fixing in the catalogue, in one language, and
     * returns counts plus a sample of product ids per issue.
     *
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function catalogAudit(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $idShop = $c['id_shop'];
        $idLang = $c['id_lang'];
        if (!empty($args['language'])) {
            $languages = PiskeeConnectorHelper::languages($idShop);
            $iso = Tools::strtolower((string) $args['language']);
            if (!isset($languages[$iso])) {
                return PiskeeConnectorHelper::error('Unknown language "' . $iso . '". Active: ' . implode(', ', array_keys($languages)) . '.');
            }
            $idLang = $languages[$iso];
        }
        $sample = PiskeeConnectorHelper::limit($args['sample'] ?? null, 20);
        $db = Db::getInstance();
        $p = _DB_PREFIX_;

        $base = 'FROM `' . $p . 'product` p
            INNER JOIN `' . $p . 'product_shop` ps ON ps.`id_product` = p.`id_product` AND ps.`id_shop` = ' . $idShop . '
            LEFT JOIN `' . $p . 'product_lang` pl ON pl.`id_product` = p.`id_product` AND pl.`id_lang` = ' . $idLang . ' AND pl.`id_shop` = ' . $idShop . '
            LEFT JOIN `' . $p . 'stock_available` sa ON sa.`id_product` = p.`id_product` AND sa.`id_product_attribute` = 0 ' . StockAvailable::addSqlShopRestriction(null, $idShop, 'sa') . '
            WHERE ';

        $checks = [
            'description_missing_or_thin' => "ps.`active` = 1 AND CHAR_LENGTH(TRIM(REGEXP_REPLACE(COALESCE(pl.`description`, ''), '<[^>]*>', ''))) < 200",
            'short_description_missing' => "ps.`active` = 1 AND CHAR_LENGTH(TRIM(REGEXP_REPLACE(COALESCE(pl.`description_short`, ''), '<[^>]*>', ''))) < 20",
            'meta_title_missing' => "ps.`active` = 1 AND TRIM(COALESCE(pl.`meta_title`, '')) = ''",
            'meta_title_too_long' => 'ps.`active` = 1 AND CHAR_LENGTH(pl.`meta_title`) > 60',
            'meta_description_missing' => "ps.`active` = 1 AND TRIM(COALESCE(pl.`meta_description`, '')) = ''",
            'meta_description_too_long' => 'ps.`active` = 1 AND CHAR_LENGTH(pl.`meta_description`) > 160',
            'no_image' => 'ps.`active` = 1 AND NOT EXISTS (SELECT 1 FROM `' . $p . 'image` i WHERE i.`id_product` = p.`id_product`)',
            'images_without_alt_text' => 'ps.`active` = 1 AND EXISTS (SELECT 1 FROM `' . $p . 'image` i LEFT JOIN `' . $p . 'image_lang` il ON il.`id_image` = i.`id_image` AND il.`id_lang` = ' . $idLang . " WHERE i.`id_product` = p.`id_product` AND TRIM(COALESCE(il.`legend`, '')) = '')",
            'no_ean' => "ps.`active` = 1 AND TRIM(COALESCE(p.`ean13`, '')) = ''",
            'no_brand' => 'ps.`active` = 1 AND p.`id_manufacturer` = 0',
            'no_features' => 'ps.`active` = 1 AND NOT EXISTS (SELECT 1 FROM `' . $p . 'feature_product` fp WHERE fp.`id_product` = p.`id_product`)',
            'only_in_home_category' => 'ps.`active` = 1 AND NOT EXISTS (SELECT 1 FROM `' . $p . 'category_product` cp WHERE cp.`id_product` = p.`id_product` AND cp.`id_category` NOT IN (' . (int) Configuration::get('PS_HOME_CATEGORY') . ', ' . (int) Configuration::get('PS_ROOT_CATEGORY') . '))',
            'active_but_out_of_stock' => 'ps.`active` = 1 AND COALESCE(sa.`quantity`, 0) <= 0',
            'inactive_but_in_stock' => 'ps.`active` = 0 AND COALESCE(sa.`quantity`, 0) > 0',
            'price_zero' => 'ps.`active` = 1 AND ps.`price` <= 0 AND NOT EXISTS (SELECT 1 FROM `' . $p . 'product_attribute` pa WHERE pa.`id_product` = p.`id_product` AND pa.`price` > 0)',
        ];

        $issues = [];
        foreach ($checks as $key => $condition) {
            try {
                $raw = $db->getValue('SELECT COUNT(DISTINCT p.`id_product`) ' . $base . $condition);
                if ($raw === false && $db->getNumberError()) {
                    throw new RuntimeException($db->getMsgError());
                }
                $count = (int) $raw;
                $ids = $count > 0 ? array_map('intval', array_column($db->executeS('SELECT DISTINCT p.`id_product` ' . $base . $condition . ' ORDER BY p.`id_product` DESC LIMIT ' . $sample) ?: [], 'id_product')) : [];
            } catch (Throwable $e) {
                // REGEXP_REPLACE needs MySQL 8 or MariaDB 10.0.5; older servers skip that check.
                $issues[$key] = ['count' => null, 'note' => 'not available on this database server'];
                continue;
            }
            $issues[$key] = ['count' => $count, 'sample_ids' => $ids];
        }

        $duplicates = $db->executeS('SELECT pl.`meta_title`, COUNT(*) AS n, SUBSTRING_INDEX(GROUP_CONCAT(pl.`id_product` ORDER BY pl.`id_product` SEPARATOR \',\'), \',\', 20) AS ids
            FROM `' . $p . 'product_lang` pl
            INNER JOIN `' . $p . 'product_shop` ps ON ps.`id_product` = pl.`id_product` AND ps.`id_shop` = ' . $idShop . ' AND ps.`active` = 1
            WHERE pl.`id_lang` = ' . $idLang . ' AND pl.`id_shop` = ' . $idShop . " AND TRIM(pl.`meta_title`) != ''
            GROUP BY pl.`meta_title` HAVING n > 1 ORDER BY n DESC LIMIT " . $sample) ?: [];
        $issues['duplicate_meta_titles'] = [
            'count' => count($duplicates),
            'groups' => array_map(static function (array $row) {
                return ['meta_title' => (string) $row['meta_title'], 'product_ids' => array_map('intval', explode(',', (string) $row['ids']))];
            }, $duplicates),
        ];

        $categoryIssues = (int) $db->getValue('SELECT COUNT(*) FROM `' . $p . 'category` c
            INNER JOIN `' . $p . 'category_shop` cs ON cs.`id_category` = c.`id_category` AND cs.`id_shop` = ' . $idShop . '
            LEFT JOIN `' . $p . 'category_lang` cl ON cl.`id_category` = c.`id_category` AND cl.`id_lang` = ' . $idLang . ' AND cl.`id_shop` = ' . $idShop . "
            WHERE c.`active` = 1 AND c.`level_depth` > 1 AND CHAR_LENGTH(TRIM(COALESCE(cl.`description`, ''))) < 50");

        return PiskeeConnectorHelper::json([
            'language' => PiskeeConnectorHelper::iso($idLang),
            'active_products' => (int) $db->getValue('SELECT COUNT(*) FROM `' . $p . 'product_shop` WHERE `active` = 1 AND `id_shop` = ' . $idShop),
            'product_issues' => $issues,
            'categories_with_missing_or_thin_description' => $categoryIssues,
            'advice' => 'Fix in this order: products with no image or no category, then titles and meta descriptions, then descriptions, then alt texts and features.',
        ]);
    }
}
