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
 * Day-to-day running of the shop: customers, sales figures, low stock,
 * customer service messages and vouchers.
 */
class PiskeeConnectorOperations
{
    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function searchCustomers(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $query = trim((string) ($args['query'] ?? ''));
        if (Tools::strlen($query) < 2) {
            return PiskeeConnectorHelper::error('query needs at least 2 characters (a name, an email or a customer id).');
        }
        $limit = PiskeeConnectorHelper::limit($args['limit'] ?? null, 20);
        $like = pSQL(str_replace(['%', '_'], ['\\%', '\\_'], $query));
        $p = _DB_PREFIX_;

        $condition = "(c.`email` LIKE '%" . $like . "%' OR c.`lastname` LIKE '%" . $like . "%' OR c.`firstname` LIKE '%" . $like . "%' OR c.`company` LIKE '%" . $like . "%'"
            . (ctype_digit($query) ? ' OR c.`id_customer` = ' . (int) $query : '') . ')';

        $rows = Db::getInstance()->executeS('SELECT c.`id_customer`, c.`firstname`, c.`lastname`, c.`email`, c.`company`, c.`active`, c.`newsletter`, c.`date_add`,
                (SELECT COUNT(*) FROM `' . $p . 'orders` o WHERE o.`id_customer` = c.`id_customer` AND o.`valid` = 1) AS orders,
                (SELECT COALESCE(SUM(o.`total_paid_tax_incl` / o.`conversion_rate`), 0) FROM `' . $p . 'orders` o WHERE o.`id_customer` = c.`id_customer` AND o.`valid` = 1) AS spent
            FROM `' . $p . 'customer` c
            WHERE c.`deleted` = 0 AND c.`id_shop` = ' . $c['id_shop'] . ' AND ' . $condition . '
            ORDER BY c.`id_customer` DESC LIMIT ' . $limit) ?: [];

        return PiskeeConnectorHelper::json(['customers' => array_map(static function (array $row) {
            return [
                'id_customer' => (int) $row['id_customer'],
                'name' => trim($row['firstname'] . ' ' . $row['lastname']),
                'email' => (string) $row['email'],
                'company' => (string) $row['company'],
                'active' => (bool) $row['active'],
                'newsletter' => (bool) $row['newsletter'],
                'registered' => (string) $row['date_add'],
                'valid_orders' => (int) $row['orders'],
                'total_spent_tax_incl' => PiskeeConnectorHelper::money((float) $row['spent']),
            ];
        }, $rows)]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function getCustomer(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $id = (int) ($args['id_customer'] ?? 0);
        $customer = new Customer($id);
        if (!Validate::isLoadedObject($customer) || (int) $customer->id_shop !== $c['id_shop'] || $customer->deleted) {
            return PiskeeConnectorHelper::error('Customer ' . $id . ' not found in this shop.');
        }

        $addresses = [];
        foreach ($customer->getAddresses($c['id_lang']) as $address) {
            $addresses[] = [
                'alias' => (string) $address['alias'],
                'name' => trim($address['firstname'] . ' ' . $address['lastname']),
                'company' => (string) $address['company'],
                'address' => trim($address['address1'] . ' ' . $address['address2']),
                'postcode' => (string) $address['postcode'],
                'city' => (string) $address['city'],
                'country' => (string) $address['country'],
                'phone' => (string) ($address['phone'] ?: $address['phone_mobile']),
            ];
        }

        $groups = [];
        foreach ($customer->getGroups() as $idGroup) {
            $groups[] = (string) (new Group((int) $idGroup, $c['id_lang']))->name;
        }

        $orders = Db::getInstance()->executeS('SELECT o.`id_order`, o.`reference`, o.`date_add`, o.`total_paid_tax_incl`, o.`payment`, osl.`name` AS status
            FROM `' . _DB_PREFIX_ . 'orders` o
            LEFT JOIN `' . _DB_PREFIX_ . 'order_state_lang` osl ON osl.`id_order_state` = o.`current_state` AND osl.`id_lang` = ' . $c['id_lang'] . '
            WHERE o.`id_customer` = ' . $id . ' ORDER BY o.`date_add` DESC LIMIT 10') ?: [];

        return PiskeeConnectorHelper::json([
            'id_customer' => $id,
            'name' => trim($customer->firstname . ' ' . $customer->lastname),
            'email' => (string) $customer->email,
            'company' => (string) $customer->company,
            'birthday' => (string) $customer->birthday,
            'active' => (bool) $customer->active,
            'newsletter' => (bool) $customer->newsletter,
            'registered' => (string) $customer->date_add,
            'groups' => $groups,
            'addresses' => $addresses,
            'last_orders' => array_map(static function (array $row) {
                return [
                    'id_order' => (int) $row['id_order'], 'reference' => (string) $row['reference'], 'date' => (string) $row['date_add'],
                    'total_tax_incl' => PiskeeConnectorHelper::money((float) $row['total_paid_tax_incl']), 'payment' => (string) $row['payment'], 'status' => (string) $row['status'],
                ];
            }, $orders),
        ]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function salesSummary(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $from = (string) ($args['date_from'] ?? date('Y-m-d'));
        $to = (string) ($args['date_to'] ?? $from);
        if (!PiskeeConnectorHelper::isDate($from) || !PiskeeConnectorHelper::isDate($to) || $from > $to) {
            return PiskeeConnectorHelper::error('date_from and date_to must be YYYY-MM-DD, with date_from on or before date_to.');
        }

        $db = Db::getInstance();
        $p = _DB_PREFIX_;
        $range = "o.`date_add` BETWEEN '" . pSQL($from) . " 00:00:00' AND '" . pSQL($to) . " 23:59:59' AND o.`id_shop` = " . $c['id_shop'];

        $totals = $db->getRow('SELECT COUNT(*) AS orders,
                COALESCE(SUM(o.`total_paid_tax_incl` / o.`conversion_rate`), 0) AS revenue_incl,
                COALESCE(SUM(o.`total_paid_tax_excl` / o.`conversion_rate`), 0) AS revenue_excl,
                COUNT(DISTINCT o.`id_customer`) AS buyers
            FROM `' . $p . 'orders` o WHERE o.`valid` = 1 AND ' . $range) ?: [];

        $newCustomers = (int) $db->getValue('SELECT COUNT(*) FROM `' . $p . "customer` WHERE `deleted` = 0 AND `id_shop` = " . $c['id_shop']
            . " AND `date_add` BETWEEN '" . pSQL($from) . " 00:00:00' AND '" . pSQL($to) . " 23:59:59'");

        $byStatus = $db->executeS('SELECT osl.`name` AS status, COUNT(*) AS n
            FROM `' . $p . 'orders` o
            LEFT JOIN `' . $p . 'order_state_lang` osl ON osl.`id_order_state` = o.`current_state` AND osl.`id_lang` = ' . $c['id_lang'] . '
            WHERE ' . $range . ' GROUP BY o.`current_state` ORDER BY n DESC') ?: [];

        $top = $db->executeS('SELECT od.`product_id`, od.`product_name`, SUM(od.`product_quantity`) AS qty,
                SUM(od.`total_price_tax_excl` / o.`conversion_rate`) AS revenue
            FROM `' . $p . 'order_detail` od
            INNER JOIN `' . $p . 'orders` o ON o.`id_order` = od.`id_order`
            WHERE o.`valid` = 1 AND ' . $range . '
            GROUP BY od.`product_id`, od.`product_name` ORDER BY revenue DESC LIMIT 10') ?: [];

        $orders = (int) ($totals['orders'] ?? 0);
        $currency = new Currency((int) Configuration::get('PS_CURRENCY_DEFAULT'));

        return PiskeeConnectorHelper::json([
            'period' => ['from' => $from, 'to' => $to],
            'currency' => (string) $currency->iso_code,
            'valid_orders' => $orders,
            'revenue_tax_incl' => PiskeeConnectorHelper::money((float) ($totals['revenue_incl'] ?? 0)),
            'revenue_tax_excl' => PiskeeConnectorHelper::money((float) ($totals['revenue_excl'] ?? 0)),
            'average_order_tax_incl' => $orders > 0 ? PiskeeConnectorHelper::money((float) $totals['revenue_incl'] / $orders) : 0,
            'buyers' => (int) ($totals['buyers'] ?? 0),
            'new_customer_accounts' => $newCustomers,
            'orders_by_status' => array_map(static function (array $row) {
                return ['status' => (string) $row['status'], 'orders' => (int) $row['n']];
            }, $byStatus),
            'top_products' => array_map(static function (array $row) {
                return ['id_product' => (int) $row['product_id'], 'name' => (string) $row['product_name'], 'quantity' => (int) $row['qty'], 'revenue_tax_excl' => PiskeeConnectorHelper::money((float) $row['revenue'])];
            }, $top),
        ]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function lowStock(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $threshold = max(0, (int) ($args['threshold'] ?? 3));
        $limit = PiskeeConnectorHelper::limit($args['limit'] ?? null, 50);
        $p = _DB_PREFIX_;

        $rows = Db::getInstance()->executeS('SELECT sa.`id_product`, sa.`id_product_attribute`, sa.`quantity`, pl.`name`, p.`reference`,
                pa.`reference` AS combination_reference
            FROM `' . $p . 'stock_available` sa
            INNER JOIN `' . $p . 'product` p ON p.`id_product` = sa.`id_product`
            INNER JOIN `' . $p . 'product_shop` ps ON ps.`id_product` = p.`id_product` AND ps.`id_shop` = ' . $c['id_shop'] . ' AND ps.`active` = 1
            LEFT JOIN `' . $p . 'product_lang` pl ON pl.`id_product` = p.`id_product` AND pl.`id_lang` = ' . $c['id_lang'] . ' AND pl.`id_shop` = ' . $c['id_shop'] . '
            LEFT JOIN `' . $p . 'product_attribute` pa ON pa.`id_product_attribute` = sa.`id_product_attribute`
            WHERE sa.`quantity` <= ' . $threshold . StockAvailable::addSqlShopRestriction(null, $c['id_shop'], 'sa') . '
              AND (sa.`id_product_attribute` > 0 OR NOT EXISTS (SELECT 1 FROM `' . $p . 'product_attribute` x WHERE x.`id_product` = sa.`id_product`))
            ORDER BY sa.`quantity` ASC, sa.`id_product` ASC LIMIT ' . $limit) ?: [];

        $out = [];
        foreach ($rows as $row) {
            $ipa = (int) $row['id_product_attribute'];
            $label = '';
            if ($ipa > 0) {
                $names = Db::getInstance()->executeS('SELECT agl.`name` AS g, al.`name` AS a FROM `' . $p . 'product_attribute_combination` pac
                    INNER JOIN `' . $p . 'attribute` at ON at.`id_attribute` = pac.`id_attribute`
                    LEFT JOIN `' . $p . 'attribute_lang` al ON al.`id_attribute` = at.`id_attribute` AND al.`id_lang` = ' . $c['id_lang'] . '
                    LEFT JOIN `' . $p . 'attribute_group_lang` agl ON agl.`id_attribute_group` = at.`id_attribute_group` AND agl.`id_lang` = ' . $c['id_lang'] . '
                    WHERE pac.`id_product_attribute` = ' . $ipa) ?: [];
                $label = implode(', ', array_map(static function (array $n) {
                    return $n['g'] . ': ' . $n['a'];
                }, $names));
            }
            $out[] = [
                'id_product' => (int) $row['id_product'],
                'id_product_attribute' => $ipa,
                'name' => (string) $row['name'] . ($label !== '' ? ' (' . $label . ')' : ''),
                'reference' => (string) ($row['combination_reference'] ?: $row['reference']),
                'quantity' => (int) $row['quantity'],
            ];
        }

        return PiskeeConnectorHelper::json(['threshold' => $threshold, 'items' => $out, 'truncated' => count($out) === $limit]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function listMessages(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $status = (string) ($args['status'] ?? 'open');
        if (!in_array($status, ['open', 'closed', 'pending1', 'pending2', 'all'], true)) {
            return PiskeeConnectorHelper::error('status must be open, pending1, pending2, closed or all.');
        }
        $limit = PiskeeConnectorHelper::limit($args['limit'] ?? null, 20);
        $p = _DB_PREFIX_;

        $rows = Db::getInstance()->executeS('SELECT ct.`id_customer_thread`, ct.`status`, ct.`email`, ct.`id_order`, ct.`date_upd`,
                c.`firstname`, c.`lastname`, cl.`name` AS contact,
                (SELECT cm.`message` FROM `' . $p . 'customer_message` cm WHERE cm.`id_customer_thread` = ct.`id_customer_thread` ORDER BY cm.`date_add` DESC LIMIT 1) AS last_message,
                (SELECT cm.`id_employee` FROM `' . $p . 'customer_message` cm WHERE cm.`id_customer_thread` = ct.`id_customer_thread` ORDER BY cm.`date_add` DESC LIMIT 1) AS last_by_employee
            FROM `' . $p . 'customer_thread` ct
            LEFT JOIN `' . $p . 'customer` c ON c.`id_customer` = ct.`id_customer`
            LEFT JOIN `' . $p . 'contact_lang` cl ON cl.`id_contact` = ct.`id_contact` AND cl.`id_lang` = ' . $c['id_lang'] . '
            WHERE ct.`id_shop` = ' . $c['id_shop'] . ($status !== 'all' ? " AND ct.`status` = '" . pSQL($status) . "'" : '') . '
            ORDER BY ct.`date_upd` DESC LIMIT ' . $limit) ?: [];

        return PiskeeConnectorHelper::json(['threads' => array_map(static function (array $row) {
            return [
                'id_customer_thread' => (int) $row['id_customer_thread'],
                'status' => (string) $row['status'],
                'from' => trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? '')) ?: (string) $row['email'],
                'email' => (string) $row['email'],
                'contact' => (string) $row['contact'],
                'id_order' => (int) $row['id_order'],
                'updated' => (string) $row['date_upd'],
                'awaiting_reply' => (int) $row['last_by_employee'] === 0,
                'last_message' => PiskeeConnectorHelper::excerpt((string) $row['last_message'], 240),
            ];
        }, $rows)]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function getMessageThread(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $id = (int) ($args['id_customer_thread'] ?? 0);
        $thread = new CustomerThread($id);
        if (!Validate::isLoadedObject($thread) || (int) $thread->id_shop !== $c['id_shop']) {
            return PiskeeConnectorHelper::error('Conversation ' . $id . ' not found in this shop.');
        }

        $messages = Db::getInstance()->executeS('SELECT cm.`message`, cm.`id_employee`, cm.`private`, cm.`date_add`, e.`firstname`, e.`lastname`
            FROM `' . _DB_PREFIX_ . 'customer_message` cm
            LEFT JOIN `' . _DB_PREFIX_ . 'employee` e ON e.`id_employee` = cm.`id_employee`
            WHERE cm.`id_customer_thread` = ' . $id . ' ORDER BY cm.`date_add` ASC') ?: [];

        return PiskeeConnectorHelper::json([
            'id_customer_thread' => $id,
            'status' => (string) $thread->status,
            'email' => (string) $thread->email,
            'id_order' => (int) $thread->id_order,
            'language' => PiskeeConnectorHelper::iso((int) $thread->id_lang),
            'messages' => array_map(static function (array $row) {
                return [
                    'from' => (int) $row['id_employee'] > 0 ? 'shop (' . trim($row['firstname'] . ' ' . $row['lastname']) . ')' : 'customer',
                    'private_note' => (bool) $row['private'],
                    'date' => (string) $row['date_add'],
                    'text' => trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", (string) $row['message']))),
                ];
            }, $messages),
        ]);
    }

    /**
     * Sends the reply by email to the customer, the way the back office does.
     *
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function replyToMessage(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $id = (int) ($args['id_customer_thread'] ?? 0);
        $thread = new CustomerThread($id);
        if (!Validate::isLoadedObject($thread) || (int) $thread->id_shop !== $c['id_shop']) {
            return PiskeeConnectorHelper::error('Conversation ' . $id . ' not found in this shop.');
        }
        $text = trim((string) ($args['message'] ?? ''));
        if (Tools::strlen($text) < 2 || Tools::strlen($text) > 10000) {
            return PiskeeConnectorHelper::error('message must be between 2 and 10000 characters.');
        }

        $message = new CustomerMessage();
        $message->id_customer_thread = $id;
        $message->id_employee = $c['id_employee'];
        // Stored like the back office does: order and contact pages print it as is.
        $message->message = Tools::htmlentitiesUTF8($text);
        $message->private = 0;
        $message->read = 1;
        if (!$message->add()) {
            return PiskeeConnectorHelper::error('PrestaShop refused to save the reply. Nothing was sent.');
        }

        $customer = new Customer((int) $thread->id_customer);
        $link = Context::getContext()->link;
        $sent = Mail::Send(
            (int) $thread->id_lang,
            'reply_msg',
            Mail::l('An answer to your message is available', (int) $thread->id_lang) . ' #ct' . (int) $thread->id . ' #tc' . $thread->token,
            [
                '{reply}' => Tools::nl2br(Tools::htmlentitiesUTF8($text)),
                '{link}' => Tools::url($link->getPageLink('contact', true, null, null, false, (int) $thread->id_shop), 'id_customer_thread=' . (int) $thread->id . '&token=' . $thread->token),
                '{firstname}' => (string) $customer->firstname,
                '{lastname}' => (string) $customer->lastname,
            ],
            (string) $thread->email,
            null,
            null,
            null,
            null,
            null,
            _PS_MAIL_DIR_,
            false,
            (int) $thread->id_shop
        );
        $sent = $sent !== false && $sent !== 0;

        $close = (bool) ($args['close'] ?? true);
        $thread->status = $close ? 'closed' : 'pending1';
        $thread->update();

        return PiskeeConnectorHelper::json([
            'id_customer_thread' => $id,
            'saved' => true,
            'email_sent' => (bool) $sent,
            'status' => $thread->status,
            'message' => $sent ? 'Reply saved and emailed to the customer.' : 'Reply saved in the back office, but the email could not be sent: check the shop email settings.',
        ]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function createVoucher(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $name = trim((string) ($args['name'] ?? ''));
        if ($name === '') {
            return PiskeeConnectorHelper::error('name is required (it is shown to the customer in the cart).');
        }

        $percent = (float) ($args['percent'] ?? 0);
        $amount = (float) ($args['amount'] ?? 0);
        $freeShipping = (bool) ($args['free_shipping'] ?? false);
        if ($percent <= 0 && $amount <= 0 && !$freeShipping) {
            return PiskeeConnectorHelper::error('Give a percent, an amount or free_shipping.');
        }
        if ($percent > 0 && $amount > 0) {
            return PiskeeConnectorHelper::error('Give a percent or an amount, not both.');
        }
        if ($percent >= 100) {
            return PiskeeConnectorHelper::error('percent must stay below 100.');
        }

        $from = (string) ($args['date_from'] ?? date('Y-m-d'));
        $to = (string) ($args['date_to'] ?? date('Y-m-d', strtotime('+1 month')));
        if (!PiskeeConnectorHelper::isDate($from) || !PiskeeConnectorHelper::isDate($to) || $from > $to) {
            return PiskeeConnectorHelper::error('date_from and date_to must be YYYY-MM-DD, with date_from on or before date_to.');
        }

        $code = Tools::strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($args['code'] ?? '')) ?? '');
        if ($code === '') {
            $code = Tools::strtoupper(Tools::passwdGen(8));
        }
        if (CartRule::cartRuleExists($code)) {
            return PiskeeConnectorHelper::error('The code ' . $code . ' is already used by another voucher.');
        }

        $idCustomer = (int) ($args['id_customer'] ?? 0);
        if ($idCustomer > 0 && !PiskeeConnectorHelper::inShop(new Customer($idCustomer), $c['id_shop'])) {
            return PiskeeConnectorHelper::error('Customer ' . $idCustomer . ' not found.');
        }

        $rule = new CartRule();
        foreach (PiskeeConnectorHelper::languages($c['id_shop']) as $idLang) {
            $rule->name[$idLang] = Tools::substr($name, 0, 254);
        }
        $rule->code = $code;
        $rule->description = Tools::substr((string) ($args['description'] ?? 'Created by Piskee'), 0, 65000);
        $rule->id_customer = $idCustomer;
        $rule->date_from = $from . ' 00:00:00';
        $rule->date_to = $to . ' 23:59:59';
        $rule->quantity = max(1, (int) ($args['quantity'] ?? 1));
        $rule->quantity_per_user = max(1, (int) ($args['quantity_per_user'] ?? 1));
        $rule->reduction_percent = $percent;
        $rule->reduction_amount = $amount;
        $rule->reduction_tax = 1;
        $rule->reduction_currency = (int) Configuration::get('PS_CURRENCY_DEFAULT');
        $rule->free_shipping = $freeShipping;
        $rule->minimum_amount = max(0.0, (float) ($args['minimum_amount'] ?? 0));
        $rule->minimum_amount_tax = 1;
        $rule->minimum_amount_currency = (int) Configuration::get('PS_CURRENCY_DEFAULT');
        $rule->highlight = 0;
        $rule->partial_use = 0;
        $rule->active = 1;
        if (Shop::isFeatureActive()) {
            $rule->shop_restriction = 1;
        }

        $error = PiskeeConnectorHelper::validate($rule);
        if ($error !== '' || !$rule->add()) {
            return PiskeeConnectorHelper::error('Not created: ' . ($error !== '' ? $error : 'PrestaShop refused the voucher.'));
        }
        if (Shop::isFeatureActive()) {
            Db::getInstance()->insert('cart_rule_shop', ['id_cart_rule' => (int) $rule->id, 'id_shop' => (int) $c['id_shop']]);
        }

        return PiskeeConnectorHelper::json([
            'id_cart_rule' => (int) $rule->id,
            'code' => $code,
            'valid' => ['from' => $from, 'to' => $to],
            'created' => true,
        ]);
    }
}
