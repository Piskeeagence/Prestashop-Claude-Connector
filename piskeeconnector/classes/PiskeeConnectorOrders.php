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
 * Orders: finding them, reading them with their tracking, status changes and
 * tracking numbers. The base of "where is my order?" answers.
 */
class PiskeeConnectorOrders
{
    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function searchOrders(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $limit = PiskeeConnectorHelper::limit($args['limit'] ?? null, 20);
        $p = _DB_PREFIX_;
        $where = ['o.`id_shop` = ' . $c['id_shop']];

        $query = trim((string) ($args['query'] ?? ''));
        if ($query !== '') {
            $like = pSQL(str_replace(['%', '_'], ['\\%', '\\_'], $query));
            $parts = ["o.`reference` LIKE '%" . $like . "%'", "c.`email` LIKE '%" . $like . "%'", "c.`lastname` LIKE '%" . $like . "%'", "oc.`tracking_number` = '" . pSQL($query) . "'"];
            if (ctype_digit($query)) {
                $parts[] = 'o.`id_order` = ' . (int) $query;
            }
            $where[] = '(' . implode(' OR ', $parts) . ')';
        }
        if (!empty($args['id_order_state'])) {
            $where[] = 'o.`current_state` = ' . (int) $args['id_order_state'];
        }
        if (!empty($args['id_customer'])) {
            $where[] = 'o.`id_customer` = ' . (int) $args['id_customer'];
        }
        foreach (['date_from' => '>=', 'date_to' => '<='] as $key => $op) {
            if (!empty($args[$key])) {
                if (!PiskeeConnectorHelper::isDate($args[$key])) {
                    return PiskeeConnectorHelper::error($key . ' must be written YYYY-MM-DD.');
                }
                $where[] = 'o.`date_add` ' . $op . " '" . pSQL($args[$key]) . ($op === '>=' ? " 00:00:00'" : " 23:59:59'");
            }
        }

        $rows = Db::getInstance()->executeS('SELECT o.`id_order`, o.`reference`, o.`date_add`, o.`total_paid_tax_incl`, o.`payment`, o.`current_state`,
                c.`firstname`, c.`lastname`, c.`email`, osl.`name` AS status, oc.`tracking_number`
            FROM `' . $p . 'orders` o
            LEFT JOIN `' . $p . 'customer` c ON c.`id_customer` = o.`id_customer`
            LEFT JOIN `' . $p . 'order_state_lang` osl ON osl.`id_order_state` = o.`current_state` AND osl.`id_lang` = ' . $c['id_lang'] . '
            LEFT JOIN `' . $p . 'order_carrier` oc ON oc.`id_order` = o.`id_order`
            WHERE ' . implode(' AND ', $where) . '
            GROUP BY o.`id_order`
            ORDER BY o.`date_add` DESC LIMIT ' . $limit) ?: [];

        return PiskeeConnectorHelper::json(['orders' => array_map(static function (array $row) {
            return [
                'id_order' => (int) $row['id_order'],
                'reference' => (string) $row['reference'],
                'date' => (string) $row['date_add'],
                'customer' => trim($row['firstname'] . ' ' . $row['lastname']),
                'email' => (string) $row['email'],
                'total_tax_incl' => PiskeeConnectorHelper::money((float) $row['total_paid_tax_incl']),
                'payment' => (string) $row['payment'],
                'status' => (string) $row['status'],
                'tracking_number' => (string) $row['tracking_number'],
            ];
        }, $rows)]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function getOrder(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $order = $this->load($args, $c['id_shop']);
        if ($order === null) {
            return PiskeeConnectorHelper::error('Order not found in this shop. Search it with search_orders.');
        }

        $customer = new Customer((int) $order->id_customer);
        $delivery = new Address((int) $order->id_address_delivery);

        $lines = [];
        foreach ($order->getProducts() as $line) {
            $lines[] = [
                'id_product' => (int) $line['product_id'],
                'id_product_attribute' => (int) $line['product_attribute_id'],
                'name' => (string) $line['product_name'],
                'reference' => (string) $line['product_reference'],
                'quantity' => (int) $line['product_quantity'],
                'quantity_refunded' => (int) $line['product_quantity_refunded'],
                'unit_price_tax_incl' => PiskeeConnectorHelper::money((float) $line['unit_price_tax_incl']),
                'total_tax_incl' => PiskeeConnectorHelper::money((float) $line['total_price_tax_incl']),
            ];
        }

        $history = [];
        foreach ($order->getHistory($c['id_lang']) as $step) {
            $history[] = ['date' => (string) $step['date_add'], 'status' => (string) $step['ostate_name']];
        }

        $shipments = [];
        foreach (Db::getInstance()->executeS('SELECT oc.`tracking_number`, oc.`date_add`, oc.`id_carrier`, ca.`name`, ca.`url`
            FROM `' . _DB_PREFIX_ . 'order_carrier` oc LEFT JOIN `' . _DB_PREFIX_ . 'carrier` ca ON ca.`id_carrier` = oc.`id_carrier`
            WHERE oc.`id_order` = ' . (int) $order->id) ?: [] as $row) {
            $number = (string) $row['tracking_number'];
            $shipments[] = [
                'carrier' => (string) $row['name'],
                'tracking_number' => $number,
                'tracking_url' => $number !== '' && (string) $row['url'] !== '' ? str_replace('@', rawurlencode($number), (string) $row['url']) : '',
                'date' => (string) $row['date_add'],
            ];
        }

        $state = new OrderState((int) $order->current_state, $c['id_lang']);

        return PiskeeConnectorHelper::json([
            'id_order' => (int) $order->id,
            'reference' => (string) $order->reference,
            'date' => (string) $order->date_add,
            'status' => (string) $state->name,
            'id_order_state' => (int) $order->current_state,
            'paid' => (bool) $state->paid,
            'shipped' => (bool) $state->shipped,
            'payment' => (string) $order->payment,
            'customer' => [
                'id_customer' => (int) $customer->id,
                'name' => trim($customer->firstname . ' ' . $customer->lastname),
                'email' => (string) $customer->email,
                'language' => PiskeeConnectorHelper::iso((int) $order->id_lang),
            ],
            'delivery_address' => trim($delivery->address1 . ' ' . $delivery->address2 . ', ' . $delivery->postcode . ' ' . $delivery->city . ', ' . $delivery->country),
            'totals' => [
                'products_tax_incl' => PiskeeConnectorHelper::money((float) $order->total_products_wt),
                'shipping_tax_incl' => PiskeeConnectorHelper::money((float) $order->total_shipping_tax_incl),
                'discounts_tax_incl' => PiskeeConnectorHelper::money((float) $order->total_discounts_tax_incl),
                'paid_tax_incl' => PiskeeConnectorHelper::money((float) $order->total_paid_tax_incl),
            ],
            'lines' => $lines,
            'shipments' => $shipments,
            'history' => $history,
            'private_note' => (string) ($order->note ?? ''),
        ]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function listOrderStatuses(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $out = [];
        foreach (OrderState::getOrderStates($c['id_lang']) as $state) {
            $out[] = [
                'id_order_state' => (int) $state['id_order_state'],
                'name' => (string) $state['name'],
                'sends_email_to_customer' => (bool) $state['send_email'],
                'paid' => (bool) $state['paid'],
                'shipped' => (bool) $state['shipped'],
            ];
        }

        return PiskeeConnectorHelper::json(['statuses' => $out]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function setOrderStatus(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $order = $this->load($args, $c['id_shop']);
        if ($order === null) {
            return PiskeeConnectorHelper::error('Order not found in this shop.');
        }
        $idState = (int) ($args['id_order_state'] ?? 0);
        $state = new OrderState($idState, $c['id_lang']);
        if (!Validate::isLoadedObject($state)) {
            return PiskeeConnectorHelper::error('Status ' . $idState . ' does not exist. Call list_order_statuses.');
        }
        if ((int) $order->current_state === $idState) {
            return PiskeeConnectorHelper::json(['id_order' => (int) $order->id, 'updated' => false, 'message' => 'The order already has this status.']);
        }

        $before = new OrderState((int) $order->current_state, $c['id_lang']);
        $history = new OrderHistory();
        $history->id_order = (int) $order->id;
        $history->id_employee = $c['id_employee'];
        $history->changeIdOrderState($idState, $order, true);
        if (!$history->addWithemail(true)) {
            return PiskeeConnectorHelper::error('PrestaShop refused the status change.');
        }

        return PiskeeConnectorHelper::json([
            'id_order' => (int) $order->id,
            'reference' => (string) $order->reference,
            'updated' => true,
            'from' => (string) $before->name,
            'to' => (string) $state->name,
            'customer_emailed' => (bool) $state->send_email,
        ]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function setTrackingNumber(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $order = $this->load($args, $c['id_shop']);
        if ($order === null) {
            return PiskeeConnectorHelper::error('Order not found in this shop.');
        }
        $number = trim((string) ($args['tracking_number'] ?? ''));
        if ($number === '' || !Validate::isTrackingNumber($number)) {
            return PiskeeConnectorHelper::error('tracking_number is missing or contains characters PrestaShop refuses.');
        }

        $idOrderCarrier = (int) $order->getIdOrderCarrier();
        $orderCarrier = new OrderCarrier($idOrderCarrier);
        if (!Validate::isLoadedObject($orderCarrier)) {
            return PiskeeConnectorHelper::error('This order has no carrier to attach a tracking number to.');
        }
        $before = (string) $orderCarrier->tracking_number;
        if ($before === $number) {
            return PiskeeConnectorHelper::json(['id_order' => (int) $order->id, 'updated' => false, 'message' => 'This tracking number is already set.']);
        }
        $orderCarrier->tracking_number = $number;
        if (!$orderCarrier->update()) {
            return PiskeeConnectorHelper::error('PrestaShop refused the tracking number.');
        }
        $order->shipping_number = $number;
        $order->update();

        $emailed = false;
        if (!empty($args['notify_customer'])) {
            $emailed = (bool) $orderCarrier->sendInTransitEmail($order);
        }
        Hook::exec('actionAdminOrdersTrackingNumberUpdate', ['order' => $order, 'customer' => new Customer((int) $order->id_customer), 'carrier' => new Carrier((int) $orderCarrier->id_carrier)]);

        return PiskeeConnectorHelper::json(['id_order' => (int) $order->id, 'updated' => true, 'from' => $before, 'to' => $number, 'customer_emailed' => $emailed]);
    }

    /**
     * @param array<string, mixed> $args
     */
    private function load(array $args, int $idShop): ?Order
    {
        $id = (int) ($args['id_order'] ?? 0);
        if ($id <= 0 && !empty($args['reference'])) {
            $id = (int) Db::getInstance()->getValue('SELECT `id_order` FROM `' . _DB_PREFIX_ . "orders` WHERE `reference` = '" . pSQL((string) $args['reference']) . "' AND `id_shop` = " . $idShop . ' ORDER BY `id_order` DESC');
        }
        $order = new Order($id);

        return Validate::isLoadedObject($order) && (int) $order->id_shop === $idShop ? $order : null;
    }
}
