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
 * The main menu of the shop, as built by PrestaShop's native module
 * "Main menu" (ps_mainmenu), used by Classic, Hummingbird and most themes
 * based on them.
 *
 * The menu is an ordered list of entries kept in MOD_BLOCKTOPMENU_ITEMS
 * (CAT3, CMS1, LNK2...). A category entry shows the category and every
 * active subcategory under it, in the order of the category positions: a
 * category created under a category of the menu appears there by itself.
 * Third-party mega menus keep their own data and are only listed.
 */
class PiskeeConnectorMenu
{
    private const MODULE = 'ps_mainmenu';
    private const KEY = 'MOD_BLOCKTOPMENU_ITEMS';

    /** Menu code => type name given to the assistant. */
    private const TYPES = [
        'CAT' => 'category', 'PRD' => 'product', 'CMS' => 'cms_page', 'CMS_CAT' => 'cms_category',
        'MAN' => 'brand', 'ALLMAN' => 'all_brands', 'SUP' => 'supplier', 'ALLSUP' => 'all_suppliers',
        'LNK' => 'link', 'SHOP' => 'shop',
    ];

    /** Types that carry no id. */
    private const NO_ID = ['all_brands', 'all_suppliers'];

    private const MAX_ITEMS = 40;

    /**
     * @return array<int, string>
     */
    public static function typeNames(): array
    {
        return array_values(self::TYPES);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function getMenu(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $others = self::otherMenus();
        if (!self::available()) {
            return PiskeeConnectorHelper::json([
                'native_menu' => false,
                'other_menu_modules' => $others,
                'note' => 'The native module "Main menu" (ps_mainmenu) is not installed or is disabled, so the menu cannot be changed from here. '
                    . ($others !== [] ? 'The menu of this shop comes from a third-party module, managed in Modules > Module Manager.' : 'The merchant can install it for free from Modules > Module Catalog.'),
            ]);
        }
        $depth = max(1, min(4, (int) ($args['depth'] ?? 2)));
        $raw = self::raw($c['id_shop']);
        $items = [];
        foreach (self::parse($raw) as $index => $code) {
            $items[] = ['position' => $index + 1] + $this->describe($code, $c['id_shop'], $c['id_lang'], $depth);
        }

        return PiskeeConnectorHelper::json([
            'native_menu' => true,
            'items' => $items,
            'custom_links' => $this->links($c['id_shop']),
            'other_menu_modules' => $others,
            'note' => 'A category entry shows the category and its active subcategories in their category order (change it with update_category position). '
                . 'Inactive categories are hidden. Change the entries with set_menu, always passing the whole list.'
                . ($others !== [] ? ' Other menu modules are active: if the visible menu comes from one of them, set_menu does not change it.' : ''),
        ]);
    }

    /**
     * Replaces the entries of the menu with the given ordered list.
     *
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function setMenu(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        if (!self::available()) {
            return PiskeeConnectorHelper::error('The native module "Main menu" (ps_mainmenu) is not installed or is disabled. Call get_menu to see which module builds the menu.');
        }
        $entries = $args['items'] ?? null;
        if (!is_array($entries) || $entries === []) {
            return PiskeeConnectorHelper::error('items must list every menu entry, in order. Call get_menu first and send the whole list back with your changes.');
        }
        if (count($entries) > self::MAX_ITEMS) {
            return PiskeeConnectorHelper::error('At most ' . self::MAX_ITEMS . ' entries.');
        }

        // Check everything before writing anything.
        $plan = [];
        foreach (array_values($entries) as $i => $entry) {
            $checked = $this->check(is_array($entry) ? $entry : [], $c['id_shop']);
            if (is_string($checked)) {
                return PiskeeConnectorHelper::error('Entry ' . ($i + 1) . ': ' . $checked . ' Nothing was changed.');
            }
            $plan[] = $checked;
        }

        $before = self::raw($c['id_shop']);
        $codes = [];
        $created = [];
        foreach ($plan as $step) {
            if ($step['code'] === 'LNK' && $step['id'] === 0) {
                $id = $this->addLink($step['labels'], $step['urls'], $step['new_window'], $c['id_shop']);
                if ($id <= 0) {
                    return PiskeeConnectorHelper::error('PrestaShop refused to create the link "' . reset($step['labels']) . '". The menu was not changed.');
                }
                $created[] = $id;
                $codes[] = 'LNK' . $id;
                continue;
            }
            if ($step['code'] === 'LNK' && $step['labels'] !== []) {
                $this->updateLink($step['id'], $step['labels'], $step['urls'], $step['new_window'], $c['id_shop']);
            }
            $codes[] = $step['code'] . ($step['id'] > 0 || !in_array(self::TYPES[$step['code']], self::NO_ID, true) ? (string) $step['id'] : '');
        }
        $codes = array_values(array_unique($codes));
        $after = implode(',', $codes);

        if ($after === $before && $created === []) {
            $changedLinks = array_filter($plan, function ($step) {
                return $step['code'] === 'LNK' && $step['labels'] !== [];
            });
            if ($changedLinks === []) {
                return PiskeeConnectorHelper::json(['updated' => false, 'message' => 'The menu already has exactly these entries.']);
            }
        }
        if (Shop::isFeatureActive()) {
            Configuration::updateValue(self::KEY, $after, false, (int) Shop::getGroupFromShop($c['id_shop']), $c['id_shop']);
        } else {
            Configuration::updateValue(self::KEY, $after);
        }
        self::clearCache();

        $items = [];
        foreach ($codes as $index => $code) {
            $items[] = ['position' => $index + 1] + $this->describe($code, $c['id_shop'], $c['id_lang'], 1);
        }

        return PiskeeConnectorHelper::json([
            'updated' => true,
            'items' => $items,
            'links_created' => $created,
            'previous' => $before,
            'note' => 'To go back, call set_menu with the previous entries (previous is the raw list: CAT = category, CMS = cms_page, CMS_CAT = cms_category, PRD = product, MAN = brand, SUP = supplier, LNK = link, SHOP = shop).',
        ]);
    }

    public static function available(): bool
    {
        return Module::isInstalled(self::MODULE) && Module::isEnabled(self::MODULE)
            && (bool) Db::getInstance()->executeS("SHOW TABLES LIKE '" . pSQL(_DB_PREFIX_ . 'linksmenutop') . "'");
    }

    private static function raw(int $idShop): string
    {
        return (string) Configuration::get(self::KEY, null, (int) Shop::getGroupFromShop($idShop), $idShop);
    }

    /**
     * @return array<int, string>
     */
    private static function parse(string $raw): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $raw)), function ($code) {
            return (bool) preg_match('/^(CAT|PRD|CMS_CAT|CMS|MAN|ALLMAN|SUP|ALLSUP|LNK|SHOP)(\d*)$/', $code);
        }));
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(string $code, int $idShop, int $idLang, int $depth): array
    {
        preg_match('/^(CAT|PRD|CMS_CAT|CMS|MAN|ALLMAN|SUP|ALLSUP|LNK|SHOP)(\d*)$/', $code, $m);
        $prefix = $m[1] ?? '';
        $id = (int) ($m[2] ?? 0);
        $type = self::TYPES[$prefix] ?? 'unknown';
        $out = ['type' => $type];
        if (!in_array($type, self::NO_ID, true)) {
            $out['id'] = $id;
        }
        $link = Context::getContext()->link;
        switch ($prefix) {
            case 'CAT':
                $category = new Category($id, $idLang, $idShop);
                if (!Validate::isLoadedObject($category)) {
                    return $out + ['missing' => true, 'note' => 'This category no longer exists: the menu skips it.'];
                }
                $isHome = $id === (int) Configuration::get('PS_HOME_CATEGORY');
                $out += ['label' => (string) $category->name, 'active' => (bool) $category->active, 'url' => $link->getCategoryLink($category, null, $idLang, null, $idShop)];
                if ($isHome) {
                    $out['note'] = 'Home category: the menu shows the home link with every top category under it.';
                }
                $out['children'] = $this->children($id, $idShop, $idLang, $depth);
                break;
            case 'PRD':
                $product = new Product($id, false, $idLang, $idShop);
                $out += Validate::isLoadedObject($product) ? ['label' => (string) $product->name, 'active' => (bool) $product->active] : ['missing' => true];
                break;
            case 'CMS':
                $cms = new CMS($id, $idLang, $idShop);
                $out += Validate::isLoadedObject($cms) ? ['label' => (string) $cms->meta_title, 'active' => (bool) $cms->active] : ['missing' => true];
                break;
            case 'CMS_CAT':
                $cmsCategory = new CMSCategory($id, $idLang, $idShop);
                $out += Validate::isLoadedObject($cmsCategory) ? ['label' => (string) $cmsCategory->name, 'note' => 'Shows the CMS category with its pages and subcategories.'] : ['missing' => true];
                break;
            case 'MAN':
                $out += ['label' => (string) Manufacturer::getNameById($id)];
                break;
            case 'SUP':
                $out += ['label' => (string) Supplier::getNameById($id)];
                break;
            case 'ALLMAN':
                $out += ['note' => 'Every brand, under a "Brands" entry.'];
                break;
            case 'ALLSUP':
                $out += ['note' => 'Every supplier, under a "Suppliers" entry.'];
                break;
            case 'SHOP':
                $shop = new Shop($id);
                $out += Validate::isLoadedObject($shop) ? ['label' => (string) $shop->name] : ['missing' => true];
                break;
            case 'LNK':
                $out += $this->link($id, $idShop) ?: ['missing' => true];
                break;
        }

        return $out;
    }

    /**
     * Active subcategories as the menu shows them, $depth levels deep.
     *
     * @return array<int, array<string, mixed>>
     */
    private function children(int $idParent, int $idShop, int $idLang, int $depth): array
    {
        if ($depth <= 0) {
            return [];
        }
        $rows = Db::getInstance()->executeS('SELECT c.`id_category`, c.`active`, cl.`name` FROM `' . _DB_PREFIX_ . 'category` c'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'category_shop` cs ON cs.`id_category` = c.`id_category` AND cs.`id_shop` = ' . $idShop
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON cl.`id_category` = c.`id_category` AND cl.`id_lang` = ' . $idLang . ' AND cl.`id_shop` = ' . $idShop
            . ' WHERE c.`id_parent` = ' . $idParent . ' ORDER BY cs.`position` ASC, c.`id_category` ASC') ?: [];
        $out = [];
        foreach ($rows as $index => $row) {
            $node = ['id_category' => (int) $row['id_category'], 'position' => $index + 1, 'name' => (string) $row['name'], 'active' => (bool) $row['active']];
            if (!$node['active']) {
                $node['shown'] = false;
            } elseif ($depth > 1) {
                $sub = $this->children((int) $row['id_category'], $idShop, $idLang, $depth - 1);
                if ($sub !== []) {
                    $node['children'] = $sub;
                }
            }
            $out[] = $node;
        }

        return $out;
    }

    /**
     * Validates one entry sent by the assistant.
     *
     * @param array<string, mixed> $entry
     *
     * @return array{code:string, id:int, labels:array<int, string>, urls:array<int, string>, new_window:bool}|string
     */
    private function check(array $entry, int $idShop)
    {
        $type = (string) ($entry['type'] ?? '');
        $prefix = array_search($type, self::TYPES, true);
        if ($prefix === false) {
            return 'unknown type "' . $type . '". Types: ' . implode(', ', self::TYPES) . '.';
        }
        $id = (int) ($entry['id'] ?? 0);
        $step = ['code' => (string) $prefix, 'id' => $id, 'labels' => [], 'urls' => [], 'new_window' => !empty($entry['new_window'])];
        if (in_array($type, self::NO_ID, true)) {
            $step['id'] = 0;

            return $step;
        }
        if ($type === 'link') {
            return $this->checkLink($entry, $step, $idShop);
        }
        if ($id <= 0) {
            return 'id is required for a ' . $type . '.';
        }
        switch ($type) {
            case 'category':
                $category = new Category($id, null, $idShop);
                $ok = PiskeeConnectorHelper::inShop($category, $idShop) && $id !== (int) Configuration::get('PS_ROOT_CATEGORY');
                break;
            case 'product':
                $ok = PiskeeConnectorHelper::inShop(new Product($id, false, null, $idShop), $idShop);
                break;
            case 'cms_page':
                $ok = PiskeeConnectorHelper::inShop(new CMS($id, null, $idShop), $idShop);
                break;
            case 'cms_category':
                $ok = Validate::isLoadedObject(new CMSCategory($id, null, $idShop));
                break;
            case 'brand':
                $ok = Validate::isLoadedObject(new Manufacturer($id));
                break;
            case 'supplier':
                $ok = Validate::isLoadedObject(new Supplier($id));
                break;
            case 'shop':
                $ok = Validate::isLoadedObject(new Shop($id));
                break;
            default:
                $ok = false;
        }

        return $ok ? $step : $type . ' ' . $id . ' not found in this shop.';
    }

    /**
     * @param array<string, mixed> $entry
     * @param array{code:string, id:int, labels:array<int, string>, urls:array<int, string>, new_window:bool} $step
     *
     * @return array{code:string, id:int, labels:array<int, string>, urls:array<int, string>, new_window:bool}|string
     */
    private function checkLink(array $entry, array $step, int $idShop)
    {
        $id = $step['id'];
        if ($id > 0 && !$this->link($id, $idShop)) {
            return 'link ' . $id . ' not found. Existing links are listed by get_menu (custom_links).';
        }
        $translations = PiskeeConnectorHelper::translations($entry['translations'] ?? null, $idShop);
        if (!$translations['ok']) {
            return $translations['error'];
        }
        if ($translations['map'] === []) {
            if ($id > 0) {
                return $step;
            }

            return 'a new link needs translations with a label and a url.';
        }

        // Missing languages take the default language, or the first one given.
        $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $current = $id > 0 ? $this->linkRows($id, $idShop) : [];
        $fallback = $translations['map'][$defaultLang] ?? reset($translations['map']);
        foreach (PiskeeConnectorHelper::languages($idShop) as $idLang) {
            $given = $translations['map'][$idLang] ?? null;
            $label = trim(strip_tags((string) ($given['label'] ?? ($current[$idLang]['label'] ?? ($fallback['label'] ?? '')))));
            $url = trim((string) ($given['url'] ?? ($current[$idLang]['link'] ?? ($fallback['url'] ?? ''))));
            if ($label === '' || $url === '') {
                return 'every link needs a label and a url.';
            }
            if (Tools::strlen($label) > 128) {
                return 'the label "' . $label . '" is longer than 128 characters.';
            }
            if (!preg_match('#^(https?://[^\s"<>]+|/[^\s"<>]*|\#[^\s"<>]*)$#i', $url)) {
                return 'the url "' . $url . '" must start with https://, http:// or / (a page of the shop).';
            }
            $step['labels'][$idLang] = $label;
            $step['urls'][$idLang] = $url;
        }
        if (!array_key_exists('new_window', $entry) && $id > 0) {
            $step['new_window'] = !empty(reset($current)['new_window']);
        }

        return $step;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function links(int $idShop): array
    {
        $rows = Db::getInstance()->executeS('SELECT `id_linksmenutop` FROM `' . _DB_PREFIX_ . 'linksmenutop` WHERE `id_shop` IN (0, ' . $idShop . ') ORDER BY `id_linksmenutop`') ?: [];
        $out = [];
        foreach ($rows as $row) {
            $out[] = ['id' => (int) $row['id_linksmenutop']] + $this->link((int) $row['id_linksmenutop'], $idShop);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function link(int $id, int $idShop): array
    {
        $rows = $this->linkRows($id, $idShop);
        if ($rows === []) {
            return [];
        }
        $translations = [];
        $newWindow = false;
        foreach ($rows as $idLang => $row) {
            $translations[PiskeeConnectorHelper::iso($idLang)] = ['label' => (string) $row['label'], 'url' => (string) $row['link']];
            $newWindow = (bool) $row['new_window'];
        }
        $defaultIso = PiskeeConnectorHelper::iso((int) Configuration::get('PS_LANG_DEFAULT'));

        return ['label' => $translations[$defaultIso]['label'] ?? (string) reset($translations)['label'], 'translations' => $translations, 'new_window' => $newWindow];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function linkRows(int $id, int $idShop): array
    {
        $rows = Db::getInstance()->executeS('SELECT l.`new_window`, ll.`id_lang`, ll.`label`, ll.`link` FROM `' . _DB_PREFIX_ . 'linksmenutop` l'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'linksmenutop_lang` ll ON ll.`id_linksmenutop` = l.`id_linksmenutop` AND ll.`id_shop` = ' . $idShop
            . ' WHERE l.`id_linksmenutop` = ' . $id . ' AND l.`id_shop` IN (0, ' . $idShop . ')') ?: [];
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['id_lang']] = $row;
        }

        return $out;
    }

    /**
     * @param array<int, string> $labels
     * @param array<int, string> $urls
     */
    private function addLink(array $labels, array $urls, bool $newWindow, int $idShop): int
    {
        $db = Db::getInstance();
        if (!$db->insert('linksmenutop', ['new_window' => (int) $newWindow, 'id_shop' => $idShop])) {
            return 0;
        }
        $id = (int) $db->Insert_ID();
        foreach ($labels as $idLang => $label) {
            $db->insert('linksmenutop_lang', [
                'id_linksmenutop' => $id, 'id_lang' => (int) $idLang, 'id_shop' => $idShop,
                'label' => pSQL($label), 'link' => pSQL($urls[$idLang]),
            ]);
        }

        return $id;
    }

    /**
     * @param array<int, string> $labels
     * @param array<int, string> $urls
     */
    private function updateLink(int $id, array $labels, array $urls, bool $newWindow, int $idShop): void
    {
        $db = Db::getInstance();
        $db->update('linksmenutop', ['new_window' => (int) $newWindow], '`id_linksmenutop` = ' . $id);
        foreach ($labels as $idLang => $label) {
            $where = '`id_linksmenutop` = ' . $id . ' AND `id_lang` = ' . (int) $idLang . ' AND `id_shop` = ' . $idShop;
            $exists = (bool) $db->getValue('SELECT 1 FROM `' . _DB_PREFIX_ . 'linksmenutop_lang` WHERE ' . $where);
            if ($exists) {
                $db->update('linksmenutop_lang', ['label' => pSQL($label), 'link' => pSQL($urls[$idLang])], $where);
            } else {
                $db->insert('linksmenutop_lang', ['id_linksmenutop' => $id, 'id_lang' => (int) $idLang, 'id_shop' => $idShop, 'label' => pSQL($label), 'link' => pSQL($urls[$idLang])]);
            }
        }
    }

    /**
     * Empties the menu cache of ps_mainmenu (JSON files per customer group).
     */
    private static function clearCache(): void
    {
        $dir = _PS_CACHE_DIR_ . 'ps_mainmenu';
        if (!is_dir($dir)) {
            return;
        }
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->isFile() && substr($file->getFilename(), -5) === '.json') {
                @unlink($file->getPathname());
            }
        }
    }

    /**
     * Other active modules that look like a menu.
     *
     * @return array<int, array<string, string>>
     */
    public static function otherMenus(): array
    {
        $rows = Db::getInstance()->executeS("SELECT `name` FROM `" . _DB_PREFIX_ . "module` WHERE `active` = 1 AND `name` != '" . self::MODULE . "'"
            . " AND (`name` LIKE '%menu%' OR `name` LIKE '%megamenu%')") ?: [];
        $out = [];
        foreach ($rows as $row) {
            $name = (string) $row['name'];
            if (in_array($name, ['piskeeconnector', 'ps_customeraccountlinks', 'blockcustomermenu'], true)) {
                continue;
            }
            $module = Module::getInstanceByName($name);
            $out[] = ['module' => $name, 'name' => $module ? (string) $module->displayName : $name];
        }

        return $out;
    }
}
