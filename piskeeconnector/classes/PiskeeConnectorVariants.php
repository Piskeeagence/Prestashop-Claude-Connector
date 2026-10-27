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
 * Variants: attribute groups and values, and the combinations built from them.
 */
class PiskeeConnectorVariants
{
    /**
     * PrestaShop 8 renamed the Attribute class to ProductAttribute.
     */
    private static function attributeClass(): string
    {
        return class_exists('ProductAttribute') ? 'ProductAttribute' : 'Attribute';
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function listAttributeGroups(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $groups = [];
        foreach (AttributeGroup::getAttributesGroups($c['id_lang']) as $group) {
            $idGroup = (int) $group['id_attribute_group'];
            $values = [];
            foreach (AttributeGroup::getAttributes($c['id_lang'], $idGroup) as $value) {
                $values[] = ['id_attribute' => (int) $value['id_attribute'], 'name' => (string) $value['name'], 'color' => (string) ($value['color'] ?? '')];
            }
            $groups[] = [
                'id_attribute_group' => $idGroup,
                'name' => (string) $group['name'],
                'public_name' => (string) $group['public_name'],
                'type' => (string) $group['group_type'],
                'values' => $values,
            ];
        }

        return PiskeeConnectorHelper::json(['attribute_groups' => $groups]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function createAttributeGroup(array $args, array $context): array
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

        $type = (string) ($args['type'] ?? 'select');
        if (!in_array($type, ['select', 'radio', 'color'], true)) {
            return PiskeeConnectorHelper::error('type must be select, radio or color.');
        }

        $group = new AttributeGroup();
        $group->group_type = $type;
        $group->is_color_group = $type === 'color';
        foreach (PiskeeConnectorHelper::languages($c['id_shop']) as $idLang) {
            $fields = $translations['map'][$idLang] ?? [];
            $group->name[$idLang] = trim((string) ($fields['name'] ?? $defaultName));
            $group->public_name[$idLang] = trim((string) ($fields['public_name'] ?? $group->name[$idLang]));
        }

        $error = PiskeeConnectorHelper::validate($group);
        if ($error !== '') {
            return PiskeeConnectorHelper::error('Not created: ' . $error);
        }
        if (!$group->add()) {
            return PiskeeConnectorHelper::error('PrestaShop refused to create the attribute group.');
        }

        return PiskeeConnectorHelper::json(['id_attribute_group' => (int) $group->id, 'created' => true]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function createAttributeValue(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $idGroup = (int) ($args['id_attribute_group'] ?? 0);
        $group = new AttributeGroup($idGroup);
        if (!PiskeeConnectorHelper::inShop($group, $c['id_shop'])) {
            return PiskeeConnectorHelper::error('Attribute group ' . $idGroup . ' not found. Call list_attribute_groups.');
        }

        $translations = PiskeeConnectorHelper::translations($args['translations'] ?? null, $c['id_shop']);
        if (!$translations['ok']) {
            return PiskeeConnectorHelper::error($translations['error']);
        }
        $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $defaultName = trim((string) ($translations['map'][$defaultLang]['name'] ?? ''));
        if ($defaultName === '') {
            return PiskeeConnectorHelper::error('A name in the default language ("' . PiskeeConnectorHelper::iso($defaultLang) . '") is required.');
        }

        foreach (AttributeGroup::getAttributes($defaultLang, $idGroup) as $existing) {
            if (Tools::strtolower(trim((string) $existing['name'])) === Tools::strtolower($defaultName)) {
                return PiskeeConnectorHelper::json(['id_attribute' => (int) $existing['id_attribute'], 'created' => false, 'message' => 'This value already exists in the group; its id is returned.']);
            }
        }

        $class = self::attributeClass();
        /** @var ObjectModel $value */
        $value = new $class();
        $value->id_attribute_group = $idGroup;
        foreach (PiskeeConnectorHelper::languages($c['id_shop']) as $idLang) {
            $value->name[$idLang] = trim((string) ($translations['map'][$idLang]['name'] ?? $defaultName));
        }
        if (!empty($args['color'])) {
            $value->color = (string) $args['color'];
        }

        $error = PiskeeConnectorHelper::validate($value);
        if ($error !== '') {
            return PiskeeConnectorHelper::error('Not created: ' . $error);
        }
        if (!$value->add()) {
            return PiskeeConnectorHelper::error('PrestaShop refused to create the attribute value.');
        }

        return PiskeeConnectorHelper::json(['id_attribute' => (int) $value->id, 'id_attribute_group' => $idGroup, 'created' => true]);
    }

    /**
     * Creates one combination per entry, each entry being one value per
     * attribute group (a size and a colour, say).
     *
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function createCombinations(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $idProduct = (int) ($args['id_product'] ?? 0);
        $product = new Product($idProduct, false, null, $c['id_shop']);
        if (!PiskeeConnectorHelper::inShop($product, $c['id_shop'])) {
            return PiskeeConnectorHelper::error('Product ' . $idProduct . ' not found in this shop.');
        }

        $entries = is_array($args['combinations'] ?? null) ? $args['combinations'] : [];
        if ($entries === [] || count($entries) > 100) {
            return PiskeeConnectorHelper::error('combinations must list between 1 and 100 entries.');
        }

        $known = [];
        foreach (Db::getInstance()->executeS('SELECT `id_attribute`, `id_attribute_group` FROM `' . _DB_PREFIX_ . 'attribute`') ?: [] as $row) {
            $known[(int) $row['id_attribute']] = (int) $row['id_attribute_group'];
        }

        $created = [];
        $skipped = [];
        $hadCombinations = $product->hasAttributes() > 0;

        foreach ($entries as $index => $entry) {
            $ids = array_values(array_unique(array_map('intval', is_array($entry['attribute_ids'] ?? null) ? $entry['attribute_ids'] : [])));
            if ($ids === []) {
                $skipped[] = ['index' => $index, 'reason' => 'attribute_ids is empty'];
                continue;
            }
            $groups = [];
            foreach ($ids as $idAttribute) {
                if (!isset($known[$idAttribute])) {
                    $skipped[] = ['index' => $index, 'reason' => 'attribute ' . $idAttribute . ' does not exist'];
                    continue 2;
                }
                if (isset($groups[$known[$idAttribute]])) {
                    $skipped[] = ['index' => $index, 'reason' => 'two values of the same attribute group'];
                    continue 2;
                }
                $groups[$known[$idAttribute]] = true;
            }
            if ($product->productAttributeExists($ids)) {
                $skipped[] = ['index' => $index, 'reason' => 'this combination already exists'];
                continue;
            }

            $combination = new Combination();
            $combination->id_product = $idProduct;
            $combination->price = (float) ($entry['price_impact_tax_excl'] ?? 0);
            $combination->weight = (float) ($entry['weight_impact'] ?? 0);
            $combination->reference = (string) ($entry['reference'] ?? '');
            $combination->ean13 = (string) ($entry['ean13'] ?? '');
            $combination->minimal_quantity = 1;
            $combination->default_on = null;

            $error = PiskeeConnectorHelper::validate($combination);
            if ($error !== '' || !$combination->add()) {
                $skipped[] = ['index' => $index, 'reason' => $error !== '' ? $error : 'PrestaShop refused the combination'];
                continue;
            }
            $combination->setAttributes($ids);

            // Always create the stock row, at 0 when no quantity is given.
            StockAvailable::setQuantity($idProduct, (int) $combination->id, max(0, (int) ($entry['quantity'] ?? 0)), $c['id_shop']);
            $created[] = ['id_product_attribute' => (int) $combination->id, 'attribute_ids' => $ids];
        }

        if ($created !== []) {
            if (!$hadCombinations || !$product->getDefaultIdProductAttribute()) {
                $product->deleteDefaultAttributes();
                $product->setDefaultAttribute($created[0]['id_product_attribute']);
            }
            self::syncProductStock($idProduct, $c['id_shop']);
        }

        return PiskeeConnectorHelper::json([
            'id_product' => $idProduct,
            'created' => $created,
            'skipped' => $skipped,
            'product_quantity' => (int) StockAvailable::getQuantityAvailableByProduct($idProduct, 0, $c['id_shop']),
        ]);
    }

    /**
     * Product level stock is the sum of its combinations.
     */
    public static function syncProductStock(int $idProduct, int $idShop): void
    {
        $ids = Db::getInstance()->executeS('SELECT `id_product_attribute` FROM `' . _DB_PREFIX_ . 'product_attribute` WHERE `id_product` = ' . $idProduct) ?: [];
        if ($ids === []) {
            return;
        }
        $sum = 0;
        foreach ($ids as $row) {
            $sum += (int) StockAvailable::getQuantityAvailableByProduct($idProduct, (int) $row['id_product_attribute'], $idShop);
        }
        StockAvailable::setQuantity($idProduct, 0, $sum, $idShop);
    }
}
