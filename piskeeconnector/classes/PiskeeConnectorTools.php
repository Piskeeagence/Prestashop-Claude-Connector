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
 * Every tool this module publishes, as the plain arrays the MCP server reads.
 *
 * Descriptions are read by the assistant, not by the merchant: they stay in
 * English and say when to use a tool, when not to, and what never to guess.
 * Titles and scope labels are shown to the merchant and are translated.
 */
class PiskeeConnectorTools
{
    /** Modules allowed to add tools through actionPiskeeConnectorTools. */
    private const ADDONS = ['piskeeconnectorpro'];

    /** @var array<int, array<string, array<string, mixed>>> */
    private static $extensions = [];

    private const LOST_ANSWER = ' If the answer is lost, read the object again before calling this tool a second time.';

    /**
     * @return array<string, mixed>
     */
    public static function declaration(string $lang): array
    {
        $catalog = new PiskeeConnectorCatalog();
        $variants = new PiskeeConnectorVariants();
        $merch = new PiskeeConnectorMerchandising();
        $images = new PiskeeConnectorImages();
        $ops = new PiskeeConnectorOperations();
        $themes = new PiskeeConnectorThemes();
        $orders = new PiskeeConnectorOrders();
        $cms = new PiskeeConnectorCms();
        $cross = new PiskeeConnectorCrossSelling();
        $llms = new PiskeeConnectorLlms();
        $menu = new PiskeeConnectorMenu();
        $look = new PiskeeConnectorAppearance();
        $hooks = new PiskeeConnectorHooks();
        $sales = new PiskeeConnectorSales();
        $customers = new PiskeeConnectorCustomers();
        $system = new PiskeeConnectorSystem();

        $instances = ['hooks' => $hooks, 'sales' => $sales, 'customers' => $customers, 'system' => $system, 'look' => $look, 'menu' => $menu, 'catalog' => $catalog, 'variants' => $variants, 'merch' => $merch, 'images' => $images, 'ops' => $ops, 'themes' => $themes, 'orders' => $orders, 'cms' => $cms, 'cross' => $cross, 'llms' => $llms];
        $tools = [];
        foreach (self::all() as $name => $def) {
            if (is_array($def['handler']) && is_string($def['handler'][0] ?? null) && isset($instances[$def['handler'][0]])) {
                [$object, $method] = $def['handler'];
                $handler = [$instances[$object], $method];
            } else {
                $handler = $def['handler'];
            }
            if ($def['permission'][0] !== 'view' && empty($def['self_metered'])) {
                // Changes go through the free plan allowance; reads never do.
                $handler = PiskeeConnectorLicense::metered($handler);
            }
            $tools[] = [
                'name' => $name,
                'title' => isset($def['title']) && is_array($def['title']) ? PiskeeConnectorI18n::pick($def['title'], $lang, $name) : PiskeeConnectorI18n::toolTitle($name, $lang),
                'description' => $def['description'],
                'input' => self::schema($def['input'] ?? []),
                'scopes' => [$def['scope']],
                'permission' => $def['permission'],
                'handler' => $handler,
            ];
        }

        $scopes = [];
        foreach (self::scopeNames() as $scope) {
            $scopes[$scope] = PiskeeConnectorI18n::scopeLabel($scope, $lang);
        }

        return ['scopes' => $scopes, 'tools' => $tools];
    }

    /**
     * @return array<int, string>
     */
    public static function scopeNames(): array
    {
        return ['catalog.read', 'catalog.write', 'orders.read', 'orders.write', 'customers.read', 'customers.write', 'sales.read', 'service.read', 'service.write', 'marketing.write', 'appearance.write', 'theme.read', 'theme.write'];
    }

    /**
     * Tool name => group, scope and nature, for the module page.
     *
     * @return array<string, array{group:string, scope:string, writes:bool}>
     */
    public static function summary(): array
    {
        $out = [];
        foreach (self::all() as $name => $def) {
            $out[$name] = ['group' => $def['group'], 'scope' => $def['scope'], 'writes' => $def['permission'][0] !== 'view', 'title' => $def['title'] ?? null];
        }

        return $out;
    }

    /**
     * Core tools, plus the tools of Piskee add-ons when the shop is on Pro.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function all(): array
    {
        return self::definitions() + self::extensions();
    }

    /**
     * Tools brought by add-on modules (such as Piskee Pro) through the hook
     * actionPiskeeConnectorTools. Each one must give a name, a title keyed by
     * language (fr, en, es, it, pl; falls back to en), a description, a known scope, a permission and a callable
     * handler. Anything malformed is ignored; core tool names cannot be
     * replaced. Add-on tools are offered only to shops with a valid Pro key.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function extensions(): array
    {
        $shop = Context::getContext()->shop;
        $idShop = isset($shop->id) ? (int) $shop->id : (int) Configuration::get('PS_SHOP_DEFAULT');
        if (isset(self::$extensions[$idShop])) {
            return self::$extensions[$idShop];
        }
        self::$extensions[$idShop] = [];
        if ($idShop <= 0 || !PiskeeConnectorLicense::isPro($idShop)) {
            return [];
        }
        try {
            $answers = Hook::exec('actionPiskeeConnectorTools', [], null, true);
        } catch (Throwable $e) {
            return [];
        }
        if (!is_array($answers)) {
            return [];
        }
        $core = self::definitions();
        $scopes = self::scopeNames();
        $out = [];
        foreach ($answers as $module => $tools) {
            // Only Piskee's own add-ons may publish tools to the assistant.
            if (!in_array((string) $module, self::ADDONS, true) || !is_array($tools)) {
                continue;
            }
            foreach ($tools as $name => $def) {
                if (!is_string($name) || !preg_match('/^[a-z][a-z0-9_]{2,63}$/', $name) || isset($core[$name]) || isset($out[$name])
                    || !is_array($def) || !is_callable($def['handler'] ?? null) || !is_string($def['description'] ?? null)
                    || !in_array($def['scope'] ?? '', $scopes, true)
                    || !is_array($def['permission'] ?? null) || !in_array($def['permission'][0] ?? '', ['view', 'add', 'edit', 'delete'], true)
                    || !is_string($def['permission'][1] ?? null)
                    || !is_array($def['title'] ?? null) || !is_string($def['title']['en'] ?? null)) {
                    continue;
                }
                // A read scope goes with a view right, a write scope with a change right: no write tool shown as read-only.
                $reads = (bool) preg_match('/\.read$/', (string) $def['scope']);
                if ($reads !== ($def['permission'][0] === 'view')) {
                    continue;
                }
                $def['group'] = is_string($def['group'] ?? null) ? $def['group'] : 'pro';
                $out[$name] = $def;
            }
        }

        return self::$extensions[$idShop] = $out;
    }

    /**
     * @param array<string, mixed> $properties
     * @param array<int, string> $required
     *
     * @return array<string, mixed>
     */
    private static function schema(array $input): array
    {
        $properties = $input['properties'] ?? [];

        $schema = [
            'type' => 'object',
            'properties' => $properties === [] ? new stdClass() : $properties,
            'additionalProperties' => false,
        ];
        if (!empty($input['required'])) {
            $schema['required'] = $input['required'];
        }

        return $schema;
    }

    /**
     * A translations object: {"fr": {...}, "en": {...}}.
     *
     * @param array<int, string> $fields
     *
     * @return array<string, mixed>
     */
    private static function translations(array $fields, string $what): array
    {
        $props = [];
        foreach ($fields as $field) {
            $props[$field] = ['type' => 'string'];
        }

        return [
            'type' => 'object',
            'description' => $what . ' Keyed by language ISO code from shop_info (for example "fr", "en"). Only the languages and fields given are changed.',
            'additionalProperties' => ['type' => 'object', 'properties' => $props, 'additionalProperties' => false],
        ];
    }

