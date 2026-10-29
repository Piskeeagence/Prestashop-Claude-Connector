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
 * Vouchers, promotions on many products at once, and catalogue price rules.
 */
class PiskeeConnectorSales
{
    private const BULK_MAX = 300;

    // ------------------------------------------------------------------
    // Vouchers
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function listVouchers(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $where = ['1'];
        $search = trim((string) ($args['search'] ?? ''));
        if ($search !== '') {
            $like = pSQL(str_replace(['%', '_'], ['\\%', '\\_'], $search));
            $where[] = "(cr.`code` LIKE '%" . $like . "%' OR crl.`name` LIKE '%" . $like . "%')";
        }
        $status = (string) ($args['status'] ?? 'all');
        if ($status === 'active') {
            $where[] = "cr.`active` = 1 AND cr.`quantity` > 0 AND cr.`date_to` >= '" . date('Y-m-d H:i:s') . "'";
        } elseif ($status === 'expired') {
            $where[] = "(cr.`date_to` < '" . date('Y-m-d H:i:s') . "' OR cr.`quantity` <= 0)";
        }
        $limit = PiskeeConnectorHelper::limit($args['limit'] ?? null, 50);
        $rows = Db::getInstance()->executeS('SELECT cr.*, crl.`name` FROM `' . _DB_PREFIX_ . 'cart_rule` cr'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'cart_rule_lang` crl ON crl.`id_cart_rule` = cr.`id_cart_rule` AND crl.`id_lang` = ' . (int) $c['id_lang']
            . ' WHERE ' . implode(' AND ', $where) . ' AND (cr.`shop_restriction` = 0 OR EXISTS (SELECT 1 FROM `' . _DB_PREFIX_ . 'cart_rule_shop` crs WHERE crs.`id_cart_rule` = cr.`id_cart_rule` AND crs.`id_shop` = ' . (int) $c['id_shop'] . '))'
            . ' ORDER BY cr.`id_cart_rule` DESC LIMIT ' . $limit) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row['id_cart_rule'];
            $out[] = [
                'id_cart_rule' => $id,
                'name' => (string) $row['name'],
                'code' => (string) $row['code'],
                'active' => (bool) $row['active'],
                'discount' => (float) $row['reduction_percent'] > 0 ? round((float) $row['reduction_percent'], 2) . ' %'
                    : ((float) $row['reduction_amount'] > 0 ? (float) $row['reduction_amount'] . ($row['reduction_tax'] ? ' tax incl.' : ' tax excl.') : ''),
                'free_shipping' => (bool) $row['free_shipping'],
                'from' => (string) $row['date_from'],
                'to' => (string) $row['date_to'],
                'uses_left' => (int) $row['quantity'],
                'used' => (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'order_cart_rule` WHERE `id_cart_rule` = ' . $id),
                'id_customer' => (int) $row['id_customer'],
                'minimum_amount' => (float) $row['minimum_amount'],
            ];
        }

        return PiskeeConnectorHelper::json(['vouchers' => $out, 'count' => count($out)]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function deleteVoucher(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $id = (int) ($args['id_cart_rule'] ?? 0);
        $rule = new CartRule($id);
        if (!Validate::isLoadedObject($rule)) {
            return PiskeeConnectorHelper::error('Voucher ' . $id . ' not found. Call list_vouchers.');
        }
        if ($rule->shop_restriction && !Db::getInstance()->getValue('SELECT 1 FROM `' . _DB_PREFIX_ . 'cart_rule_shop` WHERE `id_cart_rule` = ' . $id . ' AND `id_shop` = ' . (int) $c['id_shop'])) {
            return PiskeeConnectorHelper::error('Voucher ' . $id . ' belongs to another shop.');
        }
        $used = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'order_cart_rule` WHERE `id_cart_rule` = ' . $id);
        $code = (string) $rule->code;
        if (!empty($args['deactivate_only']) || $used > 0) {
            // A voucher already used by orders stays, deactivated: the orders keep their discount line.
            $rule->active = 0;
            if (!$rule->update()) {
                return PiskeeConnectorHelper::error('PrestaShop refused to deactivate the voucher.');
            }

            return PiskeeConnectorHelper::json(['id_cart_rule' => $id, 'code' => $code, 'deleted' => false, 'deactivated' => true,
                'message' => $used > 0 ? 'Used by ' . $used . ' order(s): deactivated rather than deleted, so those orders keep their discount line.' : 'Deactivated.']);
        }
        if (!$rule->delete()) {
            return PiskeeConnectorHelper::error('PrestaShop refused to delete the voucher.');
        }

        return PiskeeConnectorHelper::json(['id_cart_rule' => $id, 'code' => $code, 'deleted' => true]);
    }

