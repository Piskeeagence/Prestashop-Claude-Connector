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
 * Products, combinations, stock and categories, through PrestaShop's own
 * object models so that every hook, cache and index stays consistent with
 * what the back office would have done by hand.
 */
class PiskeeConnectorCatalog
{
    /** Translated product fields an assistant may write. */
    private const PRODUCT_LANG_FIELDS = ['name', 'description_short', 'description', 'meta_title', 'meta_description', 'link_rewrite'];

    /** Translated category fields an assistant may write. */
    private const CATEGORY_LANG_FIELDS = ['name', 'description', 'additional_description', 'meta_title', 'meta_description', 'link_rewrite'];

    /**
     * General facts the assistant needs before writing anything: languages,
     * currency, tax rule groups, active theme, volumes.
     *
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function shopInfo(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $shop = new Shop($c['id_shop']);
        $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT', null, null, $c['id_shop']);
        $currency = new Currency((int) Configuration::get('PS_CURRENCY_DEFAULT', null, null, $c['id_shop']));

        $languages = [];
        foreach (Language::getLanguages(true, $c['id_shop']) as $lang) {
            $languages[] = [
                'iso_code' => Tools::strtolower((string) $lang['iso_code']),
                'name' => (string) $lang['name'],
                'default' => (int) $lang['id_lang'] === $defaultLang,
            ];
        }

        $taxGroups = [];
        foreach (TaxRulesGroup::getTaxRulesGroups(true) as $group) {
            $taxGroups[] = ['id_tax_rules_group' => (int) $group['id_tax_rules_group'], 'name' => (string) $group['name']];
        }

        $db = Db::getInstance();
        $idShop = $c['id_shop'];

        return PiskeeConnectorHelper::json([
            'shop' => [
                'name' => (string) Configuration::get('PS_SHOP_NAME', null, null, $idShop),
                'url' => 'https://' . ($shop->domain_ssl ?: $shop->domain) . $shop->getBaseURI(),
                'prestashop_version' => _PS_VERSION_,
                'active_theme' => (string) $shop->theme_name,
            ],
            'languages' => $languages,
            'default_currency' => ['iso_code' => (string) $currency->iso_code, 'symbol' => (string) $currency->sign],
            'tax_rules_groups' => $taxGroups,
            'counts' => [
                'products' => (int) $db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'product_shop` WHERE `id_shop` = ' . $idShop),
                'active_products' => (int) $db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'product_shop` WHERE `active` = 1 AND `id_shop` = ' . $idShop),
                'categories' => (int) $db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'category_shop` WHERE `id_shop` = ' . $idShop),
                'customers' => (int) $db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'customer` WHERE `deleted` = 0 AND `id_shop` = ' . $idShop),
            ],
            'connector' => [
                'name' => 'Piskee for PrestaShop',
                'support' => PiskeeConnectorBrand::helpLine(),
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function searchProducts(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $idShop = $c['id_shop'];
        $idLang = $c['id_lang'];
        $limit = PiskeeConnectorHelper::limit($args['limit'] ?? null, 20);
        $offset = PiskeeConnectorHelper::offset($args['offset'] ?? null);

        $where = ['1'];
        $query = trim((string) ($args['query'] ?? ''));
        if ($query !== '') {
            $like = pSQL(str_replace(['%', '_'], ['\\%', '\\_'], $query));
            $parts = ["pl.`name` LIKE '%" . $like . "%'", "p.`reference` LIKE '%" . $like . "%'", "p.`ean13` = '" . pSQL($query) . "'"];
            if (ctype_digit($query)) {
                $parts[] = 'p.`id_product` = ' . (int) $query;
            }
            $where[] = '(' . implode(' OR ', $parts) . ')';
        }
        if (isset($args['id_category']) && (int) $args['id_category'] > 0) {
            $where[] = 'EXISTS (SELECT 1 FROM `' . _DB_PREFIX_ . 'category_product` cp WHERE cp.`id_product` = p.`id_product` AND cp.`id_category` = ' . (int) $args['id_category'] . ')';
        }
        if (array_key_exists('active', $args) && $args['active'] !== null) {
            $where[] = 'ps.`active` = ' . ((bool) $args['active'] ? 1 : 0);
        }

        $from = ' FROM `' . _DB_PREFIX_ . 'product` p
            INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON ps.`id_product` = p.`id_product` AND ps.`id_shop` = ' . $idShop . '
            LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON pl.`id_product` = p.`id_product` AND pl.`id_lang` = ' . $idLang . ' AND pl.`id_shop` = ' . $idShop . '
            LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON cl.`id_category` = ps.`id_category_default` AND cl.`id_lang` = ' . $idLang . ' AND cl.`id_shop` = ' . $idShop . '
            WHERE ' . implode(' AND ', $where);

        $db = Db::getInstance();
        $total = (int) $db->getValue('SELECT COUNT(*)' . $from);
        $rows = $db->executeS('SELECT p.`id_product`, pl.`name`, p.`reference`, p.`ean13`, ps.`price`, ps.`active`, ps.`id_category_default`, cl.`name` AS category_name'
            . $from . ' ORDER BY p.`id_product` DESC LIMIT ' . $offset . ', ' . $limit) ?: [];

        $products = [];
        foreach ($rows as $row) {
            $id = (int) $row['id_product'];
            $products[] = [
                'id_product' => $id,
                'name' => (string) $row['name'],
                'reference' => (string) $row['reference'],
                'ean13' => (string) $row['ean13'],
                'price_tax_excl' => PiskeeConnectorHelper::money((float) $row['price']),
                'active' => (bool) $row['active'],
                'default_category' => ['id_category' => (int) $row['id_category_default'], 'name' => (string) $row['category_name']],
                'quantity' => (int) StockAvailable::getQuantityAvailableByProduct($id, 0, $idShop),
            ];
        }

        return PiskeeConnectorHelper::json([
            'total' => $total,
            'offset' => $offset,
            'limit' => $limit,
            'products' => $products,
        ]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function getProduct(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $id = (int) ($args['id_product'] ?? 0);
        $product = new Product($id, false, null, $c['id_shop']);
        if (!PiskeeConnectorHelper::inShop($product, $c['id_shop'])) {
            return PiskeeConnectorHelper::error('Product ' . $id . ' not found in this shop.');
        }

        $translations = [];
        foreach (PiskeeConnectorHelper::languages($c['id_shop']) as $iso => $idLang) {
            $entry = [];
            foreach (self::PRODUCT_LANG_FIELDS as $field) {
                $entry[$field] = (string) ($product->{$field}[$idLang] ?? '');
            }
            $translations[$iso] = $entry;
        }

        $idLang = $c['id_lang'];
        $categories = [];
        foreach (Product::getProductCategoriesFull($id, $idLang) as $cat) {
            $categories[] = ['id_category' => (int) $cat['id_category'], 'name' => (string) $cat['name']];
        }

        $features = [];
        foreach (Product::getFrontFeaturesStatic($idLang, $id) as $feature) {
            $features[] = ['name' => (string) $feature['name'], 'value' => (string) $feature['value']];
        }

        $link = Context::getContext()->link;
        $rewrite = (string) ($product->link_rewrite[$idLang] ?? '');
        $images = [];
        foreach (Image::getImages($idLang, $id, null, $c['id_shop']) as $image) {
            $images[] = [
                'id_image' => (int) $image['id_image'],
                'cover' => (bool) $image['cover'],
                'legend' => (string) $image['legend'],
                'url' => $link->getImageLink($rewrite !== '' ? $rewrite : 'product', (string) (int) $image['id_image']),
            ];
        }

        $taxGroup = new TaxRulesGroup((int) $product->id_tax_rules_group);
        $combinations = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'product_attribute` WHERE `id_product` = ' . $id);

        return PiskeeConnectorHelper::json([
            'id_product' => $id,
            'active' => (bool) $product->active,
            'reference' => (string) $product->reference,
            'ean13' => (string) $product->ean13,
            'mpn' => property_exists($product, 'mpn') ? (string) $product->mpn : '',
            'price_tax_excl' => PiskeeConnectorHelper::money((float) $product->price),
            'price_tax_incl' => PiskeeConnectorHelper::money((float) Product::getPriceStatic($id, true, null, 6, null, false, false)),
            'wholesale_price' => PiskeeConnectorHelper::money((float) $product->wholesale_price),
            'tax_rules_group' => ['id_tax_rules_group' => (int) $product->id_tax_rules_group, 'name' => (string) $taxGroup->name],
            'on_sale' => (bool) $product->on_sale,
            'weight' => (float) $product->weight,
            'condition' => (string) $product->condition,
            'visibility' => (string) $product->visibility,
            'available_for_order' => (bool) $product->available_for_order,
            'manufacturer' => (string) Manufacturer::getNameById((int) $product->id_manufacturer),
            'quantity' => (int) StockAvailable::getQuantityAvailableByProduct($id, 0, $c['id_shop']),
            'id_category_default' => (int) $product->id_category_default,
            'categories' => $categories,
            'features' => $features,
            'images' => $images,
            'combinations_count' => $combinations,
            'url' => $link->getProductLink($product, null, null, null, $idLang, $c['id_shop']),
            'translations' => $translations,
            'date_upd' => (string) $product->date_upd,
        ]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function updateProduct(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $id = (int) ($args['id_product'] ?? 0);
        $product = new Product($id, false, null, $c['id_shop']);
        if (!PiskeeConnectorHelper::inShop($product, $c['id_shop'])) {
            return PiskeeConnectorHelper::error('Product ' . $id . ' not found in this shop.');
        }

        $translations = PiskeeConnectorHelper::translations($args['translations'] ?? null, $c['id_shop']);
        if (!$translations['ok']) {
            return PiskeeConnectorHelper::error($translations['error']);
        }

        $changes = [];
        $scalars = [
            'price' => 'float', 'wholesale_price' => 'float', 'weight' => 'float',
            'reference' => 'string', 'ean13' => 'string', 'mpn' => 'string',
            'active' => 'bool', 'on_sale' => 'bool', 'available_for_order' => 'bool', 'show_price' => 'bool',
            'visibility' => 'string', 'condition' => 'string', 'id_tax_rules_group' => 'int', 'id_manufacturer' => 'int',
        ];
        foreach ($scalars as $field => $type) {
            if (!array_key_exists($field, $args) || $args[$field] === null) {
                continue;
            }
            if ($field === 'mpn' && !property_exists($product, 'mpn')) {
                continue;
            }
            $value = $args[$field];
            settype($value, $type === 'bool' ? 'boolean' : ($type === 'float' ? 'double' : ($type === 'int' ? 'integer' : 'string')));
            if ($type === 'float' && $value < 0) {
                return PiskeeConnectorHelper::error($field . ' cannot be negative.');
            }
            $old = $product->{$field};
            if (self::normalise($old, $type) !== self::normalise($value, $type)) {
                $changes[$field] = ['from' => $old, 'to' => $value];
                $product->{$field} = $value;
            }
        }

        foreach ($translations['map'] as $idLang => $fields) {
            $iso = PiskeeConnectorHelper::iso($idLang);
            foreach ($fields as $field => $value) {
                if (!in_array($field, self::PRODUCT_LANG_FIELDS, true)) {
                    return PiskeeConnectorHelper::error('Field "' . $field . '" cannot be translated here. Allowed: ' . implode(', ', self::PRODUCT_LANG_FIELDS) . '.');
                }
                $value = (string) $value;
                if ($field === 'link_rewrite') {
                    $value = Tools::str2url($value);
                }
                $old = (string) ($product->{$field}[$idLang] ?? '');
                if ($old !== $value) {
                    $changes[$field . '.' . $iso] = [
                        'from' => PiskeeConnectorHelper::excerpt($old, 120),
                        'to' => PiskeeConnectorHelper::excerpt($value, 120),
                    ];
                    $product->{$field}[$idLang] = $value;
                }
            }
        }

        if ($changes === []) {
            return PiskeeConnectorHelper::json(['id_product' => $id, 'updated' => false, 'message' => 'Nothing to change: every value was already the one requested.']);
        }

        $error = PiskeeConnectorHelper::validate($product);
        if ($error !== '') {
            return PiskeeConnectorHelper::error('Not saved: ' . $error);
        }
        if (!$product->update()) {
            return PiskeeConnectorHelper::error('PrestaShop refused to save product ' . $id . '.');
        }

        return PiskeeConnectorHelper::json(['id_product' => $id, 'updated' => true, 'changes' => $changes]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function createProduct(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $idShop = $c['id_shop'];

        $translations = PiskeeConnectorHelper::translations($args['translations'] ?? null, $idShop);
        if (!$translations['ok']) {
            return PiskeeConnectorHelper::error($translations['error']);
        }
        $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $defaultName = trim((string) ($translations['map'][$defaultLang]['name'] ?? ''));
        if ($defaultName === '') {
            return PiskeeConnectorHelper::error('A name in the default language ("' . PiskeeConnectorHelper::iso($defaultLang) . '") is required.');
        }

        $idCategory = (int) ($args['id_category_default'] ?? 0);
        $category = new Category($idCategory, null, $idShop);
        if (!PiskeeConnectorHelper::inShop($category, $c['id_shop'])) {
            return PiskeeConnectorHelper::error('Category ' . $idCategory . ' not found. Call list_categories first.');
        }

        $product = new Product(null, false, null, $idShop);
        foreach (PiskeeConnectorHelper::languages($idShop) as $idLang) {
            $fields = $translations['map'][$idLang] ?? [];
            $name = trim((string) ($fields['name'] ?? $defaultName));
            $product->name[$idLang] = $name;
            $product->link_rewrite[$idLang] = Tools::str2url((string) ($fields['link_rewrite'] ?? $name));
            foreach (['description_short', 'description', 'meta_title', 'meta_description'] as $field) {
                $product->{$field}[$idLang] = (string) ($fields[$field] ?? '');
            }
        }

        $product->price = max(0.0, (float) ($args['price'] ?? 0));
        $product->wholesale_price = max(0.0, (float) ($args['wholesale_price'] ?? 0));
        $product->reference = (string) ($args['reference'] ?? '');
        $product->ean13 = (string) ($args['ean13'] ?? '');
        $product->weight = max(0.0, (float) ($args['weight'] ?? 0));
        $product->id_category_default = $idCategory;
        $product->active = (bool) ($args['active'] ?? false);
        $product->visibility = 'both';
        if (property_exists($product, 'product_type')) {
            $product->product_type = 'standard';
        }
        $product->condition = 'new';
        $product->id_tax_rules_group = isset($args['id_tax_rules_group'])
            ? (int) $args['id_tax_rules_group']
            : $this->mostUsedTaxGroup($idShop);

        $error = PiskeeConnectorHelper::validate($product);
        if ($error !== '') {
            return PiskeeConnectorHelper::error('Not created: ' . $error);
        }
        if (!$product->add()) {
            return PiskeeConnectorHelper::error('PrestaShop refused to create the product.');
        }

        $ids = [$idCategory];
        foreach (array_map('intval', is_array($args['category_ids'] ?? null) ? $args['category_ids'] : []) as $idExtra) {
            if (PiskeeConnectorHelper::inShop(new Category($idExtra), $idShop)) {
                $ids[] = $idExtra;
            }
        }
        $product->addToCategories(array_values(array_unique($ids)));

        if (isset($args['quantity'])) {
            StockAvailable::setQuantity((int) $product->id, 0, max(0, (int) $args['quantity']), $idShop);
        }

        return PiskeeConnectorHelper::json([
            'id_product' => (int) $product->id,
            'created' => true,
            'active' => (bool) $product->active,
            'message' => $product->active
                ? 'Product created and visible in the shop.'
                : 'Product created as inactive: it is not visible until the merchant activates it.',
        ]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function setStock(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $id = (int) ($args['id_product'] ?? 0);
        $ipa = (int) ($args['id_product_attribute'] ?? 0);
        $hasQuantity = isset($args['quantity']) && $args['quantity'] !== null;
        $hasDelta = isset($args['delta']) && $args['delta'] !== null;
        if ($hasQuantity === $hasDelta) {
            return PiskeeConnectorHelper::error('Pass either quantity (the new stock) or delta (units to add or remove), not both and not neither.');
        }
        $quantity = $hasQuantity ? (int) $args['quantity'] : 0;
        if ($hasQuantity && $quantity < 0) {
            return PiskeeConnectorHelper::error('quantity must be zero or more.');
        }

        $product = new Product($id, false, null, $c['id_shop']);
        if (!PiskeeConnectorHelper::inShop($product, $c['id_shop'])) {
            return PiskeeConnectorHelper::error('Product ' . $id . ' not found in this shop.');
        }
        if ($ipa > 0) {
            $owner = (int) Db::getInstance()->getValue('SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product_attribute` WHERE `id_product_attribute` = ' . $ipa);
            if ($owner !== $id) {
                return PiskeeConnectorHelper::error('Combination ' . $ipa . ' does not belong to product ' . $id . '.');
            }
        } elseif ($product->hasAttributes()) {
            return PiskeeConnectorHelper::error('This product has combinations: its stock is set per combination. Call list_combinations and pass id_product_attribute.');
        }

        $before = (int) StockAvailable::getQuantityAvailableByProduct($id, $ipa, $c['id_shop']);
        if ($hasDelta) {
            $delta = (int) $args['delta'];
            if ($before + $delta < 0) {
                return PiskeeConnectorHelper::error('This would bring the stock below zero (' . $before . ' + ' . $delta . '). Nothing was changed.');
            }
            if ($delta === 0) {
                return PiskeeConnectorHelper::json(['id_product' => $id, 'id_product_attribute' => $ipa, 'quantity_before' => $before, 'quantity_after' => $before, 'updated' => false]);
            }
            // Relative update in SQL: no lost update if an order comes in meanwhile.
            StockAvailable::updateQuantity($id, $ipa, $delta, $c['id_shop'], true);
        } else {
            if ($quantity === $before) {
                return PiskeeConnectorHelper::json(['id_product' => $id, 'id_product_attribute' => $ipa, 'quantity_before' => $before, 'quantity_after' => $before, 'updated' => false]);
            }
            StockAvailable::setQuantity($id, $ipa, $quantity, $c['id_shop']);
        }
        if ($ipa > 0) {
            PiskeeConnectorVariants::syncProductStock($id, $c['id_shop']);
        }

        return PiskeeConnectorHelper::json([
            'id_product' => $id,
            'id_product_attribute' => $ipa,
            'quantity_before' => $before,
            'quantity_after' => (int) StockAvailable::getQuantityAvailableByProduct($id, $ipa, $c['id_shop']),
            'updated' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function listCombinations(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $id = (int) ($args['id_product'] ?? 0);
        $product = new Product($id, false, $c['id_lang'], $c['id_shop']);
        if (!PiskeeConnectorHelper::inShop($product, $c['id_shop'])) {
            return PiskeeConnectorHelper::error('Product ' . $id . ' not found in this shop.');
        }

        $combinations = [];
        foreach ($product->getAttributeCombinations($c['id_lang']) as $row) {
            $ipa = (int) $row['id_product_attribute'];
            if (!isset($combinations[$ipa])) {
                $combinations[$ipa] = [
                    'id_product_attribute' => $ipa,
                    'attributes' => [],
                    'reference' => (string) $row['reference'],
                    'ean13' => (string) $row['ean13'],
                    'price_impact_tax_excl' => PiskeeConnectorHelper::money((float) $row['price']),
                    'weight_impact' => (float) $row['weight'],
                    'default' => (bool) $row['default_on'],
                    'quantity' => (int) StockAvailable::getQuantityAvailableByProduct($id, $ipa, $c['id_shop']),
                ];
            }
            $combinations[$ipa]['attributes'][] = $row['group_name'] . ': ' . $row['attribute_name'];
        }

        foreach ($combinations as &$combination) {
            $combination['label'] = implode(', ', $combination['attributes']);
        }
        unset($combination);

        return PiskeeConnectorHelper::json([
            'id_product' => $id,
            'base_price_tax_excl' => PiskeeConnectorHelper::money((float) $product->price),
            'combinations' => array_values($combinations),
        ]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function updateCombination(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $ipa = (int) ($args['id_product_attribute'] ?? 0);
        $combination = new Combination($ipa, null, $c['id_shop']);
        if (!PiskeeConnectorHelper::inShop($combination, $c['id_shop'])) {
            return PiskeeConnectorHelper::error('Combination ' . $ipa . ' not found.');
        }

        $changes = [];
        $map = ['price_impact_tax_excl' => ['price', 'float'], 'weight_impact' => ['weight', 'float'], 'reference' => ['reference', 'string'], 'ean13' => ['ean13', 'string']];
        foreach ($map as $arg => [$field, $type]) {
            if (!array_key_exists($arg, $args) || $args[$arg] === null) {
                continue;
            }
            $value = $type === 'float' ? (float) $args[$arg] : (string) $args[$arg];
            if (self::normalise($combination->{$field}, $type) !== self::normalise($value, $type)) {
                $changes[$arg] = ['from' => self::normalise($combination->{$field}, $type), 'to' => $value];
                $combination->{$field} = $value;
            }
        }

        $makeDefault = (bool) ($args['default'] ?? false) && !(bool) $combination->default_on;
        if ($changes === [] && !$makeDefault) {
            return PiskeeConnectorHelper::json(['id_product_attribute' => $ipa, 'updated' => false, 'message' => 'Nothing to change.']);
        }

        $error = PiskeeConnectorHelper::validate($combination);
        if ($error !== '') {
            return PiskeeConnectorHelper::error('Not saved: ' . $error);
        }
        if ($changes !== [] && !$combination->update()) {
            return PiskeeConnectorHelper::error('PrestaShop refused to save combination ' . $ipa . '.');
        }
        if ($makeDefault) {
            $product = new Product((int) $combination->id_product, false, null, $c['id_shop']);
            $product->deleteDefaultAttributes();
            $product->setDefaultAttribute($ipa);
            $changes['default'] = ['from' => false, 'to' => true];
        }

        return PiskeeConnectorHelper::json(['id_product_attribute' => $ipa, 'id_product' => (int) $combination->id_product, 'updated' => true, 'changes' => $changes]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function listCategories(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $idShop = $c['id_shop'];
        $idLang = $c['id_lang'];
        $limit = PiskeeConnectorHelper::limit($args['limit'] ?? null, 100);
        $offset = PiskeeConnectorHelper::offset($args['offset'] ?? null);

        $where = ['c.`id_category` != ' . (int) Configuration::get('PS_ROOT_CATEGORY')];
        if (isset($args['id_parent']) && (int) $args['id_parent'] > 0) {
            $where[] = 'c.`id_parent` = ' . (int) $args['id_parent'];
        }
        $search = trim((string) ($args['search'] ?? ''));
        if ($search !== '') {
            $where[] = "cl.`name` LIKE '%" . pSQL(str_replace(['%', '_'], ['\\%', '\\_'], $search)) . "%'";
        }

        $rows = Db::getInstance()->executeS('SELECT c.`id_category`, c.`id_parent`, c.`level_depth`, c.`active`, cl.`name`, cl.`link_rewrite`,
                (SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'category_product` cp WHERE cp.`id_category` = c.`id_category`) AS products
            FROM `' . _DB_PREFIX_ . 'category` c
            INNER JOIN `' . _DB_PREFIX_ . 'category_shop` cs ON cs.`id_category` = c.`id_category` AND cs.`id_shop` = ' . $idShop . '
            LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON cl.`id_category` = c.`id_category` AND cl.`id_lang` = ' . $idLang . ' AND cl.`id_shop` = ' . $idShop . '
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY c.`nleft` ASC LIMIT ' . $offset . ', ' . $limit) ?: [];

        $categories = [];
        foreach ($rows as $row) {
            $categories[] = [
                'id_category' => (int) $row['id_category'],
                'id_parent' => (int) $row['id_parent'],
                'depth' => (int) $row['level_depth'],
                'name' => (string) $row['name'],
                'active' => (bool) $row['active'],
                'products' => (int) $row['products'],
                'is_home' => (int) $row['id_category'] === (int) Configuration::get('PS_HOME_CATEGORY'),
            ];
        }

        return PiskeeConnectorHelper::json(['categories' => $categories, 'offset' => $offset, 'truncated' => count($categories) === $limit, 'next_offset' => count($categories) === $limit ? $offset + $limit : null]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function getCategory(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $id = (int) ($args['id_category'] ?? 0);
        $category = new Category($id, null, $c['id_shop']);
        if (!PiskeeConnectorHelper::inShop($category, $c['id_shop'])) {
            return PiskeeConnectorHelper::error('Category ' . $id . ' not found.');
        }

        $translations = [];
        foreach (PiskeeConnectorHelper::languages($c['id_shop']) as $iso => $idLang) {
            $entry = [];
            foreach (self::CATEGORY_LANG_FIELDS as $field) {
                if (property_exists($category, $field)) {
                    $entry[$field] = (string) ($category->{$field}[$idLang] ?? '');
                }
            }
            $translations[$iso] = $entry;
        }

        return PiskeeConnectorHelper::json([
            'id_category' => $id,
            'id_parent' => (int) $category->id_parent,
            'active' => (bool) $category->active,
            'url' => Context::getContext()->link->getCategoryLink($category, null, $c['id_lang'], null, $c['id_shop']),
            'translations' => $translations,
        ]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function updateCategory(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $id = (int) ($args['id_category'] ?? 0);
        $category = new Category($id, null, $c['id_shop']);
        if (!PiskeeConnectorHelper::inShop($category, $c['id_shop']) || $category->is_root_category || $id === (int) Configuration::get('PS_ROOT_CATEGORY')) {
            return PiskeeConnectorHelper::error('Category ' . $id . ' not found or not editable.');
        }

        $translations = PiskeeConnectorHelper::translations($args['translations'] ?? null, $c['id_shop']);
        if (!$translations['ok']) {
            return PiskeeConnectorHelper::error($translations['error']);
        }

        $changes = [];
        if (array_key_exists('active', $args) && $args['active'] !== null && (bool) $category->active !== (bool) $args['active']) {
            $changes['active'] = ['from' => (bool) $category->active, 'to' => (bool) $args['active']];
            $category->active = (bool) $args['active'];
        }

        foreach ($translations['map'] as $idLang => $fields) {
            $iso = PiskeeConnectorHelper::iso($idLang);
            foreach ($fields as $field => $value) {
                if (!in_array($field, self::CATEGORY_LANG_FIELDS, true) || !property_exists($category, $field)) {
                    return PiskeeConnectorHelper::error('Field "' . $field . '" cannot be written on a category of this PrestaShop version.');
                }
                $value = (string) $value;
                if ($field === 'link_rewrite') {
                    $value = Tools::str2url($value);
                }
                $old = (string) ($category->{$field}[$idLang] ?? '');
                if ($old !== $value) {
                    $changes[$field . '.' . $iso] = ['from' => PiskeeConnectorHelper::excerpt($old, 120), 'to' => PiskeeConnectorHelper::excerpt($value, 120)];
                    $category->{$field}[$idLang] = $value;
                }
            }
        }

        if ($changes === []) {
            return PiskeeConnectorHelper::json(['id_category' => $id, 'updated' => false, 'message' => 'Nothing to change.']);
        }
        $error = PiskeeConnectorHelper::validate($category);
        if ($error !== '') {
            return PiskeeConnectorHelper::error('Not saved: ' . $error);
        }
        if (!$category->update()) {
            return PiskeeConnectorHelper::error('PrestaShop refused to save category ' . $id . '.');
        }

        return PiskeeConnectorHelper::json(['id_category' => $id, 'updated' => true, 'changes' => $changes]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function setProductCategories(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $id = (int) ($args['id_product'] ?? 0);
        $product = new Product($id, false, null, $c['id_shop']);
        if (!PiskeeConnectorHelper::inShop($product, $c['id_shop'])) {
            return PiskeeConnectorHelper::error('Product ' . $id . ' not found in this shop.');
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', is_array($args['category_ids'] ?? null) ? $args['category_ids'] : []))));
        if ($ids === []) {
            return PiskeeConnectorHelper::error('category_ids must list at least one category.');
        }
        foreach ($ids as $idCategory) {
            if (!PiskeeConnectorHelper::inShop(new Category($idCategory), $c['id_shop'])) {
                return PiskeeConnectorHelper::error('Category ' . $idCategory . ' not found. Nothing was changed.');
            }
        }

        if (isset($args['id_category_default']) && !in_array((int) $args['id_category_default'], $ids, true)) {
            return PiskeeConnectorHelper::error('id_category_default must be one of category_ids. Nothing was changed.');
        }
        $default = (int) ($args['id_category_default'] ?? $product->id_category_default);
        if (!in_array($default, $ids, true)) {
            $default = $ids[0];
        }

        $before = array_map('intval', Product::getProductCategories($id));
        $sortedBefore = $before;
        $sortedAfter = $ids;
        sort($sortedBefore);
        sort($sortedAfter);
        if ($sortedBefore === $sortedAfter && (int) $product->id_category_default === $default) {
            return PiskeeConnectorHelper::json(['id_product' => $id, 'updated' => false, 'message' => 'The product is already in exactly these categories.']);
        }
        $product->updateCategories($ids);
        if ((int) $product->id_category_default !== $default) {
            $product->id_category_default = $default;
            $product->update();
        }

        return PiskeeConnectorHelper::json([
            'id_product' => $id,
            'categories_before' => $before,
            'categories_after' => array_map('intval', Product::getProductCategories($id)),
            'id_category_default' => $default,
        ]);
    }

    /**
     * Applies update_product to up to 50 products. Each product actually
     * changed counts as one change of the free plan; when the allowance runs
     * out, the remaining products are left untouched and listed.
     *
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function bulkUpdateProducts(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $items = is_array($args['products'] ?? null) ? array_values($args['products']) : [];
        if ($items === [] || count($items) > 50) {
            return PiskeeConnectorHelper::error('products must list between 1 and 50 entries.');
        }

        $updated = [];
        $unchanged = [];
        $failed = [];
        $notDone = [];
        foreach ($items as $index => $item) {
            if (!is_array($item) || (int) ($item['id_product'] ?? 0) <= 0) {
                $failed[] = ['index' => $index, 'error' => 'id_product is missing'];
                continue;
            }
            $refusal = PiskeeConnectorLicense::gate($c['id_shop']);
            if ($refusal !== '') {
                $notDone[] = (int) $item['id_product'];
                continue;
            }
            $result = $this->updateProduct($item, $context);
            if (!empty($result['error'])) {
                $failed[] = ['id_product' => (int) $item['id_product'], 'error' => (string) $result['text']];
            } elseif (($result['json']['updated'] ?? false) === true) {
                PiskeeConnectorLicense::count($c['id_shop']);
                $updated[] = ['id_product' => (int) $item['id_product'], 'changes' => array_keys((array) $result['json']['changes'])];
            } else {
                $unchanged[] = (int) $item['id_product'];
            }
        }

        $out = ['updated' => $updated, 'unchanged' => $unchanged, 'failed' => $failed];
        if ($notDone !== []) {
            $out['not_done_free_plan_limit'] = $notDone;
            $out['free_plan_message'] = PiskeeConnectorLicense::gate($c['id_shop']);
        }
        if (!PiskeeConnectorLicense::isPro($c['id_shop'])) {
            $out['free_plan'] = [
                'changes_used_this_month' => PiskeeConnectorLicense::used($c['id_shop']),
                'monthly_allowance' => PiskeeConnectorLicense::FREE_MONTHLY_WRITES,
            ];
        }

        return PiskeeConnectorHelper::json($out);
    }

    private function mostUsedTaxGroup(int $idShop): int
    {
        return (int) Db::getInstance()->getValue('SELECT `id_tax_rules_group` FROM `' . _DB_PREFIX_ . 'product_shop`
            WHERE `id_shop` = ' . $idShop . ' GROUP BY `id_tax_rules_group` ORDER BY COUNT(*) DESC');
    }

    /**
     * Compares stored and new values the way PrestaShop stores them
     * ("19.900000" equals 19.9, false equals "0").
     *
     * @param mixed $value
     *
     * @return bool|float|int|string
     */
    private static function normalise($value, string $type)
    {
        switch ($type) {
            case 'float':
                return round((float) $value, 6);
            case 'bool':
                return (bool) (int) $value;
            case 'int':
                return (int) $value;
            default:
                return (string) $value;
        }
    }
}