    private static function id(string $what): array
    {
        return ['type' => 'integer', 'minimum' => 1, 'description' => $what];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function definitions(): array
    {
        $productId = self::id('Product id, taken from search_products or get_product. Never guessed.');
        $limit = ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'description' => 'How many results, 100 at most.'];
        $date = ['type' => 'string', 'format' => 'date', 'description' => 'YYYY-MM-DD.'];
        $themeName = ['type' => 'string', 'description' => 'Technical theme name, taken from list_themes.'];
        $themePath = ['type' => 'string', 'description' => 'Path inside the theme, for example templates/catalog/product.tpl or assets/css/custom.css.'];

        return [
            // ----- Shop -----
            'shop_info' => [
                'group' => 'shop', 'scope' => 'catalog.read', 'permission' => ['view', 'AdminProducts'],
                'handler' => ['catalog', 'shopInfo'],
                'description' => 'Facts about this shop: name, URL, PrestaShop version, active languages with their ISO codes, default currency, '
                    . 'tax rule groups, live theme, and how many products, categories and customers it has. Call it first in a conversation, '
                    . 'before writing any translated field, to know which language codes exist. It also gives the support contact of the module author.',
            ],

            // ----- Products -----
            'search_products' => [
                'group' => 'catalog', 'scope' => 'catalog.read', 'permission' => ['view', 'AdminProducts'],
                'handler' => ['catalog', 'searchProducts'],
                'description' => 'Finds products by name, reference, EAN or id, optionally in one category or by active status, newest first. '
                    . 'Use it to find the id of a product before reading or changing it. Returns name, reference, price, stock and default category. '
                    . 'For the full product with descriptions, use get_product.',
                'input' => ['properties' => [
                    'query' => ['type' => 'string', 'description' => 'Part of the name or reference, an exact EAN, or a product id. Leave out to list all.'],
                    'id_category' => self::id('Only products in this category (from list_categories).'),
                    'active' => ['type' => 'boolean', 'description' => 'true for active only, false for inactive only. Leave out for both.'],
                    'limit' => $limit,
                    'offset' => ['type' => 'integer', 'minimum' => 0, 'description' => 'For paging.'],
                ]],
            ],
            'get_product' => [
                'group' => 'catalog', 'scope' => 'catalog.read', 'permission' => ['view', 'AdminProducts'],
                'handler' => ['catalog', 'getProduct'],
                'description' => 'Reads one product in full: every translated field (name, short and long description, meta title, meta description, URL slug) in every language, '
                    . 'prices with and without tax, stock, categories, features, images and combinations count. Always read a product before rewriting its texts.',
                'input' => ['properties' => ['id_product' => $productId], 'required' => ['id_product']],
            ],
            'update_product' => [
                'group' => 'catalog', 'scope' => 'catalog.write', 'permission' => ['edit', 'AdminProducts'],
                'handler' => ['catalog', 'updateProduct'],
                'description' => 'Changes one product: price (tax excluded), wholesale price, reference, EAN, MPN, weight, active, on sale, visibility, condition, tax rule group, brand, '
                    . 'and translated texts (name, descriptions, meta title, meta description, URL slug). Only the fields given change. '
                    . 'Descriptions are HTML. Keep meta titles under 60 characters and meta descriptions under 160. Never invent prices or EANs: they come from the merchant. '
                    . 'Use set_stock for quantities and set_product_categories for categories. Returns every change as from/to.' . self::LOST_ANSWER,
                'input' => ['properties' => [
                    'id_product' => $productId,
                    'price' => ['type' => 'number', 'minimum' => 0, 'description' => 'Selling price tax excluded, in the default currency.'],
                    'wholesale_price' => ['type' => 'number', 'minimum' => 0],
                    'reference' => ['type' => 'string'],
                    'ean13' => ['type' => 'string'],
                    'mpn' => ['type' => 'string'],
                    'weight' => ['type' => 'number', 'minimum' => 0],
                    'active' => ['type' => 'boolean'],
                    'on_sale' => ['type' => 'boolean'],
                    'available_for_order' => ['type' => 'boolean'],
                    'show_price' => ['type' => 'boolean'],
                    'visibility' => ['type' => 'string', 'enum' => ['both', 'catalog', 'search', 'none']],
                    'condition' => ['type' => 'string', 'enum' => ['new', 'used', 'refurbished']],
                    'id_tax_rules_group' => ['type' => 'integer', 'minimum' => 0, 'description' => 'From shop_info.'],
                    'id_manufacturer' => ['type' => 'integer', 'minimum' => 0, 'description' => 'Brand id from list_brands, 0 for none.'],
                    'translations' => self::translations(['name', 'description_short', 'description', 'meta_title', 'meta_description', 'link_rewrite'], 'Translated texts.'),
                ], 'required' => ['id_product']],
            ],
            'create_product' => [
                'group' => 'catalog', 'scope' => 'catalog.write', 'permission' => ['add', 'AdminProducts'],
                'handler' => ['catalog', 'createProduct'],
                'description' => 'Creates a simple product, inactive by default so the merchant can check it before it is visible. A name in the default language and a default category are required. '
                    . 'Add combinations afterwards with create_combinations, images with add_product_image_from_url. Do not call it twice for the same product: search first.' . self::LOST_ANSWER,
                'input' => ['properties' => [
                    'translations' => self::translations(['name', 'description_short', 'description', 'meta_title', 'meta_description', 'link_rewrite'], 'Texts of the product.'),
                    'id_category_default' => self::id('Main category, from list_categories.'),
                    'category_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Other categories.'],
                    'price' => ['type' => 'number', 'minimum' => 0, 'description' => 'Price tax excluded.'],
                    'wholesale_price' => ['type' => 'number', 'minimum' => 0],
                    'reference' => ['type' => 'string'],
                    'ean13' => ['type' => 'string'],
                    'weight' => ['type' => 'number', 'minimum' => 0],
                    'quantity' => ['type' => 'integer', 'minimum' => 0],
                    'id_tax_rules_group' => ['type' => 'integer', 'minimum' => 0, 'description' => 'Defaults to the one most used in the catalogue.'],
                    'active' => ['type' => 'boolean', 'description' => 'Leave out to create it inactive (recommended).'],
                ], 'required' => ['translations', 'id_category_default']],
            ],
            'duplicate_product' => [
                'group' => 'catalog', 'scope' => 'catalog.write', 'permission' => ['add', 'AdminProducts'],
                'handler' => ['catalog', 'duplicateProduct'],
                'description' => 'Copies a product with its combinations, images, features, categories, related products, prices and promotions (like "Duplicate" in the back office), as an INACTIVE product with stock 0 and no EAN. '
                    . 'Give the copy its own name, slug and reference (translations, reference) so it does not look like the original.' . self::LOST_ANSWER,
                'input' => ['properties' => [
                    'id_product' => $productId,
                    'translations' => self::translations(['name', 'description_short', 'description', 'meta_title', 'meta_description', 'link_rewrite'], 'Texts of the copy (the original\'s are kept otherwise).'),
                    'reference' => ['type' => 'string'],
                    'copy_images' => ['type' => 'boolean', 'description' => 'Default true.'],
                ], 'required' => ['id_product']],
            ],
            'set_stock' => [
                'group' => 'catalog', 'scope' => 'catalog.write', 'permission' => ['edit', 'AdminProducts'],
                'handler' => ['catalog', 'setStock'],
                'description' => 'Sets the stock of a product or of one combination, either to an exact quantity or by adding/removing units (delta). '
                    . 'For a product with combinations, stock is per combination: pass id_product_attribute from list_combinations; the product total is recalculated. '
                    . 'Use delta for "I received 10" and quantity for "there are 25 left".' . self::LOST_ANSWER,
                'input' => ['properties' => [
                    'id_product' => $productId,
                    'id_product_attribute' => ['type' => 'integer', 'minimum' => 0, 'description' => 'Combination id, 0 or left out for a product without combinations.'],
                    'quantity' => ['type' => 'integer', 'minimum' => 0, 'description' => 'New stock. Do not combine with delta.'],
                    'delta' => ['type' => 'integer', 'description' => 'Units to add (positive) or remove (negative). Do not combine with quantity.'],
                ], 'required' => ['id_product']],
            ],
            'list_categories' => [
                'group' => 'catalog', 'scope' => 'catalog.read', 'permission' => ['view', 'AdminCategories'],
                'handler' => ['catalog', 'listCategories'],
                'description' => 'Lists categories in tree order with id, parent, depth, name, active state and number of products. Use it to find category ids. '
                    . 'Filter by parent or by part of the name.',
                'input' => ['properties' => [
                    'id_parent' => self::id('Only direct children of this category.'),
                    'search' => ['type' => 'string', 'description' => 'Part of the name.'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                    'offset' => ['type' => 'integer', 'minimum' => 0],
                ]],
            ],
            'get_category' => [
                'group' => 'catalog', 'scope' => 'catalog.read', 'permission' => ['view', 'AdminCategories'],
                'handler' => ['catalog', 'getCategory'],
                'description' => 'Reads one category with its translated name, descriptions, meta title, meta description and slug in every language. Read before rewriting.',
                'input' => ['properties' => ['id_category' => self::id('Category id from list_categories.')], 'required' => ['id_category']],
            ],
            'update_category' => [
                'group' => 'catalog', 'scope' => 'catalog.write', 'permission' => ['edit', 'AdminCategories'],
                'handler' => ['catalog', 'updateCategory'],
                'description' => 'Changes a category: active state, parent (to move it with its subcategories), position among its sister categories (the order of the menu and of the category pages), '
                    . 'and translated name, description, additional description (PrestaShop 8+), meta title, meta description, slug. '
                    . 'Only what is given changes. Changing a slug changes the address of the page: say so to the merchant.' . self::LOST_ANSWER,
                'input' => ['properties' => [
                    'id_category' => self::id('Category id from list_categories.'),
                    'active' => ['type' => 'boolean'],
                    'id_parent' => self::id('New parent category, to move the category (and its subcategories) elsewhere in the tree.'),
                    'position' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Place among the categories of the same parent, from 1 (first). Moving to another parent without a position puts it last.'],
                    'translations' => self::translations(['name', 'description', 'additional_description', 'meta_title', 'meta_description', 'link_rewrite'], 'Translated texts.'),
                ], 'required' => ['id_category']],
            ],
            'create_category' => [
                'group' => 'catalog', 'scope' => 'catalog.write', 'permission' => ['add', 'AdminCategories'],
                'handler' => ['catalog', 'createCategory'],
                'description' => 'Creates a category under a parent (the home category by default, which makes it a top category), visible to the same customer groups as its parent. '
                    . 'Check list_categories first so you do not create a category that already exists. A name in the default language is required; other languages take it unless given. '
                    . 'Write a real description and meta texts when the merchant wants the page to rank. It is created INACTIVE unless active is true: inactive categories are hidden everywhere, menu included. '
                    . 'Under a category that is in the main menu, it appears in the menu by itself once active (see get_menu). Then put products in it with set_product_categories.' . self::LOST_ANSWER,
                'input' => ['properties' => [
                    'id_parent' => self::id('Parent category from list_categories. Default: the home category.'),
                    'translations' => self::translations(['name', 'description', 'additional_description', 'meta_title', 'meta_description', 'link_rewrite'], 'Translated texts.'),
                    'position' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Place among the categories of the same parent, from 1 (first). Default: last.'],
                    'active' => ['type' => 'boolean', 'description' => 'Leave out to create it inactive, then activate it with update_category once the merchant approves.'],
                    'allow_same_name' => ['type' => 'boolean', 'description' => 'Only when the merchant explicitly wants a second category with the same name under the same parent.'],
                ], 'required' => ['translations']],
            ],
            'set_product_categories' => [
                'group' => 'catalog', 'scope' => 'catalog.write', 'permission' => ['edit', 'AdminProducts'],
                'handler' => ['catalog', 'setProductCategories'],
                'description' => 'Replaces the categories of a product with the given list and sets its main category. Categories not in the list are removed from the product.' . self::LOST_ANSWER,
                'input' => ['properties' => [
                    'id_product' => $productId,
                    'category_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'minItems' => 1, 'description' => 'Every category the product must be in.'],
                    'id_category_default' => self::id('Main category, must be in category_ids.'),
                ], 'required' => ['id_product', 'category_ids']],
            ],

            // ----- Main menu -----
            'get_menu' => [
                'group' => 'menu', 'scope' => 'catalog.read', 'permission' => ['view', 'AdminCategories'],
                'handler' => ['menu', 'getMenu'],
                'description' => 'Reads the main menu of the shop (native PrestaShop module "Main menu", ps_mainmenu, used by Classic, Hummingbird and most themes): its entries in order '
                    . '(categories with the subcategories they show, CMS pages, CMS categories, products, brands, suppliers, custom links), the custom links available, '
                    . 'and any other active menu module. Read it before set_menu. If the shop uses a third-party mega menu, it is listed but cannot be changed from here.',
                'input' => ['properties' => [
                    'depth' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 4, 'description' => 'Levels of subcategories shown under each category entry. Default 2.'],
                ]],
            ],
            'set_menu' => [
                'group' => 'menu', 'scope' => 'catalog.write', 'permission' => ['edit', 'AdminModulesSf'],
                'handler' => ['menu', 'setMenu'],
                'description' => 'Replaces the entries of the main menu (ps_mainmenu) with the given ordered list: to add, remove or reorder an entry, call get_menu and send back the WHOLE list with the change. '
                    . 'Entry types: category (shows the category and its active subcategories, ordered by category position: reorder them with update_category position), cms_page, cms_category, product, brand, supplier, '
                    . 'all_brands, all_suppliers, shop, and link (an existing custom link by id, or a new one with a label and a url per language; urls start with https:// or / ). '
                    . 'A category to add as its own entry must exist (create_category). Show the merchant the new menu before calling. The answer gives the previous list to undo.' . self::LOST_ANSWER,
                'input' => ['properties' => [
                    'items' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 40, 'description' => 'Every menu entry, in display order.', 'items' => [
                        'type' => 'object', 'additionalProperties' => false,
                        'properties' => [
                            'type' => ['type' => 'string', 'enum' => PiskeeConnectorMenu::typeNames()],
                            'id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Id of the category, page, product, brand, supplier, shop or existing link. Leave out for a new link, all_brands and all_suppliers.'],
                            'translations' => ['type' => 'object', 'description' => 'Links only: label and url keyed by language ISO code. Missing languages take the default language.', 'additionalProperties' => [
                                'type' => 'object', 'additionalProperties' => false, 'properties' => ['label' => ['type' => 'string'], 'url' => ['type' => 'string']],
                            ]],
                            'new_window' => ['type' => 'boolean', 'description' => 'Links only: open in a new tab.'],
                        ],
                        'required' => ['type'],
                    ]],
                ], 'required' => ['items']],
            ],

            'inspect_menu_module' => [
                'group' => 'menu', 'scope' => 'theme.read', 'permission' => ['view', 'AdminModulesSf'],
                'handler' => ['menu', 'inspectMenuModule'],
                'description' => 'Reads, without changing anything, a third-party menu or mega menu module listed by get_menu (other_menu_modules): its tables (entries, columns, blocks as the module stores them) and settings. '
                    . 'Use it to describe the current menu to the merchant or to rebuild it with the Piskee Pro mega menu. Never claim you changed such a module: it is edited in its own configuration page.',
                'input' => ['properties' => [
                    'module' => ['type' => 'string', 'description' => 'Technical module name, from get_menu other_menu_modules.'],
                    'rows' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'description' => 'Rows read per table (default 30).'],
                ], 'required' => ['module']],
            ],

            // ----- Shop appearance (live shop settings, no theme code) -----
            'get_appearance' => [
                'group' => 'appearance', 'scope' => 'theme.read', 'permission' => ['view', 'AdminModulesSf'],
                'handler' => ['look', 'getAppearance'],
                'description' => 'Overview of what can be configured on the LIVE shop without touching the theme code: the blocks of the home page in order, '
                    . 'the native front modules (image slider, banner, custom text, featured / best sellers / new / on sale products, social links) with where they are shown and their settings. '
                    . 'Call it before changing the look of the shop. Theme code (templates, CSS) is changed only in draft child themes, with the theme tools.',
            ],
            'list_slides' => [
                'group' => 'appearance', 'scope' => 'theme.read', 'permission' => ['view', 'AdminModulesSf'],
                'handler' => ['look', 'listSlides'],
                'description' => 'Lists the slides of the home page image slider (native module ps_imageslider) in order: id, position, active, and per language title, legend, description, link and image. Also the slider settings.',
            ],
            'save_slide' => [
                'group' => 'appearance', 'scope' => 'appearance.write', 'permission' => ['edit', 'AdminModulesSf'],
                'handler' => ['look', 'saveSlide'],
                'description' => 'Adds a slide to the home page slider (without id_slide) or changes one (with id_slide from list_slides). The image comes from a public address (JPEG, PNG, GIF, WebP), '
                    . 'one for every language or one per language; it is re-encoded and stored in the slider module. Use a wide picture of the size of the other slides. '
                    . 'Texts and link per language; only what is given changes. A new slide is INACTIVE unless active is true. To take a slide off the home page, set active false. '
                    . 'Show the merchant the slide (texts, image, link) before calling. The answer gives the previous values.' . self::LOST_ANSWER,
                'input' => ['properties' => [
                    'id_slide' => self::id('Slide to change, from list_slides. Leave out to add a slide.'),
                    'image_url' => ['type' => 'string', 'description' => 'Public https address of the picture, for every language.'],
                    'translations' => self::translations(['title', 'legend', 'description', 'url', 'image_url'], 'Title, legend (short line over the picture), description (HTML), url (link of the slide: https://... or /page) and image_url for one language only.'),
                    'active' => ['type' => 'boolean'],
                    'position' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Place in the slider, from 1 (first).'],
                ]],
            ],
            'configure_slider' => [
                'group' => 'appearance', 'scope' => 'appearance.write', 'permission' => ['edit', 'AdminModulesSf'],
                'handler' => ['look', 'configureSlider'],
                'description' => 'Sets the home page slider: how long each slide stays, pause when the mouse is over it, and whether it loops. Only what is given changes.',
                'input' => ['properties' => [
                    'speed_ms' => ['type' => 'integer', 'minimum' => 1000, 'maximum' => 30000, 'description' => 'Time each slide stays, in milliseconds (5000 = 5 s).'],
                    'pause_on_hover' => ['type' => 'boolean'],
                    'loop' => ['type' => 'boolean', 'description' => 'Go back to the first slide after the last one.'],
                ]],
            ],
            'set_banner' => [
                'group' => 'appearance', 'scope' => 'appearance.write', 'permission' => ['edit', 'AdminModulesSf'],
                'handler' => ['look', 'setBanner'],
                'description' => 'Changes the banner of the native module ps_banner (shown where get_appearance says, often the home page): picture from a public address, link and description per language. '
                    . 'Only what is given changes; the previous picture file is kept and given back to undo.' . self::LOST_ANSWER,
                'input' => ['properties' => [
                    'image_url' => ['type' => 'string', 'description' => 'Public https address of the picture, for every language.'],
                    'translations' => self::translations(['link', 'description', 'image_url'], 'Link (https://... or /page), description (alternative text of the picture) and image_url for one language only.'),
                ]],
            ],
            'set_custom_text' => [
                'group' => 'appearance', 'scope' => 'appearance.write', 'permission' => ['edit', 'AdminModulesSf'],
                'handler' => ['look', 'setCustomText'],
                'description' => 'Replaces the HTML of the custom text block (native module ps_customtext, often the reassurance or welcome text of the home page). '
                    . 'Simple HTML only (headings, paragraphs, lists, links, images); scripts are refused. Read the current text with get_appearance and show the merchant the new one first.' . self::LOST_ANSWER,
                'input' => ['properties' => [
                    'translations' => self::translations(['html'], 'The HTML of the block.'),
                ], 'required' => ['translations']],
            ],
            'configure_home_products' => [
                'group' => 'appearance', 'scope' => 'appearance.write', 'permission' => ['edit', 'AdminModulesSf'],
                'handler' => ['look', 'configureHomeProducts'],
                'description' => 'Sets the product blocks of the home page (native modules): how many featured products and from which category (the products of that category are shown), random order, '
                    . 'how many best sellers, new products (and for how many days a product counts as new), products on sale. Only what is given changes. To show or hide a block, use set_home_blocks.',
                'input' => ['properties' => [
                    'featured_count' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 48],
                    'featured_category' => self::id('Category whose products are featured (list_categories).'),
                    'featured_random' => ['type' => 'boolean'],
                    'bestsellers_count' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 48],
                    'new_products_count' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 48],
                    'new_products_days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 365],
                    'specials_count' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 48],
                ]],
            ],
            'set_social_links' => [
                'group' => 'appearance', 'scope' => 'appearance.write', 'permission' => ['edit', 'AdminModulesSf'],
                'handler' => ['look', 'setSocialLinks'],
                'description' => 'Sets the social network links shown by the native module ps_socialfollow (usually in the footer). Give only the networks to change; an empty address hides that network. Never invent an address: ask the merchant.',
                'input' => ['properties' => [
                    'links' => ['type' => 'object', 'description' => 'Network => https address.', 'additionalProperties' => false, 'properties' => array_fill_keys(PiskeeConnectorAppearance::networkNames(), ['type' => 'string'])],
                ], 'required' => ['links']],
            ],
            'set_home_blocks' => [
                'group' => 'appearance', 'scope' => 'appearance.write', 'permission' => ['edit', 'AdminModulesSf'],
                'handler' => ['look', 'setHomeBlocks'],
                'description' => 'Chooses which blocks (modules) the home page shows and in which order: give the WHOLE ordered list of module names (from get_appearance home_page_blocks). '
                    . 'A module left out is taken off the home page but keeps its settings; an installed module can be added if it can display on the home page. Only the displayHome area changes. '
                    . 'Show the merchant the new order first. The answer gives the previous list to undo.' . self::LOST_ANSWER,
                'input' => ['properties' => [
                    'blocks' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 30, 'items' => ['type' => 'string'], 'description' => 'Module names, in display order.'],
                ], 'required' => ['blocks']],
            ],

            // ----- Module positions (zones of the theme) -----
            'list_module_hooks' => [
                'group' => 'appearance', 'scope' => 'theme.read', 'permission' => ['view', 'AdminModulesPositions'],
                'handler' => ['hooks', 'listModuleHooks'],
                'description' => 'Where modules show on the shop (back office Design > Positions). Without arguments: the zones (display hooks) that show something. '
                    . 'With hook: the modules of that zone in order, with the pages they are hidden on. With module: the zones it shows in, and the zones it could show in. '
                    . 'Use it when a block disappeared, shows twice or in the wrong place, typically after a theme change.',
                'input' => ['properties' => [
                    'hook' => ['type' => 'string', 'description' => 'Zone name, such as displayHome, displayTop, displayFooter.'],
                    'module' => ['type' => 'string', 'description' => 'Technical module name, such as ps_customtext.'],
                ]],
            ],
            'set_module_hook' => [
                'group' => 'appearance', 'scope' => 'appearance.write', 'permission' => ['edit', 'AdminModulesPositions'],
                'handler' => ['hooks', 'setModuleHook'],
                'description' => 'Attaches a module to a zone of the shop front (display hook), detaches it, or moves it in the zone; can also set the pages it is hidden on. '
                    . 'No theme code changes. Read the zone with list_module_hooks first and show the merchant the change. The answer gives the state before, to undo.' . self::LOST_ANSWER,
                'input' => ['properties' => [
                    'action' => ['type' => 'string', 'enum' => ['attach', 'detach', 'move']],
                    'module' => ['type' => 'string'],
                    'hook' => ['type' => 'string', 'description' => 'Zone, starting with "display".'],
                    'position' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Rank in the zone, from 1 (first). attach: default last.'],
                    'hidden_on' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Pages (controllers) where the module is hidden in this zone: index, category, product, cms, cart, order... Empty list: shown everywhere.'],
                ], 'required' => ['action', 'module', 'hook']],
            ],

            // ----- Vouchers, promotions in bulk, catalogue price rules -----
            'list_vouchers' => [
                'group' => 'daily', 'scope' => 'sales.read', 'permission' => ['view', 'AdminCartRules'],
                'handler' => ['sales', 'listVouchers'],
                'description' => 'Lists the vouchers (cart rules): code, discount, dates, uses left and times used, customer restriction. Filter by part of the code or name, or by status.',
                'input' => ['properties' => [
                    'search' => ['type' => 'string'],
                    'status' => ['type' => 'string', 'enum' => ['all', 'active', 'expired']],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                ]],
            ],
            'delete_voucher' => [
                'group' => 'daily', 'scope' => 'marketing.write', 'permission' => ['delete', 'AdminCartRules'],
                'handler' => ['sales', 'deleteVoucher'],
                'description' => 'Deletes a voucher (from list_vouchers). A voucher already used by orders is deactivated instead, so those orders keep their discount line; deactivate_only deactivates in any case. '
                    . 'Confirm the code with the merchant first.' . self::LOST_ANSWER,
                'input' => ['properties' => [
                    'id_cart_rule' => self::id('Voucher id, from list_vouchers.'),
                    'deactivate_only' => ['type' => 'boolean'],
                ], 'required' => ['id_cart_rule']],
            ],
            'bulk_create_promotion' => [
                'group' => 'enrichment', 'scope' => 'catalog.write', 'permission' => ['edit', 'AdminProducts'], 'self_metered' => true,
                'handler' => ['sales', 'bulkCreatePromotion'],
                'description' => 'Puts the same promotion (percentage or amount, optional dates) on many products at once: a list, every product of a category (subcategories included by default), of a brand, '
                    . 'or the whole catalogue (300 products at most; beyond, use create_price_rule). Products that already have a promotion for the period are left as they are and listed, unless replace_existing. '
                    . 'The discount always comes from the merchant. On the free plan each product counts as one change.' . self::LOST_ANSWER,
                'input' => ['properties' => [
                    'product_ids' => ['type' => 'array', 'maxItems' => 300, 'items' => ['type' => 'integer', 'minimum' => 1]],
                    'id_category' => self::id('Every product of this category.'),
                    'include_subcategories' => ['type' => 'boolean', 'description' => 'With id_category. Default true.'],
                    'id_manufacturer' => self::id('Every product of this brand.'),
                    'all_products' => ['type' => 'boolean'],
                    'type' => ['type' => 'string', 'enum' => ['percentage', 'amount']],
                    'value' => ['type' => 'number', 'exclusiveMinimum' => 0, 'description' => '20 for 20 %, or an amount in the default currency.'],
                    'tax_included' => ['type' => 'boolean'],
                    'date_from' => $date,
                    'date_to' => $date,
                    'id_group' => ['type' => 'integer', 'minimum' => 0, 'description' => 'Customer group only, 0 for everyone.'],
                    'replace_existing' => ['type' => 'boolean'],
                ], 'required' => ['type', 'value']],
            ],
            'list_price_rules' => [
                'group' => 'enrichment', 'scope' => 'catalog.read', 'permission' => ['view', 'AdminSpecificPriceRule'],
                'handler' => ['sales', 'listPriceRules'],
                'description' => 'Lists the catalogue price rules (Catalog > Discounts > Catalog price rules): reduction, dates, group, country, currency and the categories or brands they apply to.',
            ],
            'create_price_rule' => [
                'group' => 'enrichment', 'scope' => 'catalog.write', 'permission' => ['add', 'AdminSpecificPriceRule'],
                'handler' => ['sales', 'createPriceRule'],
                'description' => 'Creates a catalogue price rule: one reduction (percentage or amount) on the whole catalogue, or on products of some categories or brands, optionally for one customer group, '
                    . 'country or currency, from a quantity, between dates. Best for sales and for prices per group (B2B). Shown as a promotion on every matching product. '
                    . 'The reduction always comes from the merchant.' . self::LOST_ANSWER,
                'input' => ['properties' => [
                    'name' => ['type' => 'string', 'description' => 'Internal name, such as "Winter sales 2026".'],
                    'type' => ['type' => 'string', 'enum' => ['percentage', 'amount']],
                    'value' => ['type' => 'number', 'exclusiveMinimum' => 0],
                    'tax_included' => ['type' => 'boolean', 'description' => 'For amounts. Default true.'],
                    'category_ids' => ['type' => 'array', 'items' => ['type' => 'integer', 'minimum' => 1], 'description' => 'Products of any of these categories. None: whole catalogue (or brands below).'],
                    'manufacturer_ids' => ['type' => 'array', 'items' => ['type' => 'integer', 'minimum' => 1]],
                    'id_group' => ['type' => 'integer', 'minimum' => 0],
                    'id_country' => ['type' => 'integer', 'minimum' => 0],
                    'id_currency' => ['type' => 'integer', 'minimum' => 0],
                    'from_quantity' => ['type' => 'integer', 'minimum' => 1],
                    'date_from' => $date,
                    'date_to' => $date,
                ], 'required' => ['name', 'type', 'value']],
            ],
            'delete_price_rule' => [
                'group' => 'enrichment', 'scope' => 'catalog.write', 'permission' => ['delete', 'AdminSpecificPriceRule'],
                'handler' => ['sales', 'deletePriceRule'],
                'description' => 'Deletes a catalogue price rule (from list_price_rules): the products get their normal price back. Confirm with the merchant first.',
                'input' => ['properties' => ['id_price_rule' => self::id('From list_price_rules.')], 'required' => ['id_price_rule']],
            ],
            'bulk_set_product_feature' => [
                'group' => 'enrichment', 'scope' => 'catalog.write', 'permission' => ['edit', 'AdminProducts'], 'self_metered' => true,
                'handler' => ['merch', 'bulkSetProductFeature'],
                'description' => 'Sets one feature to the same value on many products: a list, a category (subcategories included by default), a brand or the whole catalogue, 300 at most. '
                    . 'The value is one predefined value (id_feature_value, or shared_value found or created by its text), so customers can filter on it. On the free plan each product counts as one change.' . self::LOST_ANSWER,
                'input' => ['properties' => [
                    'id_feature' => self::id('From list_features.'),
                    'id_feature_value' => self::id('A predefined value from list_features.'),
                    'shared_value' => self::translations(['value'], 'Text of the value per language: reused if it exists, created once otherwise.'),
                    'product_ids' => ['type' => 'array', 'maxItems' => 300, 'items' => ['type' => 'integer', 'minimum' => 1]],
                    'id_category' => self::id('Every product of this category.'),
                    'include_subcategories' => ['type' => 'boolean'],
                    'id_manufacturer' => self::id('Every product of this brand.'),
                    'all_products' => ['type' => 'boolean'],
                ], 'required' => ['id_feature']],
            ],

            // ----- Customers and groups -----
            'list_customer_groups' => [
                'group' => 'customers', 'scope' => 'customers.read', 'permission' => ['view', 'AdminGroups'],
                'handler' => ['customers', 'listGroups'],
                'description' => 'Lists the customer groups: discount, prices shown tax included or excluded, whether prices are shown, number of customers.',
            ],
            'save_customer_group' => [
                'group' => 'customers', 'scope' => 'customers.write', 'permission' => ['edit', 'AdminGroups'],
                'handler' => ['customers', 'saveGroup'],
                'description' => 'Creates a customer group (such as "Professionals") or changes one (id_group): name, discount on the whole catalogue for its members, prices shown tax excluded, prices shown or hidden. '
                    . 'For prices on some products only, use create_promotion or create_price_rule with id_group.' . self::LOST_ANSWER,
                'input' => ['properties' => [
                    'id_group' => self::id('Group to change. Leave out to create one.'),
                    'translations' => self::translations(['name'], 'Group name (32 characters at most).'),
                    'discount_percent' => ['type' => 'number', 'minimum' => 0, 'maximum' => 99],
                    'prices_tax_excluded' => ['type' => 'boolean'],
                    'show_prices' => ['type' => 'boolean'],
                ]],
            ],
            'create_customer' => [
                'group' => 'customers', 'scope' => 'customers.write', 'permission' => ['add', 'AdminCustomers'],
                'handler' => ['customers', 'createCustomer'],
                'description' => 'Creates a customer account, typically a professional one, in the groups given. No e-mail is sent and no password is set: the customer gets in with "Forgot your password?". '
                    . 'Newsletter and partner offers stay off. Only with details given by the merchant.' . self::LOST_ANSWER,
                'input' => ['properties' => [
                    'email' => ['type' => 'string'],
                    'firstname' => ['type' => 'string'],
                    'lastname' => ['type' => 'string'],
                    'company' => ['type' => 'string'],
                    'siret' => ['type' => 'string'],
                    'group_ids' => ['type' => 'array', 'items' => ['type' => 'integer', 'minimum' => 1]],
                    'id_default_group' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Group whose prices the customer sees.'],
                ], 'required' => ['email', 'firstname', 'lastname']],
            ],
            'update_customer' => [
                'group' => 'customers', 'scope' => 'customers.write', 'permission' => ['edit', 'AdminCustomers'],
                'handler' => ['customers', 'updateCustomer'],
                'description' => 'Changes a customer account: name, company, SIRET, e-mail, active, groups (the whole list) and default group. Never the password, newsletter or partner consent. Only what is given changes.' . self::LOST_ANSWER,
                'input' => ['properties' => [
                    'id_customer' => self::id('From search_customers.'),
                    'firstname' => ['type' => 'string'],
                    'lastname' => ['type' => 'string'],
                    'company' => ['type' => 'string'],
                    'siret' => ['type' => 'string'],
                    'email' => ['type' => 'string'],
                    'active' => ['type' => 'boolean'],
                    'group_ids' => ['type' => 'array', 'items' => ['type' => 'integer', 'minimum' => 1]],
                    'id_default_group' => ['type' => 'integer', 'minimum' => 1],
                ], 'required' => ['id_customer']],
            ],

            // ----- Shop maintenance -----
            'clear_cache' => [
                'group' => 'shop', 'scope' => 'appearance.write', 'permission' => ['edit', 'AdminPerformance'],
                'handler' => ['system', 'clearCache'],
                'description' => 'Empties PrestaShop\'s caches (templates, CSS and JS, data), like the "Clear cache" button. Use it when a change does not show on the shop.',
            ],
            'set_maintenance' => [
                'group' => 'shop', 'scope' => 'appearance.write', 'permission' => ['edit', 'AdminMaintenance'],
                'handler' => ['system', 'setMaintenance'],
                'description' => 'Puts the shop in maintenance mode (visitors see the maintenance page, except from the allowed IP addresses) or opens it again; can set the allowed IPs and the message. '
                    . 'Without shop_open, reads the current state. Only on the merchant\'s explicit request.',
                'input' => ['properties' => [
                    'shop_open' => ['type' => 'boolean'],
                    'allowed_ips' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'IP addresses that still see the shop (the whole list).'],
                    'message' => self::translations(['text'], 'Maintenance message (simple HTML).'),
                ]],
            ],

            // ----- Orders in bulk and documents -----
            'bulk_set_order_status' => [
                'group' => 'orders', 'scope' => 'orders.write', 'permission' => ['edit', 'AdminOrders'], 'self_metered' => true,
                'handler' => ['orders', 'bulkSetOrderStatus'],
                'description' => 'Gives the same status to up to 50 orders, each exactly like set_order_status (history, stock, invoice, customer e-mail). '
                    . 'Unpaid orders are not marked paid unless confirm_paid. Say which e-mail customers receive before asking for approval. On the free plan each order counts as one change.' . self::LOST_ANSWER,
                'input' => ['properties' => [
                    'order_ids' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 50, 'items' => ['type' => 'integer', 'minimum' => 1]],
                    'id_order_state' => self::id('From list_order_statuses.'),
                    'confirm_paid' => ['type' => 'boolean', 'description' => 'true only when the merchant confirmed that unpaid orders may be marked as paid.'],
                ], 'required' => ['order_ids', 'id_order_state']],
            ],
            'get_order_document' => [
                'group' => 'orders', 'scope' => 'orders.read', 'permission' => ['view', 'AdminOrders'],
                'handler' => ['orders', 'getOrderDocument'],
                'description' => 'Makes the PDF invoice, delivery slip or credit slip of an order, as PrestaShop prints it, and gives a download link valid 30 minutes. '
                    . 'Give the link to the merchant only; never publish it.',
                'input' => ['properties' => [
                    'id_order' => self::id('Order id.'),
                    'document' => ['type' => 'string', 'enum' => ['invoice', 'delivery_slip', 'credit_slip']],
                    'id_order_slip' => self::id('credit_slip: which one (default: the latest).'),
                ], 'required' => ['id_order', 'document']],
            ],

            // ----- Variants -----
            'list_combinations' => [
                'group' => 'variants', 'scope' => 'catalog.read', 'permission' => ['view', 'AdminProducts'],
                'handler' => ['catalog', 'listCombinations'],
                'description' => 'Lists the combinations (variants) of a product: attributes, reference, EAN, price impact, weight impact, default flag and stock of each.',
                'input' => ['properties' => ['id_product' => $productId], 'required' => ['id_product']],
            ],
            'list_attribute_groups' => [
                'group' => 'variants', 'scope' => 'catalog.read', 'permission' => ['view', 'AdminAttributesGroups'],
                'handler' => ['variants', 'listAttributeGroups'],
                'description' => 'Lists attribute groups (Size, Colour...) with every value and its id. Call it before create_combinations to get attribute ids.',
            ],
            'create_attribute_group' => [
                'group' => 'variants', 'scope' => 'catalog.write', 'permission' => ['add', 'AdminAttributesGroups'],
                'handler' => ['variants', 'createAttributeGroup'],
                'description' => 'Creates an attribute group such as "Size" or "Colour". Check list_attribute_groups first: do not create a group that already exists.' . self::LOST_ANSWER,
                'input' => ['properties' => [
                    'translations' => self::translations(['name', 'public_name'], 'Internal name and name shown to customers.'),
                    'type' => ['type' => 'string', 'enum' => ['select', 'radio', 'color'], 'description' => 'How customers pick a value. Default select.'],
                ], 'required' => ['translations']],
            ],
            'create_attribute_value' => [
                'group' => 'variants', 'scope' => 'catalog.write', 'permission' => ['add', 'AdminAttributesGroups'],
                'handler' => ['variants', 'createAttributeValue'],
                'description' => 'Adds a value (such as "XL" or "Red") to an attribute group. If the value already exists, its id is returned instead of a duplicate.',
                'input' => ['properties' => [
                    'id_attribute_group' => self::id('From list_attribute_groups.'),
                    'translations' => self::translations(['name'], 'Value name.'),
                    'color' => ['type' => 'string', 'description' => 'Hex colour such as #FF0000, for colour groups only.'],
                ], 'required' => ['id_attribute_group', 'translations']],
            ],
            'create_combinations' => [
                'group' => 'variants', 'scope' => 'catalog.write', 'permission' => ['add', 'AdminProducts'],
                'handler' => ['variants', 'createCombinations'],
                'description' => 'Creates combinations (variants) for a product, up to 100 at once. Each entry takes one attribute value id per group (for example a size id and a colour id), '
                    . 'with optional price impact, weight impact, reference, EAN and stock. Existing combinations are skipped, never duplicated. '
                    . 'Get attribute ids from list_attribute_groups.' . self::LOST_ANSWER,
                'input' => ['properties' => [
                    'id_product' => $productId,
                    'combinations' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 100, 'items' => [
                        'type' => 'object',
                        'properties' => [
                            'attribute_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'minItems' => 1],
                            'price_impact_tax_excl' => ['type' => 'number'],
                            'weight_impact' => ['type' => 'number'],
                            'reference' => ['type' => 'string'],
                            'ean13' => ['type' => 'string'],
                            'quantity' => ['type' => 'integer', 'minimum' => 0],
                        ],
                        'required' => ['attribute_ids'],
                        'additionalProperties' => false,
                    ]],
                ], 'required' => ['id_product', 'combinations']],
            ],
            'update_combination' => [
                'group' => 'variants', 'scope' => 'catalog.write', 'permission' => ['edit', 'AdminProducts'],
                'handler' => ['catalog', 'updateCombination'],
                'description' => 'Changes one combination: price impact, weight impact, reference, EAN, or makes it the default one. Stock goes through set_stock.' . self::LOST_ANSWER,
                'input' => ['properties' => [
                    'id_product_attribute' => self::id('Combination id from list_combinations.'),
                    'price_impact_tax_excl' => ['type' => 'number'],
                    'weight_impact' => ['type' => 'number'],
                    'reference' => ['type' => 'string'],
                    'ean13' => ['type' => 'string'],
                    'default' => ['type' => 'boolean', 'description' => 'true to make it the default combination.'],
                ], 'required' => ['id_product_attribute']],
            ],

            // ----- Images -----
            'list_product_images' => [
                'group' => 'images', 'scope' => 'catalog.read', 'permission' => ['view', 'AdminProducts'],
                'handler' => ['images', 'listProductImages'],
                'description' => 'Lists the images of a product with cover flag, alt text in every language, original size in pixels and bytes, and URL.',
                'input' => ['properties' => ['id_product' => $productId], 'required' => ['id_product']],
            ],
            'add_product_image_from_url' => [
                'group' => 'images', 'scope' => 'catalog.write', 'permission' => ['add', 'AdminProducts'],
                'handler' => ['images', 'addProductImageFromUrl'],
                'description' => 'Downloads a public JPEG, PNG, GIF or WebP image (15 MB max) and adds it to a product, with alt text, and builds every thumbnail. '
                    . 'Only use URLs the merchant gave or that clearly belong to them.' . self::LOST_ANSWER,
                'input' => ['properties' => [
                    'id_product' => $productId,
                    'url' => ['type' => 'string', 'description' => 'Public http(s) address of the image.'],
                    'alt_text' => self::translations(['value'], 'Alt text per language, 128 characters max.'),
                    'cover' => ['type' => 'boolean', 'description' => 'Make it the main image.'],
                ], 'required' => ['id_product', 'url']],
            ],
            'update_product_image' => [
                'group' => 'images', 'scope' => 'catalog.write', 'permission' => ['edit', 'AdminProducts'],
                'handler' => ['images', 'updateProductImage'],
                'description' => 'Sets the alt text of an image (per language, 128 characters max, describing what is visible, not keyword stuffing) and/or makes it the cover.',
                'input' => ['properties' => [
                    'id_image' => self::id('From list_product_images.'),
                    'alt_text' => self::translations(['value'], 'Alt text per language.'),
                    'cover' => ['type' => 'boolean'],
                ], 'required' => ['id_image']],
            ],
            'optimize_product_images' => [
                'group' => 'images', 'scope' => 'catalog.write', 'permission' => ['edit', 'AdminProducts'],
                'handler' => ['images', 'optimizeProductImages'],
                'description' => 'Reshapes the original images of a product, then rebuilds every thumbnail: downsizes originals larger than max_side pixels (faster pages) '
                    . 'and/or centres them on a square canvas with a background colour (uniform listings). With no option, only rebuilds thumbnails. '
                    . 'The originals are overwritten: say so to the merchant before using square.',
                'input' => ['properties' => [
                    'id_product' => $productId,
                    'id_image' => self::id('Only this image. Leave out for all images of the product.'),
                    'max_side' => ['type' => 'integer', 'minimum' => 300, 'maximum' => 6000, 'description' => 'Longest side in pixels, for example 2000.'],
                    'square' => ['type' => 'boolean', 'description' => 'Centre on a square canvas.'],
                    'background' => ['type' => 'string', 'description' => 'Hex background for square, default ffffff.'],
                ], 'required' => ['id_product']],
            ],

            // ----- Enrichment -----
            'list_features' => [
                'group' => 'enrichment', 'scope' => 'catalog.read', 'permission' => ['view', 'AdminFeatures'],
                'handler' => ['merch', 'listFeatures'],
                'description' => 'Lists product features (Material, Dimensions...) and their predefined values with ids.',
            ],
            'create_feature' => [
                'group' => 'enrichment', 'scope' => 'catalog.write', 'permission' => ['add', 'AdminFeatures'],
                'handler' => ['merch', 'createFeature'],
                'description' => 'Creates a product feature. If one with the same name exists, its id is returned instead.',
                'input' => ['properties' => ['translations' => self::translations(['name'], 'Feature name.')], 'required' => ['translations']],
            ],
            'set_product_feature' => [
                'group' => 'enrichment', 'scope' => 'catalog.write', 'permission' => ['edit', 'AdminProducts'],
                'handler' => ['merch', 'setProductFeature'],
                'description' => 'Sets one feature on a product, to a predefined value (id_feature_value), to a predefined value found or created by its text (shared_value: use it when the same text goes on several products, so it is one value customers can filter on), '
                    . 'or to a free text for this product only (custom_value). Replaces the previous value of that feature.',
                'input' => ['properties' => [
                    'id_product' => $productId,
                    'id_feature' => self::id('From list_features.'),
                    'id_feature_value' => self::id('A predefined value from list_features.'),
                    'shared_value' => self::translations(['value'], 'Text of a predefined value, per language: reused if it exists for this feature, created otherwise.'),
                    'custom_value' => self::translations(['value'], 'Free text per language, for this product only.'),
                ], 'required' => ['id_product', 'id_feature']],
            ],
            'list_brands' => [
                'group' => 'enrichment', 'scope' => 'catalog.read', 'permission' => ['view', 'AdminManufacturers'],
                'handler' => ['merch', 'listBrands'],
                'description' => 'Lists brands (manufacturers) with id and product count, to set id_manufacturer with update_product.',
            ],
            'get_cross_selling' => [
                'group' => 'crossselling', 'scope' => 'catalog.read', 'permission' => ['view', 'AdminProducts'],
                'handler' => ['cross', 'getCrossSelling'],
                'description' => 'Cross-selling of one product: its related products (accessories shown on the product page), the products customers actually bought with it in valid orders, and the cross-selling or cart upsell modules installed on the shop with where they appear (product page, cart). Read it before changing related products.',
                'input' => ['properties' => [
                    'id_product' => $productId,
                    'period_days' => ['type' => 'integer', 'minimum' => 7, 'maximum' => 1095, 'description' => 'Orders taken into account, default 365 days.'],
                ], 'required' => ['id_product']],
            ],
            'suggest_related_products' => [
                'group' => 'crossselling', 'scope' => 'catalog.read', 'permission' => ['view', 'AdminProducts'],
                'handler' => ['cross', 'suggestRelatedProducts'],
                'description' => 'Suggests related products from real orders ("bought together"), for one product or for the best sellers, leaving out inactive, out of stock and already related products. Nothing is changed: show the suggestions to the merchant, then apply the approved ones.',
                'input' => ['properties' => [
                    'id_product' => self::id('One product. Leave out to get suggestions for the best sellers.'),
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 30, 'description' => 'Best sellers to cover, default 15.'],
                    'per_product' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10, 'description' => 'Suggestions per product, default 4.'],
                    'min_orders' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Orders where both were bought, default 2.'],
                    'period_days' => ['type' => 'integer', 'minimum' => 7, 'maximum' => 1095],
                ]],
            ],
            'set_related_products' => [
                'group' => 'crossselling', 'scope' => 'catalog.write', 'permission' => ['edit', 'AdminProducts'],
                'handler' => ['cross', 'setRelatedProducts'],
                'description' => 'Changes the related products (accessories) shown on a product page: replace the whole list, add products to it, or remove some. With replace, an empty list removes them all.',
                'input' => ['properties' => [
                    'id_product' => $productId,
                    'related_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                    'mode' => ['type' => 'string', 'enum' => ['replace', 'add', 'remove'], 'description' => 'Default replace.'],
                ], 'required' => ['id_product', 'related_ids']],
            ],
            'bulk_set_related_products' => [
                'group' => 'crossselling', 'scope' => 'catalog.write', 'permission' => ['edit', 'AdminProducts'], 'self_metered' => true,
                'handler' => ['cross', 'bulkSetRelatedProducts'],
                'description' => 'Applies related products to up to 50 products in one call (default mode add), typically the suggestions approved by the merchant. On the free plan each changed product counts as one change.',
                'input' => ['properties' => [
                    'products' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 50, 'items' => ['type' => 'object', 'additionalProperties' => false, 'properties' => [
                        'id_product' => ['type' => 'integer', 'minimum' => 1],
                        'related_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                        'mode' => ['type' => 'string', 'enum' => ['replace', 'add', 'remove']],
                    ], 'required' => ['id_product', 'related_ids']]],
                ], 'required' => ['products']],
            ],
            'configure_cross_selling_block' => [
                'group' => 'crossselling', 'scope' => 'marketing.write', 'permission' => ['edit', 'AdminModulesSf'],
                'handler' => ['cross', 'configureCrossSellingBlock'],
                'description' => 'Sets the native PrestaShop block "customers who bought this product also bought" (module ps_crossselling): number of products, prices shown, and whether it appears on the product page and in the cart, as additional purchases. Third-party modules are not configured here. Only the fields given change.',
                'input' => ['properties' => [
                    'products_count' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
                    'show_price' => ['type' => 'boolean'],
                    'show_on_product_page' => ['type' => 'boolean'],
                    'show_in_cart' => ['type' => 'boolean', 'description' => 'Show the block on the cart page.'],
                ]],
            ],
            'list_promotions' => [
                'group' => 'enrichment', 'scope' => 'catalog.read', 'permission' => ['view', 'AdminProducts'],
                'handler' => ['merch', 'listPromotions'],
                'description' => 'Lists the specific prices (promotions, quantity discounts) of a product.',
                'input' => ['properties' => ['id_product' => $productId], 'required' => ['id_product']],
            ],
            'create_promotion' => [
                'group' => 'enrichment', 'scope' => 'catalog.write', 'permission' => ['edit', 'AdminProducts'],
                'handler' => ['merch', 'createPromotion'],
                'description' => 'Puts a product (or one combination) on promotion: a percentage or an amount off, optionally between two dates, from a quantity or for one customer group. '
                    . 'Check list_promotions first to avoid stacking discounts. Never guess the discount: it comes from the merchant.' . self::LOST_ANSWER,
                'input' => ['properties' => [
                    'id_product' => $productId,
                    'id_product_attribute' => ['type' => 'integer', 'minimum' => 0],
                    'type' => ['type' => 'string', 'enum' => ['percentage', 'amount']],
                    'value' => ['type' => 'number', 'exclusiveMinimum' => 0, 'description' => '20 for 20 %, or an amount in the default currency.'],
                    'tax_included' => ['type' => 'boolean', 'description' => 'For amount reductions. Default true.'],
                    'date_from' => $date,
                    'date_to' => $date,
                    'from_quantity' => ['type' => 'integer', 'minimum' => 1],
                    'id_group' => ['type' => 'integer', 'minimum' => 0, 'description' => 'Customer group, 0 for everyone.'],
                    'replace_existing' => ['type' => 'boolean', 'description' => 'Replace the promotion already set on this product for the same period. Without it, such a product is refused and the existing promotion is named.'],
                ], 'required' => ['id_product', 'type', 'value']],
            ],
            'delete_promotion' => [
                'group' => 'enrichment', 'scope' => 'catalog.write', 'permission' => ['delete', 'AdminProducts'],
                'handler' => ['merch', 'deletePromotion'],
                'description' => 'Removes one specific price (promotion) by its id from list_promotions. Ask the merchant before removing.',
                'input' => ['properties' => ['id_specific_price' => self::id('From list_promotions.')], 'required' => ['id_specific_price']],
            ],
            'catalog_audit' => [
                'group' => 'enrichment', 'scope' => 'catalog.read', 'permission' => ['view', 'AdminProducts'],
                'handler' => ['merch', 'catalogAudit'],
                'description' => 'Audits the active catalogue in one language and counts what is worth fixing: thin or missing descriptions, missing or too long meta titles and descriptions, '
                    . 'duplicate meta titles, products without image, images without alt text, no EAN, no brand, no features, products only in the home category, '
                    . 'active but out of stock, inactive but in stock, zero price, and thin category descriptions. Returns sample product ids per issue. '
                    . 'Use it for "what should I improve in my catalogue" or before an SEO campaign.',
                'input' => ['properties' => [
                    'language' => ['type' => 'string', 'description' => 'ISO code, default the employee language.'],
                    'sample' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'description' => 'How many product ids per issue, default 20.'],
                ]],
            ],


            // ----- Orders -----
            'search_orders' => [
                'group' => 'orders', 'scope' => 'orders.read', 'permission' => ['view', 'AdminOrders'],
                'handler' => ['orders', 'searchOrders'],
                'description' => 'Finds orders by reference, id, customer email or name, or tracking number, optionally by status, customer or date range, newest first. '
                    . 'Use it for "where is the order of Mrs Martin" or to find orders waiting for shipment.',
                'input' => ['properties' => [
                    'query' => ['type' => 'string', 'description' => 'Reference, id, email, last name or tracking number.'],
                    'id_order_state' => self::id('Only this status, from list_order_statuses.'),
                    'id_customer' => self::id('Only this customer.'),
                    'date_from' => $date,
                    'date_to' => $date,
                    'limit' => $limit,
                ]],
            ],
            'get_order' => [
                'group' => 'orders', 'scope' => 'orders.read', 'permission' => ['view', 'AdminOrders'],
                'handler' => ['orders', 'getOrder'],
                'description' => 'Reads one order: status and history, paid and shipped flags, customer and language, delivery address, lines, totals, and shipments with carrier, '
                    . 'tracking number and tracking link. Use it to answer "where is my order?" with real data, never with guesses.',
                'input' => ['properties' => [
                    'id_order' => self::id('Order id.'),
                    'reference' => ['type' => 'string', 'description' => 'Order reference, if the id is unknown.'],
                ]],
            ],
            'list_order_statuses' => [
                'group' => 'orders', 'scope' => 'orders.read', 'permission' => ['view', 'AdminOrders'],
                'handler' => ['orders', 'listOrderStatuses'],
                'description' => 'Lists order statuses with id, whether each one emails the customer, and whether it means paid or shipped.',
            ],
            'set_order_status' => [
                'group' => 'orders', 'scope' => 'orders.write', 'permission' => ['edit', 'AdminOrders'],
                'handler' => ['orders', 'setOrderStatus'],
                'description' => 'Changes the status of one order, exactly as the back office does (history, stock, invoice and the customer email when the status sends one). '
                    . 'Say which email the customer will receive before asking for approval. Some statuses (Shipped, Delivered...) also mark an unpaid order as paid and create its invoice: '
                    . 'such a change is refused until the merchant confirms (confirm_paid).' . self::LOST_ANSWER,
                'input' => ['properties' => [
                    'id_order' => self::id('Order id.'),
                    'id_order_state' => self::id('New status, from list_order_statuses.'),
                    'confirm_paid' => ['type' => 'boolean', 'description' => 'true only when the merchant confirmed that this unpaid order may be marked as paid.'],
                ], 'required' => ['id_order', 'id_order_state']],
            ],
            'set_tracking_number' => [
                'group' => 'orders', 'scope' => 'orders.write', 'permission' => ['edit', 'AdminOrders'],
                'handler' => ['orders', 'setTrackingNumber'],
                'description' => 'Sets the tracking number of an order\'s shipment, and optionally emails the customer the in-transit message with the tracking link. '
                    . 'The number always comes from the merchant or the carrier, never guessed.' . self::LOST_ANSWER,
                'input' => ['properties' => [
                    'id_order' => self::id('Order id.'),
                    'tracking_number' => ['type' => 'string'],
                    'notify_customer' => ['type' => 'boolean', 'description' => 'Send the in-transit email. Default false.'],
                ], 'required' => ['id_order', 'tracking_number']],
            ],

            // ----- CMS -----
            'list_cms_pages' => [
                'group' => 'cms', 'scope' => 'catalog.read', 'permission' => ['view', 'AdminCmsContent'],
                'handler' => ['cms', 'listPages'],
                'description' => 'Lists the CMS pages (delivery, terms, about...) with title, slug, status and content length.',
            ],
            'get_cms_page' => [
                'group' => 'cms', 'scope' => 'catalog.read', 'permission' => ['view', 'AdminCmsContent'],
                'handler' => ['cms', 'getPage'],
                'description' => 'Reads a CMS page in every language: meta title, SEO title, meta description, HTML content, slug.',
                'input' => ['properties' => ['id_cms' => self::id('From list_cms_pages.')], 'required' => ['id_cms']],
            ],
            'update_cms_page' => [
                'group' => 'cms', 'scope' => 'catalog.write', 'permission' => ['edit', 'AdminCmsContent'],
                'handler' => ['cms', 'updatePage'],
                'description' => 'Changes a CMS page: active state and translated meta title, SEO title, meta description, HTML content, slug. Only what is given changes. '
                    . 'Use it to translate pages or improve their SEO.' . self::LOST_ANSWER,
                'input' => ['properties' => [
                    'id_cms' => self::id('From list_cms_pages.'),
                    'active' => ['type' => 'boolean'],
                    'translations' => self::translations(['meta_title', 'head_seo_title', 'meta_description', 'content', 'link_rewrite'], 'Translated texts.'),
                ], 'required' => ['id_cms']],
            ],
            'preview_llms_txt' => [
                'group' => 'geo', 'scope' => 'catalog.read', 'permission' => ['view', 'AdminMeta'],
                'handler' => ['llms', 'preview'],
                'description' => 'Builds the llms.txt file of the shop (the llmstxt.org standard: a Markdown map that helps AI assistants and answer engines understand and cite the shop) '
                    . 'without publishing it: shop summary, main categories, best-selling products with prices, customer information pages and contact, with links. '
                    . 'Show the result to the merchant before publish_llms_txt. Also says whether a file already exists and whether it can be written.',
                'input' => ['properties' => [
                    'summary' => ['type' => 'string', 'description' => 'One or two sentences on what the shop sells and for whom, approved by the merchant. Default: the home page meta description.'],
                    'details' => ['type' => 'string', 'description' => 'Optional paragraph (plain text): specialities, guarantees, delivery promise. Never invent facts.'],
                    'products_limit' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 200, 'description' => 'How many products to list, best sellers first (default 30, 0 for none).'],
                ]],
            ],
            'publish_llms_txt' => [
                'group' => 'geo', 'scope' => 'catalog.write', 'permission' => ['edit', 'AdminMeta'],
                'handler' => ['llms', 'publish'],
                'description' => 'Writes llms.txt at the root of the shop (yourshop.com/llms.txt) with the same options as preview_llms_txt. Any previous file is kept as a backup. '
                    . 'Call it only after the merchant approved the preview. Run it again after big catalogue changes to refresh the file.',
                'input' => ['properties' => [
                    'summary' => ['type' => 'string'],
                    'details' => ['type' => 'string'],
                    'products_limit' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 200],
                ]],
            ],

            // ----- Bulk -----
            'bulk_update_products' => [
                'group' => 'catalog', 'scope' => 'catalog.write', 'permission' => ['edit', 'AdminProducts'], 'self_metered' => true,
                'handler' => ['catalog', 'bulkUpdateProducts'],
                'description' => 'Applies update_product to up to 50 products in one call: prices, statuses, references, SEO texts or translations into another language. '
                    . 'Each entry takes the same fields as update_product. Use it for batch edits and batch translations after the merchant approved the style on a first product. '
                    . 'Returns what was updated, unchanged or failed per product. On the free plan each changed product counts as one change; products beyond the allowance are listed as not done.'
                    . self::LOST_ANSWER,
                'input' => ['properties' => [
                    'products' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 50, 'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id_product' => ['type' => 'integer', 'minimum' => 1],
                            'price' => ['type' => 'number', 'minimum' => 0],
                            'wholesale_price' => ['type' => 'number', 'minimum' => 0],
                            'reference' => ['type' => 'string'],
                            'ean13' => ['type' => 'string'],
                            'active' => ['type' => 'boolean'],
                            'on_sale' => ['type' => 'boolean'],
                            'visibility' => ['type' => 'string', 'enum' => ['both', 'catalog', 'search', 'none']],
                            'id_manufacturer' => ['type' => 'integer', 'minimum' => 0],
                            'translations' => self::translations(['name', 'description_short', 'description', 'meta_title', 'meta_description', 'link_rewrite'], 'Translated texts.'),
                        ],
                        'required' => ['id_product'],
                        'additionalProperties' => false,
                    ]],
                ], 'required' => ['products']],
            ],

            // ----- Customers and daily work -----
            'search_customers' => [
                'group' => 'customers', 'scope' => 'customers.read', 'permission' => ['view', 'AdminCustomers'],
                'handler' => ['ops', 'searchCustomers'],
                'description' => 'Finds customers by name, email, company or id, with their number of valid orders and total spent. Personal data: only use it for the merchant\'s own requests.',
                'input' => ['properties' => ['query' => ['type' => 'string', 'minLength' => 2], 'limit' => $limit], 'required' => ['query']],
            ],
            'get_customer' => [
                'group' => 'customers', 'scope' => 'customers.read', 'permission' => ['view', 'AdminCustomers'],
                'handler' => ['ops', 'getCustomer'],
                'description' => 'Reads one customer: contact details, groups, addresses and last 10 orders with status.',
                'input' => ['properties' => ['id_customer' => self::id('From search_customers.')], 'required' => ['id_customer']],
            ],
            'sales_summary' => [
                'group' => 'daily', 'scope' => 'sales.read', 'permission' => ['view', 'AdminStats'],
                'handler' => ['ops', 'salesSummary'],
                'description' => 'Sales figures for a period: valid orders, revenue with and without tax, average order, buyers, new customer accounts, orders by status and top 10 products. '
                    . 'Default period is today. Use it for "how was today / this week / last month". Compute the dates from today\'s date; never guess another year.',
                'input' => ['properties' => ['date_from' => $date, 'date_to' => $date]],
            ],
            'low_stock' => [
                'group' => 'daily', 'scope' => 'catalog.read', 'permission' => ['view', 'AdminProducts'],
                'handler' => ['ops', 'lowStock'],
                'description' => 'Lists active products and combinations whose stock is at or below a threshold (default 3), lowest first. Use it for restocking.',
                'input' => ['properties' => ['threshold' => ['type' => 'integer', 'minimum' => 0], 'limit' => $limit]],
            ],
            'list_customer_messages' => [
                'group' => 'daily', 'scope' => 'service.read', 'permission' => ['view', 'AdminCustomerThreads'],
                'handler' => ['ops', 'listMessages'],
                'description' => 'Lists customer service conversations (contact form and order messages), open ones by default, with the last message and whether a reply is awaited.',
                'input' => ['properties' => [
                    'status' => ['type' => 'string', 'enum' => ['open', 'pending1', 'pending2', 'closed', 'all']],
                    'limit' => $limit,
                ]],
            ],
            'get_customer_messages' => [
                'group' => 'daily', 'scope' => 'service.read', 'permission' => ['view', 'AdminCustomerThreads'],
                'handler' => ['ops', 'getMessageThread'],
                'description' => 'Reads a whole customer service conversation. Always read it before drafting a reply.',
                'input' => ['properties' => ['id_customer_thread' => self::id('From list_customer_messages.')], 'required' => ['id_customer_thread']],
            ],
            'reply_to_customer' => [
                'group' => 'daily', 'scope' => 'service.write', 'permission' => ['edit', 'AdminCustomerThreads'],
                'handler' => ['ops', 'replyToMessage'],
                'description' => 'Sends a reply to a customer by email and saves it in the conversation. The customer receives it immediately: show the exact text to the merchant and get their approval first. '
                    . 'Write in the customer\'s language (given by get_customer_messages). Never promise refunds, delays or discounts the merchant did not decide.' . self::LOST_ANSWER,
                'input' => ['properties' => [
                    'id_customer_thread' => self::id('From list_customer_messages.'),
                    'message' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 10000, 'description' => 'Plain text reply.'],
                    'close' => ['type' => 'boolean', 'description' => 'Close the conversation after replying. Default true.'],
                ], 'required' => ['id_customer_thread', 'message']],
            ],
            'create_voucher' => [
                'group' => 'daily', 'scope' => 'marketing.write', 'permission' => ['add', 'AdminCartRules'],
                'handler' => ['ops', 'createVoucher'],
                'description' => 'Creates a voucher code: percent off, amount off and/or free shipping, with dates, minimum order, number of uses, optionally for one customer. '
                    . 'The discount always comes from the merchant, never decided by the assistant.' . self::LOST_ANSWER,
                'input' => ['properties' => [
                    'name' => ['type' => 'string', 'description' => 'Shown to the customer in the cart.'],
                    'code' => ['type' => 'string', 'description' => 'Letters, digits, - and _. Generated if left out.'],
                    'percent' => ['type' => 'number', 'exclusiveMinimum' => 0, 'maximum' => 99],
                    'amount' => ['type' => 'number', 'exclusiveMinimum' => 0, 'description' => 'Tax included, default currency.'],
                    'free_shipping' => ['type' => 'boolean'],
                    'date_from' => $date,
                    'date_to' => $date,
                    'minimum_amount' => ['type' => 'number', 'minimum' => 0],
                    'quantity' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Total number of uses. Default 1.'],
                    'quantity_per_user' => ['type' => 'integer', 'minimum' => 1],
                    'id_customer' => ['type' => 'integer', 'minimum' => 0, 'description' => 'Restrict to one customer.'],
                    'description' => ['type' => 'string', 'description' => 'Internal note.'],
                ], 'required' => ['name']],
            ],

            // ----- Theme (child themes only) -----
            'list_themes' => [
                'group' => 'theme', 'scope' => 'theme.read', 'permission' => ['view', 'AdminThemes'],
                'handler' => ['themes', 'listThemes'],
                'description' => 'Lists the installed themes: which one is live on which shop, which are child themes, and which can be edited (every theme that is not live and not the parent of a live theme). '
                    . 'Call it before any theme work.',
            ],
            'create_child_theme' => [
                'group' => 'theme', 'scope' => 'theme.write', 'permission' => ['add', 'AdminThemes'],
                'handler' => ['themes', 'createChildTheme'],
                'description' => 'Creates a draft child theme to work on, never live. From a parent theme (such as classic or hummingbird) it starts empty and inherits everything; '
                    . 'from an existing child theme (for example the live one) it copies its overrides, to keep working on the current design safely. '
                    . 'Only the merchant can put the draft live, from the Piskee module page in the back office.' . self::LOST_ANSWER,
                'input' => ['properties' => [
                    'source_theme' => $themeName,
                    'name' => ['type' => 'string', 'description' => 'New technical name: lowercase letters, digits, - or _.'],
                    'display_name' => ['type' => 'string'],
                ], 'required' => ['source_theme', 'name']],
            ],
            'list_theme_files' => [
                'group' => 'theme', 'scope' => 'theme.read', 'permission' => ['view', 'AdminThemes'],
                'handler' => ['themes', 'listThemeFiles'],
                'description' => 'Lists the template, stylesheet and script files of a theme under a folder, separating files the child theme overrides from files inherited from its parent.',
                'input' => ['properties' => ['theme' => $themeName, 'folder' => ['type' => 'string', 'description' => 'Default templates. Also: modules, assets/css, assets/js.']], 'required' => ['theme']],
            ],
            'read_theme_file' => [
                'group' => 'theme', 'scope' => 'theme.read', 'permission' => ['view', 'AdminThemes'],
                'handler' => ['themes', 'readThemeFile'],
                'description' => 'Reads a .tpl, .css, .js, .yml or .json file of a theme. For a child theme, a file it does not override is read from the parent. '
                    . 'Always read the current file before writing an override.',
                'input' => ['properties' => ['theme' => $themeName, 'path' => $themePath], 'required' => ['theme', 'path']],
            ],
            'search_theme_files' => [
                'group' => 'theme', 'scope' => 'theme.read', 'permission' => ['view', 'AdminThemes'],
                'handler' => ['themes', 'searchThemeFiles'],
                'description' => 'Searches a text in the effective files of a theme (child overrides first, then parent), case-insensitive, and returns file, line and the line itself. '
                    . 'Use it to find where headings are written ("<h1", "<h2"), where the breadcrumb is included ("breadcrumb"), or a CSS class.',
                'input' => ['properties' => ['theme' => $themeName, 'text' => ['type' => 'string', 'minLength' => 2]], 'required' => ['theme', 'text']],
            ],
            'write_theme_file' => [
                'group' => 'theme', 'scope' => 'theme.write', 'permission' => ['edit', 'AdminThemes'],
                'handler' => ['themes', 'writeThemeFile'],
                'description' => 'Writes a whole .tpl, .css or .js file in a theme that is not live (refused on the live theme and on the parent of a live theme), under templates/, modules/, assets/css/ or assets/js/. '
                    . 'Writing a path that exists in the parent overrides it. The previous version is backed up. Templates with unbalanced Smarty blocks and any PHP are refused. '
                    . 'Send the complete file, not a fragment. Prefer assets/css/custom.css for style changes. Nothing changes on the live shop.',
                'input' => ['properties' => ['theme' => $themeName, 'path' => $themePath, 'content' => ['type' => 'string', 'description' => 'Complete new content of the file.']], 'required' => ['theme', 'path', 'content']],
            ],
            'remove_theme_file' => [
                'group' => 'theme', 'scope' => 'theme.write', 'permission' => ['delete', 'AdminThemes'],
                'handler' => ['themes', 'removeThemeFile'],
                'description' => 'Removes a file from a theme that is not live (for a child theme, the parent version is used again). The removed file is backed up and can be restored.',
                'input' => ['properties' => ['theme' => $themeName, 'path' => $themePath], 'required' => ['theme', 'path']],
            ],
            'list_theme_backups' => [
                'group' => 'theme', 'scope' => 'theme.read', 'permission' => ['view', 'AdminThemes'],
                'handler' => ['themes', 'listThemeBackups'],
                'description' => 'Lists the backups kept for a theme (one per write or removal), newest first, optionally for one path.',
                'input' => ['properties' => ['theme' => $themeName, 'path' => $themePath], 'required' => ['theme']],
            ],
            'restore_theme_file' => [
                'group' => 'theme', 'scope' => 'theme.write', 'permission' => ['edit', 'AdminThemes'],
                'handler' => ['themes', 'restoreThemeFile'],
                'description' => 'Restores a backed up version of a file in a theme that is not live. The version it replaces is itself backed up.',
                'input' => ['properties' => ['theme' => $themeName, 'backup_id' => ['type' => 'string', 'description' => 'From list_theme_backups.']], 'required' => ['theme', 'backup_id']],
            ],
            'audit_page' => [
                'group' => 'theme', 'scope' => 'theme.read', 'permission' => ['view', 'AdminThemes'],
                'handler' => ['themes', 'auditPage'],
                'description' => 'Loads a page of the live shop and returns its title, meta description, canonical, heading outline (h1 to h6 in order) with problems found '
                    . '(no h1, several h1, skipped levels, empty headings) and whether a breadcrumb and BreadcrumbList structured data are present. '
                    . 'Use it on the home, a category and a product page before cleaning headings or adding a breadcrumb.',
                'input' => ['properties' => ['url' => ['type' => 'string', 'description' => 'Full URL of this shop, or a path such as /en/3-clothes. Default: home page.']]],
            ],
        ];
    }
}