    // ------------------------------------------------------------------
    // Promotions on many products
    // ------------------------------------------------------------------

    /**
     * One reduction on every product of a category (and its subcategories), of a brand,
     * of a list, or of the whole catalogue.
     *
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function bulkCreatePromotion(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $idShop = $c['id_shop'];
        $ids = $this->targetProducts($args, $idShop);
        if (is_string($ids)) {
            return PiskeeConnectorHelper::error($ids);
        }
        if ($ids === []) {
            return PiskeeConnectorHelper::error('No active product matches.');
        }
        if (count($ids) > self::BULK_MAX) {
            return PiskeeConnectorHelper::error(count($ids) . ' products match, more than ' . self::BULK_MAX . ' at once. For the whole catalogue or a big range, use create_price_rule (one catalogue price rule instead of one promotion per product).');
        }
        $merch = new PiskeeConnectorMerchandising();
        $created = [];
        $refused = [];
        $notDone = [];
        foreach ($ids as $id) {
            if (PiskeeConnectorLicense::gate($idShop) !== '') {
                $notDone[] = $id;
                continue;
            }
            $one = $merch->createPromotion(['id_product' => $id] + array_intersect_key($args, array_flip(['type', 'value', 'date_from', 'date_to', 'from_quantity', 'id_group', 'tax_included', 'replace_existing'])), $context);
            if (!empty($one['error'])) {
                $refused[] = ['id_product' => $id, 'reason' => PiskeeConnectorHelper::excerpt((string) $one['text'], 200)];
                if (count($refused) === 1 && strpos((string) $one['text'], 'type must be') !== false) {
                    return PiskeeConnectorHelper::error((string) $one['text']);
                }
                continue;
            }
            PiskeeConnectorLicense::count($idShop);
            $created[] = ['id_product' => $id, 'id_specific_price' => (int) ($one['json']['id_specific_price'] ?? 0)];
        }

        return PiskeeConnectorHelper::json([
            'products' => count($ids),
            'created' => count($created),
            'promotions' => $created,
            'not_created' => $refused,
            'not_done_free_plan_limit' => $notDone,
            'note' => $refused !== [] ? 'Products that already had a promotion for the period were left as they are; call again with replace_existing: true if the merchant wants to replace them.' : '',
        ]);
    }

    /**
     * Product ids targeted by a bulk action: ids, a category (and subcategories), a brand, or all.
     *
     * @param array<string, mixed> $args
     *
     * @return array<int, int>|string
     */
    public function targetProducts(array $args, int $idShop)
    {
        $db = Db::getInstance();
        $base = 'SELECT DISTINCT p.`id_product` FROM `' . _DB_PREFIX_ . 'product` p INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON ps.`id_product` = p.`id_product` AND ps.`id_shop` = ' . $idShop;
        $active = empty($args['include_inactive']) ? ' AND ps.`active` = 1' : '';
        if (!empty($args['product_ids']) && is_array($args['product_ids'])) {
            $ids = array_values(array_unique(array_filter(array_map('intval', $args['product_ids']))));

            return $ids === [] ? [] : array_map('intval', array_column($db->executeS($base . ' WHERE p.`id_product` IN (' . implode(',', $ids) . ')' . $active) ?: [], 'id_product'));
        }
        if (!empty($args['id_category'])) {
            $category = new Category((int) $args['id_category'], null, $idShop);
            if (!PiskeeConnectorHelper::inShop($category, $idShop)) {
                return 'Category ' . (int) $args['id_category'] . ' not found.';
            }
            $where = !isset($args['include_subcategories']) || $args['include_subcategories']
                ? 'c.`nleft` >= ' . (int) $category->nleft . ' AND c.`nright` <= ' . (int) $category->nright
                : 'c.`id_category` = ' . (int) $category->id;

            return array_map('intval', array_column($db->executeS($base . ' INNER JOIN `' . _DB_PREFIX_ . 'category_product` cp ON cp.`id_product` = p.`id_product`'
                . ' INNER JOIN `' . _DB_PREFIX_ . 'category` c ON c.`id_category` = cp.`id_category` WHERE ' . $where . $active) ?: [], 'id_product'));
        }
        if (!empty($args['id_manufacturer'])) {
            return array_map('intval', array_column($db->executeS($base . ' WHERE p.`id_manufacturer` = ' . (int) $args['id_manufacturer'] . $active) ?: [], 'id_product'));
        }
        if (!empty($args['all_products'])) {
            return array_map('intval', array_column($db->executeS($base . ' WHERE 1' . $active) ?: [], 'id_product'));
        }

        return 'Say which products: product_ids, id_category, id_manufacturer or all_products: true.';
    }

