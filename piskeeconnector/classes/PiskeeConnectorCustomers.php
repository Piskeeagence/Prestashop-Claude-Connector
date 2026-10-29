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
 * Customer accounts (professional accounts included) and customer groups
 * with their discount and price display. Prices per group are set with
 * create_promotion or create_price_rule and their id_group.
 */
class PiskeeConnectorCustomers
{
    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function listGroups(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $out = [];
        foreach (Group::getGroups($c['id_lang'], $c['id_shop']) as $row) {
            $id = (int) $row['id_group'];
            $out[] = [
                'id_group' => $id,
                'name' => (string) $row['name'],
                'discount_percent' => (float) $row['reduction'],
                'prices_shown' => (int) $row['price_display_method'] === 1 ? 'tax excluded' : 'tax included',
                'show_prices' => (bool) $row['show_prices'],
                'customers' => (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'customer_group` cg INNER JOIN `' . _DB_PREFIX_ . 'customer` cu ON cu.`id_customer` = cg.`id_customer` AND cu.`deleted` = 0 AND cu.`id_shop` = ' . (int) $c['id_shop'] . ' WHERE cg.`id_group` = ' . $id),
                'built_in' => in_array($id, [(int) Configuration::get('PS_UNIDENTIFIED_GROUP'), (int) Configuration::get('PS_GUEST_GROUP'), (int) Configuration::get('PS_CUSTOMER_GROUP')], true),
            ];
        }

        return PiskeeConnectorHelper::json(['groups' => $out, 'note' => 'Prices for one group: create_promotion or create_price_rule with id_group. A group discount applies to the whole catalogue for its members.']);
    }

