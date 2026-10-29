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
 * Cross-selling: the related products (accessories) of each product, what
 * customers actually buy together, and the native "customers who bought this
 * also bought" block, on the product page and in the cart.
 *
 * Other cross-selling or cart upsell modules are detected and reported, never
 * changed: each one stores its rules its own way.
 */
class PiskeeConnectorCrossSelling
{
    private const NATIVE_MODULE = 'ps_crossselling';
    private const HOOK_PRODUCT = 'displayFooterProduct';
    private const HOOK_CART = 'displayShoppingCartFooter';
    private const BULK_MAX = 50;

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function getCrossSelling(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $idProduct = (int) ($args['id_product'] ?? 0);
        $product = new Product($idProduct, false, $c['id_lang'], $c['id_shop']);
        if (!PiskeeConnectorHelper::inShop($product, $c['id_shop'])) {
            return PiskeeConnectorHelper::error('Product ' . $idProduct . ' not found in this shop.');
        }
        $days = $this->days($args);
        $related = $this->relatedIds($idProduct);

        return PiskeeConnectorHelper::json([
            'id_product' => $idProduct,
            'name' => (string) $product->name,
            'related_products' => $this->describe($related, $c),
            'bought_together' => $this->boughtTogether($idProduct, $days, 10, $related, $c),
            'period_days' => $days,
            'modules' => self::modules(),
            'note' => 'related_products are shown on the product page by the theme. bought_together comes from valid orders and can be turned into related products with set_related_products (mode add).',
        ]);
    }