    // ------------------------------------------------------------------
    // Catalogue price rules
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function listPriceRules(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $rows = Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'specific_price_rule` WHERE `id_shop` IN (0, ' . (int) $c['id_shop'] . ') ORDER BY `id_specific_price_rule` DESC') ?: [];
        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row['id_specific_price_rule'];
            $conditions = Db::getInstance()->executeS('SELECT cg.`id_specific_price_rule_condition_group` AS g, c.`type`, c.`value` FROM `' . _DB_PREFIX_ . 'specific_price_rule_condition_group` cg'
                . ' LEFT JOIN `' . _DB_PREFIX_ . 'specific_price_rule_condition` c ON c.`id_specific_price_rule_condition_group` = cg.`id_specific_price_rule_condition_group`'
                . ' WHERE cg.`id_specific_price_rule` = ' . $id) ?: [];
            $out[] = [
                'id_price_rule' => $id,
                'name' => (string) $row['name'],
                'reduction' => $row['reduction_type'] === 'percentage' ? round((float) $row['reduction'], 2) . ' %' : (float) $row['reduction'] . ($row['reduction_tax'] ? ' tax incl.' : ' tax excl.'),
                'from' => (string) $row['from'],
                'to' => (string) $row['to'],
                'id_group' => (int) $row['id_group'],
                'id_country' => (int) $row['id_country'],
                'id_currency' => (int) $row['id_currency'],
                'from_quantity' => (int) $row['from_quantity'],
                'conditions' => array_map(static function ($r) {
                    return ['group' => (int) $r['g'], 'type' => (string) $r['type'], 'value' => (string) $r['value']];
                }, $conditions),
            ];
        }

        return PiskeeConnectorHelper::json(['price_rules' => $out]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function createPriceRule(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $idShop = $c['id_shop'];
        $name = trim(strip_tags((string) ($args['name'] ?? '')));
        $type = (string) ($args['type'] ?? '');
        $value = (float) ($args['value'] ?? 0);
        if ($name === '') {
            return PiskeeConnectorHelper::error('name is required (shown in the back office).');
        }
        if (!in_array($type, ['percentage', 'amount'], true) || $value <= 0 || ($type === 'percentage' && $value >= 100)) {
            return PiskeeConnectorHelper::error('type must be percentage (below 100) or amount, with a value above zero.');
        }
        foreach (['date_from', 'date_to'] as $key) {
            if (!empty($args[$key]) && !PiskeeConnectorHelper::isDate((string) $args[$key])) {
                return PiskeeConnectorHelper::error($key . ' must be written YYYY-MM-DD.');
            }
        }
        $conditions = [];
        foreach ((array) ($args['category_ids'] ?? []) as $id) {
            if (!PiskeeConnectorHelper::inShop(new Category((int) $id, null, $idShop), $idShop)) {
                return PiskeeConnectorHelper::error('Category ' . (int) $id . ' not found.');
            }
            $conditions[] = ['type' => 'category', 'value' => (int) $id];
        }
        foreach ((array) ($args['manufacturer_ids'] ?? []) as $id) {
            if (!Validate::isLoadedObject(new Manufacturer((int) $id))) {
                return PiskeeConnectorHelper::error('Brand ' . (int) $id . ' not found.');
            }
            $conditions[] = ['type' => 'manufacturer', 'value' => (int) $id];
        }
        foreach (['id_group' => 'Group', 'id_country' => 'Country', 'id_currency' => 'Currency'] as $key => $class) {
            if (!empty($args[$key]) && !Validate::isLoadedObject(new $class((int) $args[$key]))) {
                return PiskeeConnectorHelper::error($key . ' ' . (int) $args[$key] . ' not found.');
            }
        }

        $rule = new SpecificPriceRule();
        $rule->name = Tools::substr($name, 0, 255);
        $rule->id_shop = $idShop;
        $rule->id_currency = (int) ($args['id_currency'] ?? 0);
        $rule->id_country = (int) ($args['id_country'] ?? 0);
        $rule->id_group = (int) ($args['id_group'] ?? 0);
        $rule->from_quantity = max(1, (int) ($args['from_quantity'] ?? 1));
        $rule->price = -1;
        $rule->reduction = $value;
        $rule->reduction_type = $type;
        $rule->reduction_tax = !isset($args['tax_included']) || $args['tax_included'] ? 1 : 0;
        $rule->from = !empty($args['date_from']) ? $args['date_from'] . ' 00:00:00' : '0000-00-00 00:00:00';
        $rule->to = !empty($args['date_to']) ? $args['date_to'] . ' 23:59:59' : '0000-00-00 00:00:00';
        if (!$rule->add()) {
            return PiskeeConnectorHelper::error('PrestaShop refused the price rule.');
        }
        if ($conditions !== []) {
            // One condition group per category or brand: the rule applies to products matching ANY of them.
            foreach ($conditions as $condition) {
                $rule->addConditions([$condition]);
            }
        }
        $rule->apply();

        return PiskeeConnectorHelper::json([
            'id_price_rule' => (int) $rule->id,
            'created' => true,
            'applies_to' => $conditions === [] ? 'the whole catalogue' : $conditions,
            'note' => 'A catalogue price rule shows as a promotion on every matching product (Catalog > Discounts > Catalog price rules). It does not stack with a product\'s own promotion: PrestaShop uses the best match.',
        ]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function deletePriceRule(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $id = (int) ($args['id_price_rule'] ?? 0);
        $rule = new SpecificPriceRule($id);
        if (!Validate::isLoadedObject($rule) || !in_array((int) $rule->id_shop, [0, (int) $c['id_shop']], true)) {
            return PiskeeConnectorHelper::error('Price rule ' . $id . ' not found. Call list_price_rules.');
        }
        $name = (string) $rule->name;
        if (!$rule->delete()) {
            return PiskeeConnectorHelper::error('PrestaShop refused to delete the price rule.');
        }

        return PiskeeConnectorHelper::json(['id_price_rule' => $id, 'name' => $name, 'deleted' => true, 'note' => 'The products got their normal price back (their own promotions are kept).']);
    }
}