    /**
     * Creates a group, or changes one when id_group is given.
     *
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function saveGroup(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $id = (int) ($args['id_group'] ?? 0);
        $group = new Group($id > 0 ? $id : null);
        if ($id > 0 && !Validate::isLoadedObject($group)) {
            return PiskeeConnectorHelper::error('Group ' . $id . ' not found. Call list_customer_groups.');
        }
        $translations = PiskeeConnectorHelper::translations($args['translations'] ?? null, $c['id_shop']);
        if (!$translations['ok']) {
            return PiskeeConnectorHelper::error($translations['error']);
        }
        $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');
        if ($id <= 0 && trim((string) ($translations['map'][$defaultLang]['name'] ?? '')) === '') {
            return PiskeeConnectorHelper::error('A new group needs a name in the default language.');
        }
        $fallback = (string) ($translations['map'][$defaultLang]['name'] ?? '');
        foreach (PiskeeConnectorHelper::languages($c['id_shop']) as $idLang) {
            $name = $translations['map'][$idLang]['name'] ?? ($id > 0 ? null : $fallback);
            if ($name !== null) {
                $group->name[$idLang] = Tools::substr(trim(strip_tags((string) $name)), 0, 32);
            }
        }
        if (isset($args['discount_percent'])) {
            $discount = (float) $args['discount_percent'];
            if ($discount < 0 || $discount > 99) {
                return PiskeeConnectorHelper::error('discount_percent must be between 0 and 99.');
            }
            $group->reduction = $discount;
        } elseif ($id <= 0) {
            $group->reduction = 0;
        }
        if (isset($args['prices_tax_excluded'])) {
            $group->price_display_method = $args['prices_tax_excluded'] ? 1 : 0;
        } elseif ($id <= 0) {
            $group->price_display_method = (int) Configuration::get('PS_TAX') ? 0 : 1;
        }
        if (isset($args['show_prices'])) {
            $group->show_prices = (bool) $args['show_prices'];
        } elseif ($id <= 0) {
            $group->show_prices = true;
        }
        $error = PiskeeConnectorHelper::validate($group);
        if ($error !== '') {
            return PiskeeConnectorHelper::error('Not saved: ' . $error);
        }
        $ok = $id > 0 ? $group->update() : $group->add();
        if (!$ok) {
            return PiskeeConnectorHelper::error('PrestaShop refused to save the group.');
        }
        if ($id <= 0) {
            // A new group sees every category and module, like the back office gives it.
            Db::getInstance()->execute('INSERT IGNORE INTO `' . _DB_PREFIX_ . 'category_group` (`id_category`, `id_group`) SELECT `id_category`, ' . (int) $group->id . ' FROM `' . _DB_PREFIX_ . 'category`');
            $modules = Db::getInstance()->executeS('SELECT `id_module` FROM `' . _DB_PREFIX_ . 'module` WHERE `active` = 1') ?: [];
            foreach ($modules as $module) {
                Db::getInstance()->execute('INSERT IGNORE INTO `' . _DB_PREFIX_ . 'module_group` (`id_module`, `id_shop`, `id_group`) VALUES (' . (int) $module['id_module'] . ', ' . (int) $c['id_shop'] . ', ' . (int) $group->id . ')');
            }
        }

        return PiskeeConnectorHelper::json(['id_group' => (int) $group->id, 'created' => $id <= 0, 'discount_percent' => (float) $group->reduction,
            'prices_shown' => (int) $group->price_display_method === 1 ? 'tax excluded' : 'tax included']);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function createCustomer(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $email = Tools::strtolower(trim((string) ($args['email'] ?? '')));
        if (!Validate::isEmail($email)) {
            return PiskeeConnectorHelper::error('A valid email is required.');
        }
        if (Customer::customerExists($email, false, true)) {
            return PiskeeConnectorHelper::error('An account already uses this email. Find it with search_customers.');
        }
        $customer = new Customer();
        $customer->email = $email;
        $customer->firstname = trim((string) ($args['firstname'] ?? ''));
        $customer->lastname = trim((string) ($args['lastname'] ?? ''));
        $customer->company = trim((string) ($args['company'] ?? ''));
        $customer->siret = trim((string) ($args['siret'] ?? ''));
        $customer->id_shop = $c['id_shop'];
        $customer->id_shop_group = (int) Shop::getGroupFromShop($c['id_shop']);
        $customer->id_lang = $c['id_lang'];
        $customer->active = 1;
        $customer->newsletter = 0;
        $customer->optin = 0;
        // Nobody knows this password: the customer sets their own with "Forgot your password?".
        $password = bin2hex(random_bytes(16)) . 'Aa1!';
        $customer->passwd = class_exists('PrestaShop\\PrestaShop\\Core\\Crypto\\Hashing') ? (new PrestaShop\PrestaShop\Core\Crypto\Hashing())->hash($password) : Tools::hash($password);
        $groups = $this->groupIds($args['group_ids'] ?? null);
        if (is_string($groups)) {
            return PiskeeConnectorHelper::error($groups);
        }
        $default = (int) ($args['id_default_group'] ?? ($groups[0] ?? Configuration::get('PS_CUSTOMER_GROUP')));
        if ($groups === []) {
            $groups = [(int) Configuration::get('PS_CUSTOMER_GROUP')];
        }
        if (!in_array($default, $groups, true)) {
            $groups[] = $default;
        }
        $customer->id_default_group = $default;
        $error = PiskeeConnectorHelper::validate($customer);
        if ($error !== '') {
            return PiskeeConnectorHelper::error('Not created: ' . $error);
        }
        if (!$customer->add()) {
            return PiskeeConnectorHelper::error('PrestaShop refused to create the account.');
        }
        $customer->updateGroup($groups);

        return PiskeeConnectorHelper::json([
            'id_customer' => (int) $customer->id,
            'created' => true,
            'groups' => $groups,
            'id_default_group' => $default,
            'note' => 'No e-mail was sent and nobody knows the password: the customer gets in with "Forgot your password?" on the login page. Newsletter and partner offers are off: only the customer can accept them.',
        ]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function updateCustomer(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $id = (int) ($args['id_customer'] ?? 0);
        $customer = new Customer($id);
        if (!Validate::isLoadedObject($customer) || (int) $customer->id_shop !== $c['id_shop'] || $customer->deleted || $customer->is_guest) {
            return PiskeeConnectorHelper::error('Customer account ' . $id . ' not found in this shop.');
        }
        $changes = [];
        foreach (['firstname', 'lastname', 'company', 'siret', 'website'] as $field) {
            if (array_key_exists($field, $args) && property_exists($customer, $field)) {
                $value = trim(strip_tags((string) $args[$field]));
                if ((string) $customer->{$field} !== $value) {
                    $changes[$field] = ['from' => (string) $customer->{$field}, 'to' => $value];
                    $customer->{$field} = $value;
                }
            }
        }
        if (isset($args['email'])) {
            $email = Tools::strtolower(trim((string) $args['email']));
            if (!Validate::isEmail($email)) {
                return PiskeeConnectorHelper::error('Invalid email.');
            }
            if ($email !== $customer->email) {
                if (Customer::customerExists($email, false, true)) {
                    return PiskeeConnectorHelper::error('Another account already uses this email.');
                }
                $changes['email'] = ['from' => $customer->email, 'to' => $email];
                $customer->email = $email;
            }
        }
        if (isset($args['active']) && (bool) $customer->active !== (bool) $args['active']) {
            $changes['active'] = ['from' => (bool) $customer->active, 'to' => (bool) $args['active']];
            $customer->active = (bool) $args['active'];
        }
        $groups = null;
        if (isset($args['group_ids'])) {
            $groups = $this->groupIds($args['group_ids']);
            if (is_string($groups)) {
                return PiskeeConnectorHelper::error($groups);
            }
            if ($groups === []) {
                return PiskeeConnectorHelper::error('group_ids cannot be empty: an account is always in at least one group.');
            }
        }
        if (isset($args['id_default_group'])) {
            $default = (int) $args['id_default_group'];
            $all = $groups ?? array_map('intval', $customer->getGroups());
            if (!in_array($default, $all, true)) {
                return PiskeeConnectorHelper::error('id_default_group must be one of the customer\'s groups.');
            }
            if ($default !== (int) $customer->id_default_group) {
                $changes['id_default_group'] = ['from' => (int) $customer->id_default_group, 'to' => $default];
                $customer->id_default_group = $default;
            }
        } elseif ($groups !== null && !in_array((int) $customer->id_default_group, $groups, true)) {
            $changes['id_default_group'] = ['from' => (int) $customer->id_default_group, 'to' => $groups[0]];
            $customer->id_default_group = $groups[0];
        }
        if ($groups !== null) {
            $current = array_map('intval', $customer->getGroups());
            sort($current);
            $sorted = $groups;
            sort($sorted);
            if ($current !== $sorted) {
                $changes['groups'] = ['from' => $current, 'to' => $sorted];
            }
        }
        if ($changes === []) {
            return PiskeeConnectorHelper::json(['id_customer' => $id, 'updated' => false, 'message' => 'Nothing to change.']);
        }
        $error = PiskeeConnectorHelper::validate($customer);
        if ($error !== '') {
            return PiskeeConnectorHelper::error('Not saved: ' . $error);
        }
        if (!$customer->update()) {
            return PiskeeConnectorHelper::error('PrestaShop refused to save the account.');
        }
        if ($groups !== null) {
            $customer->updateGroup($groups);
        }

        return PiskeeConnectorHelper::json(['id_customer' => $id, 'updated' => true, 'changes' => $changes]);
    }

    /**
     * @param mixed $value
     *
     * @return array<int, int>|string
     */
    private function groupIds($value)
    {
        if ($value === null) {
            return [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', is_array($value) ? $value : []))));
        foreach ($ids as $id) {
            if (!Validate::isLoadedObject(new Group($id))) {
                return 'Group ' . $id . ' not found. Call list_customer_groups.';
            }
        }

        return $ids;
    }
}