    /**
     * Suggestions from real orders, for one product or for the best sellers.
     *
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function suggestRelatedProducts(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $days = $this->days($args);
        $perProduct = max(1, min(10, (int) ($args['per_product'] ?? 4)));
        $minOrders = max(1, (int) ($args['min_orders'] ?? 2));
        $idProduct = (int) ($args['id_product'] ?? 0);

        if ($idProduct > 0) {
            if (!PiskeeConnectorHelper::inShop(new Product($idProduct, false, null, $c['id_shop']), $c['id_shop'])) {
                return PiskeeConnectorHelper::error('Product ' . $idProduct . ' not found in this shop.');
            }
            $ids = [$idProduct];
        } else {
            $limit = max(1, min(30, (int) ($args['limit'] ?? 15)));
            $ids = array_map('intval', array_column(Db::getInstance()->executeS('SELECT od.`product_id`, SUM(od.`product_quantity`) AS qty
                FROM `' . _DB_PREFIX_ . 'order_detail` od
                INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.`id_order` = od.`id_order` AND o.`valid` = 1 AND o.`id_shop` = ' . $c['id_shop'] . '
                    AND o.`date_add` >= DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)
                GROUP BY od.`product_id` ORDER BY qty DESC LIMIT ' . $limit) ?: [], 'product_id'));
        }

        $out = [];
        foreach ($ids as $id) {
            $related = $this->relatedIds($id);
            $pairs = array_values(array_filter($this->boughtTogether($id, $days, $perProduct * 3, $related, $c), static function (array $row) use ($minOrders) {
                return $row['orders_together'] >= $minOrders && $row['active'] && !$row['already_related'] && $row['in_stock'];
            }));
            $out[] = [
                'id_product' => $id,
                'name' => (string) Product::getProductName($id, null, $c['id_lang']),
                'current_related_ids' => $related,
                'suggested' => array_slice($pairs, 0, $perProduct),
            ];
        }

        return PiskeeConnectorHelper::json([
            'period_days' => $days,
            'min_orders_together' => $minOrders,
            'products' => $out,
            'note' => $out === [] ? 'No valid order in this period: there is nothing to learn from yet. Related products can still be chosen by hand (same collection, complementary use).' : 'Show these suggestions to the merchant, then apply the approved ones with set_related_products (mode add) or bulk_set_related_products.',
        ]);
    }

    /**
     * Replaces, extends or trims the related products of one product.
     *
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function setRelatedProducts(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);

        return $this->applyRelated((int) ($args['id_product'] ?? 0), $args['related_ids'] ?? null, (string) ($args['mode'] ?? 'replace'), $c);
    }

    /**
     * Same as set_related_products for up to 50 products. Each product
     * actually changed counts as one change of the free plan.
     *
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function bulkSetRelatedProducts(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $items = is_array($args['products'] ?? null) ? array_values($args['products']) : [];
        if ($items === [] || count($items) > self::BULK_MAX) {
            return PiskeeConnectorHelper::error('products must list between 1 and ' . self::BULK_MAX . ' entries.');
        }

        $updated = [];
        $unchanged = [];
        $failed = [];
        $notDone = [];
        foreach ($items as $item) {
            $id = (int) ($item['id_product'] ?? 0);
            if (PiskeeConnectorLicense::gate($c['id_shop']) !== '') {
                $notDone[] = $id;
                continue;
            }
            $result = $this->applyRelated($id, $item['related_ids'] ?? null, (string) ($item['mode'] ?? 'add'), $c);
            if (!empty($result['error'])) {
                $failed[] = ['id_product' => $id, 'error' => (string) $result['text']];
            } elseif (empty($result['json']['updated'])) {
                $unchanged[] = $id;
            } else {
                PiskeeConnectorLicense::count($c['id_shop']);
                $updated[] = ['id_product' => $id, 'related_ids' => $result['json']['related_ids']];
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

    /**
     * Settings of the native "customers who bought this also bought" module:
     * how many products, prices shown, and where it appears (product page,
     * cart).
     *
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function configureCrossSellingBlock(array $args, array $context): array
    {
        PiskeeConnectorHelper::boot($context);
        $module = Module::getInstanceByName(self::NATIVE_MODULE);
        if (!$module || !Module::isInstalled(self::NATIVE_MODULE)) {
            return PiskeeConnectorHelper::error('The PrestaShop module "Cross-selling" (ps_crossselling) is not installed. The merchant can install it for free from Modules > Module Catalog, then ask again. Other cross-selling modules are listed by get_cross_selling but cannot be configured from here.');
        }

        $changes = [];
        if (isset($args['products_count'])) {
            $count = (int) $args['products_count'];
            if ($count < 1 || $count > 20) {
                return PiskeeConnectorHelper::error('products_count must be between 1 and 20.');
            }
            if ((int) Configuration::get('CROSSSELLING_NBR') !== $count) {
                $changes['products_count'] = ['from' => (int) Configuration::get('CROSSSELLING_NBR'), 'to' => $count];
                Configuration::updateValue('CROSSSELLING_NBR', $count);
            }
        }
        if (isset($args['show_price'])) {
            $show = (bool) $args['show_price'];
            if ((bool) Configuration::get('CROSSSELLING_DISPLAY_PRICE') !== $show) {
                $changes['show_price'] = ['from' => !$show, 'to' => $show];
                Configuration::updateValue('CROSSSELLING_DISPLAY_PRICE', $show ? 1 : 0);
            }
        }
        if (!$module->active && (!empty($args['show_in_cart']) || !empty($args['show_on_product_page']))) {
            $module->enable();
            $changes['module'] = ['from' => 'disabled', 'to' => 'enabled'];
        }
        foreach (['show_on_product_page' => self::HOOK_PRODUCT, 'show_in_cart' => self::HOOK_CART] as $arg => $hook) {
            if (!isset($args[$arg])) {
                continue;
            }
            $wanted = (bool) $args[$arg];
            $now = $module->isRegisteredInHook($hook);
            if ($wanted && !$now) {
                $module->registerHook($hook);
                $changes[$arg] = ['from' => false, 'to' => true];
            } elseif (!$wanted && $now) {
                $module->unregisterHook($hook);
                $changes[$arg] = ['from' => true, 'to' => false];
            }
        }

        if ($changes === []) {
            return PiskeeConnectorHelper::json(['updated' => false, 'message' => 'Nothing to change.', 'settings' => self::nativeSettings()]);
        }
        Tools::clearSmartyCache();

        return PiskeeConnectorHelper::json([
            'updated' => true,
            'changes' => $changes,
            'settings' => self::nativeSettings(),
            'note' => 'The block suggests products bought by customers who bought the same items. Some themes do not show the cart hook: check the cart page.',
        ]);
    }

    /**
     * Cross-selling and cart upsell modules installed on the shop.
     *
     * @return array<string, mixed>
     */
    public static function modules(): array
    {
        $rows = Db::getInstance()->executeS('SELECT m.`id_module`, m.`name`, m.`active` FROM `' . _DB_PREFIX_ . 'module` m
            WHERE m.`name` REGEXP \'cross|upsell|up_sell|upsale|bundle|together|accessor|recommend|related|pack\'') ?: [];
        $others = [];
        foreach ($rows as $row) {
            if ((string) $row['name'] === self::NATIVE_MODULE) {
                continue;
            }
            $instance = Module::getInstanceByName((string) $row['name']);
            $others[] = [
                'name' => (string) $row['name'],
                'display_name' => $instance ? (string) $instance->displayName : (string) $row['name'],
                'active' => (bool) $row['active'],
                'shown_in' => self::placements((int) $row['id_module']),
                'managed_by_piskee' => false,
            ];
        }

        return [
            'native_block' => self::nativeSettings(),
            'other_modules' => $others,
            'note' => $others === [] ? 'No third-party cross-selling or cart upsell module found.' : 'Third-party modules keep their own rules: Piskee lists them but does not change them. Their settings are in Modules > Module Manager.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function nativeSettings(): array
    {
        if (!Module::isInstalled(self::NATIVE_MODULE)) {
            return ['module' => self::NATIVE_MODULE, 'installed' => false];
        }
        $module = Module::getInstanceByName(self::NATIVE_MODULE);

        return [
            'module' => self::NATIVE_MODULE,
            'installed' => true,
            'active' => (bool) ($module && $module->active),
            'products_count' => (int) Configuration::get('CROSSSELLING_NBR'),
            'show_price' => (bool) Configuration::get('CROSSSELLING_DISPLAY_PRICE'),
            'show_on_product_page' => (bool) ($module && $module->isRegisteredInHook(self::HOOK_PRODUCT)),
            'show_in_cart' => (bool) ($module && $module->isRegisteredInHook(self::HOOK_CART)),
        ];
    }

    /**
     * Product page and cart hooks a module is attached to.
     *
     * @return array<int, string>
     */
    private static function placements(int $idModule): array
    {
        $rows = Db::getInstance()->executeS('SELECT DISTINCT h.`name` FROM `' . _DB_PREFIX_ . 'hook_module` hm
            INNER JOIN `' . _DB_PREFIX_ . 'hook` h ON h.`id_hook` = hm.`id_hook`
            WHERE hm.`id_module` = ' . $idModule . ' AND (h.`name` LIKE \'displayShoppingCart%\' OR h.`name` LIKE \'displayCart%\'
                OR h.`name` LIKE \'displayFooterProduct%\' OR h.`name` LIKE \'displayProduct%\' OR h.`name` LIKE \'displayExpressCheckout%\'
                OR h.`name` LIKE \'displayCheckout%\' OR h.`name` LIKE \'displayOrderConfirmation%\')') ?: [];

        return array_map(static function (array $row) {
            return (string) $row['name'];
        }, $rows);
    }

    /**
     * @param mixed $requested
     * @param array<string, int> $c
     *
     * @return array<string, mixed>
     */
    private function applyRelated(int $idProduct, $requested, string $mode, array $c): array
    {
        if (!in_array($mode, ['replace', 'add', 'remove'], true)) {
            return PiskeeConnectorHelper::error('mode must be replace, add or remove.');
        }
        $product = new Product($idProduct, false, null, $c['id_shop']);
        if (!PiskeeConnectorHelper::inShop($product, $c['id_shop'])) {
            return PiskeeConnectorHelper::error('Product ' . $idProduct . ' not found in this shop.');
        }
        if (!is_array($requested)) {
            return PiskeeConnectorHelper::error('related_ids must be a list of product ids.');
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $requested), static function (int $id) use ($idProduct) {
            return $id > 0 && $id !== $idProduct;
        })));
        if ($mode !== 'remove') {
            foreach ($ids as $id) {
                if (!PiskeeConnectorHelper::inShop(new Product($id, false, null, $c['id_shop']), $c['id_shop'])) {
                    return PiskeeConnectorHelper::error('Product ' . $id . ' not found. Nothing was changed.');
                }
            }
        }

        $before = $this->relatedIds($idProduct);
        if ($mode === 'add') {
            $after = array_values(array_unique(array_merge($before, $ids)));
        } elseif ($mode === 'remove') {
            $after = array_values(array_diff($before, $ids));
        } else {
            $after = $ids;
        }
        $sortedBefore = $before;
        $sortedAfter = $after;
        sort($sortedBefore);
        sort($sortedAfter);
        if ($sortedBefore === $sortedAfter) {
            return PiskeeConnectorHelper::json(['id_product' => $idProduct, 'related_ids' => $before, 'updated' => false, 'message' => 'Nothing to change.']);
        }

        $product->deleteAccessories();
        if ($after !== []) {
            $product->changeAccessories($after);
        }
        $now = $this->relatedIds($idProduct);
        $name = Product::getProductName($idProduct, null, (int) Configuration::get('PS_LANG_DEFAULT'));
        PiskeeConnectorHelper::recordChange('RelatedProducts', $idProduct, (string) $name, 'update', ['related' => $before], ['related' => $now]);

        return PiskeeConnectorHelper::json([
            'id_product' => $idProduct,
            'related_ids' => $now,
            'before' => $before,
            'updated' => true,
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function relatedIds(int $idProduct): array
    {
        return array_map('intval', array_column(Db::getInstance()->executeS('SELECT `id_product_2` FROM `' . _DB_PREFIX_ . 'accessory`
            WHERE `id_product_1` = ' . $idProduct, true, false) ?: [], 'id_product_2'));
    }

    /**
     * @param array<int, int> $ids
     * @param array<string, int> $c
     *
     * @return array<int, array<string, mixed>>
     */
    private function describe(array $ids, array $c): array
    {
        $out = [];
        foreach ($ids as $id) {
            $product = new Product($id, false, $c['id_lang'], $c['id_shop']);
            $out[] = [
                'id_product' => $id,
                'name' => (string) $product->name,
                'active' => (bool) $product->active,
                'quantity' => (int) StockAvailable::getQuantityAvailableByProduct($id, null, $c['id_shop']),
            ];
        }

        return $out;
    }

    /**
     * Products found in the same valid orders as this one.
     *
     * @param array<int, int> $related
     * @param array<string, int> $c
     *
     * @return array<int, array<string, mixed>>
     */
    private function boughtTogether(int $idProduct, int $days, int $limit, array $related, array $c): array
    {
        $p = _DB_PREFIX_;
        $rows = Db::getInstance()->executeS('SELECT od2.`product_id` AS id, COUNT(DISTINCT od2.`id_order`) AS n, pl.`name`, ps.`active`
            FROM `' . $p . 'order_detail` od1
            INNER JOIN `' . $p . 'orders` o ON o.`id_order` = od1.`id_order` AND o.`valid` = 1 AND o.`id_shop` = ' . $c['id_shop'] . '
                AND o.`date_add` >= DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)
            INNER JOIN `' . $p . 'order_detail` od2 ON od2.`id_order` = od1.`id_order` AND od2.`product_id` != od1.`product_id`
            INNER JOIN `' . $p . 'product_shop` ps ON ps.`id_product` = od2.`product_id` AND ps.`id_shop` = ' . $c['id_shop'] . '
            LEFT JOIN `' . $p . 'product_lang` pl ON pl.`id_product` = od2.`product_id` AND pl.`id_lang` = ' . $c['id_lang'] . ' AND pl.`id_shop` = ' . $c['id_shop'] . '
            WHERE od1.`product_id` = ' . $idProduct . '
            GROUP BY od2.`product_id`, pl.`name`, ps.`active`
            ORDER BY n DESC LIMIT ' . max(1, $limit)) ?: [];

        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $out[] = [
                'id_product' => $id,
                'name' => (string) $row['name'],
                'orders_together' => (int) $row['n'],
                'active' => (bool) $row['active'],
                'in_stock' => (int) StockAvailable::getQuantityAvailableByProduct($id, null, $c['id_shop']) > 0 || (bool) Product::isAvailableWhenOutOfStock(StockAvailable::outOfStock($id, $c['id_shop'])),
                'already_related' => in_array($id, $related, true),
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $args
     */
    private function days(array $args): int
    {
        return max(7, min(1095, (int) ($args['period_days'] ?? 365)));
    }
}
