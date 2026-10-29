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
 * The look of the live shop, through the settings of PrestaShop's native
 * front modules, never through the code of the theme: image slider,
 * banner, custom text block, product blocks of the home page, social
 * links, and which blocks the home page shows in which order.
 *
 * Each change goes through the same storage as the module's own
 * configuration page, so the merchant finds it there and can change it
 * back by hand. Every answer gives the previous values.
 */
class PiskeeConnectorAppearance
{
    private const SLIDER = 'ps_imageslider';
    private const BANNER = 'ps_banner';
    private const TEXT = 'ps_customtext';
    private const SOCIAL = 'ps_socialfollow';
    private const HOME_HOOK = 'displayHome';

    /** Network => configuration key of ps_socialfollow. */
    private const NETWORKS = [
        'facebook' => 'BLOCKSOCIAL_FACEBOOK', 'instagram' => 'BLOCKSOCIAL_INSTAGRAM', 'tiktok' => 'BLOCKSOCIAL_TIKTOK',
        'youtube' => 'BLOCKSOCIAL_YOUTUBE', 'pinterest' => 'BLOCKSOCIAL_PINTEREST', 'linkedin' => 'BLOCKSOCIAL_LINKEDIN',
        'twitter' => 'BLOCKSOCIAL_TWITTER', 'vimeo' => 'BLOCKSOCIAL_VIMEO', 'discord' => 'BLOCKSOCIAL_DISCORD', 'rss' => 'BLOCKSOCIAL_RSS',
    ];

    /** Product blocks of the home page: setting => [module, key, min, max]. */
    private const PRODUCT_BLOCKS = [
        'featured_count' => ['ps_featuredproducts', 'HOME_FEATURED_NBR', 1, 48],
        'bestsellers_count' => ['ps_bestsellers', 'PS_BLOCK_BESTSELLERS_TO_DISPLAY', 1, 48],
        'new_products_count' => ['ps_newproducts', 'NEW_PRODUCTS_NBR', 1, 48],
        'new_products_days' => ['ps_newproducts', 'PS_NB_DAYS_NEW_PRODUCT', 1, 365],
        'specials_count' => ['ps_specials', 'BLOCKSPECIALS_SPECIALS_NBR', 1, 48],
    ];

    /** Modules never moved on or off the home page by set_home_blocks. */
    private const NOT_A_BLOCK = ['piskeeconnector', 'piskeeconnectorpro'];

    /**
     * @return array<int, string>
     */
    public static function networkNames(): array
    {
        return array_keys(self::NETWORKS);
    }

    // ------------------------------------------------------------------
    // Overview
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function getAppearance(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $idShop = $c['id_shop'];
        $idLang = $c['id_lang'];
        $modules = [];
        foreach ([self::SLIDER => 'Image slider', self::BANNER => 'Banner', self::TEXT => 'Custom text block', self::SOCIAL => 'Social media links',
            'ps_featuredproducts' => 'Featured products', 'ps_bestsellers' => 'Best sellers', 'ps_newproducts' => 'New products', 'ps_specials' => 'On sale', 'ps_mainmenu' => 'Main menu (get_menu)', ] as $name => $label) {
            $modules[$name] = ['label' => $label, 'installed' => Module::isInstalled($name), 'enabled' => Module::isInstalled($name) && Module::isEnabled($name), 'shown_on' => self::hooksOf($name, $idShop)];
        }

        $slides = self::moduleReady(self::SLIDER) ? $this->slides($idShop, $idLang, false) : [];
        $text = self::moduleReady(self::TEXT) ? $this->customText($idShop) : null;
        $settings = [];
        foreach (self::PRODUCT_BLOCKS as $setting => [$module, $key]) {
            if (Module::isInstalled($module)) {
                $settings[$setting] = (int) self::conf($key, $idShop);
            }
        }
        if (Module::isInstalled('ps_featuredproducts')) {
            $settings['featured_category'] = (int) self::conf('HOME_FEATURED_CAT', $idShop);
            $settings['featured_random'] = (bool) self::conf('HOME_FEATURED_RANDOMIZE', $idShop);
        }

        return PiskeeConnectorHelper::json([
            'live_theme' => (string) Context::getContext()->shop->theme_name,
            'home_page_blocks' => $this->homeBlocks($idShop),
            'modules' => $modules,
            'slider' => ['slides' => count($slides), 'active_slides' => count(array_filter($slides, function ($slide) {
                return $slide['active'];
            })), 'settings' => self::moduleReady(self::SLIDER) ? $this->sliderSettings($idShop) : null],
            'banner' => self::moduleReady(self::BANNER) ? $this->banner($idShop) : null,
            'custom_text' => $text === null ? null : array_map(function ($html) {
                return PiskeeConnectorHelper::excerpt($html, 300);
            }, $text),
            'home_products' => $settings,
            'social_links' => self::moduleReady(self::SOCIAL) ? $this->social($idShop) : null,
            'note' => 'These are settings of the live shop, changed without touching the theme code: list_slides, save_slide, configure_slider, set_banner, set_custom_text, '
                . 'configure_home_products, set_social_links, set_home_blocks. Blocks of third-party modules are listed in home_page_blocks but configured in their own module page.',
        ]);
    }

    // ------------------------------------------------------------------
    // Image slider
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function listSlides(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        if (!self::moduleReady(self::SLIDER)) {
            return self::missing(self::SLIDER, 'Image slider');
        }

        return PiskeeConnectorHelper::json([
            'slides' => $this->slides($c['id_shop'], $c['id_lang'], true),
            'settings' => $this->sliderSettings($c['id_shop']),
            'shown_on' => self::hooksOf(self::SLIDER, $c['id_shop']),
            'note' => 'Slides are shown in position order; inactive slides are hidden. Change one with save_slide (id_slide), add one with save_slide without id_slide.',
        ]);
    }

    /**
     * Creates a slide, or changes one when id_slide is given.
     *
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function saveSlide(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $idShop = $c['id_shop'];
        if (!self::moduleReady(self::SLIDER)) {
            return self::missing(self::SLIDER, 'Image slider');
        }
        Module::getInstanceByName(self::SLIDER); // Loads Ps_HomeSlide.

        $idSlide = (int) ($args['id_slide'] ?? 0);
        $creating = $idSlide <= 0;
        if ($creating) {
            $slide = new Ps_HomeSlide();
            $slide->active = (bool) ($args['active'] ?? false);
        } else {
            if (!$this->slideInShop($idSlide, $idShop)) {
                return PiskeeConnectorHelper::error('Slide ' . $idSlide . ' not found. Call list_slides.');
            }
            $slide = new Ps_HomeSlide($idSlide);
        }
        $before = $creating ? null : $this->slideData($slide, $idShop);

        $translations = PiskeeConnectorHelper::translations($args['translations'] ?? null, $idShop);
        if (!$translations['ok']) {
            return PiskeeConnectorHelper::error($translations['error']);
        }
        $languages = PiskeeConnectorHelper::languages($idShop);
        $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');

        // Images first: one for every language, or one per language.
        $images = [];
        $common = trim((string) ($args['image_url'] ?? ''));
        foreach ($languages as $idLang) {
            $url = trim((string) ($translations['map'][$idLang]['image_url'] ?? $common));
            if ($url !== '') {
                $images[$idLang] = $url;
            }
        }
        if ($creating && $images === []) {
            return PiskeeConnectorHelper::error('A new slide needs image_url: a public address of the picture (JPEG, PNG, GIF or WebP).');
        }
        $stored = [];
        foreach (array_unique($images) as $url) {
            $file = $this->storeImage($url, _PS_MODULE_DIR_ . self::SLIDER . '/images/');
            if ($file['error'] !== '') {
                return PiskeeConnectorHelper::error('Image ' . $url . ': ' . $file['error'] . ' Nothing was changed.');
            }
            $stored[$url] = $file['name'];
        }

        $fallback = $translations['map'][$defaultLang] ?? ($translations['map'] !== [] ? reset($translations['map']) : []);
        foreach ($languages as $idLang) {
            $fields = $translations['map'][$idLang] ?? ($creating ? $fallback : []);
            foreach (['title', 'legend', 'description', 'url'] as $field) {
                if (array_key_exists($field, $fields)) {
                    $value = (string) $fields[$field];
                    $slide->{$field}[$idLang] = $field === 'description' ? $value : trim(strip_tags($value));
                } elseif ($creating) {
                    $slide->{$field}[$idLang] = '';
                }
            }
            if (isset($images[$idLang])) {
                $slide->image[$idLang] = $stored[$images[$idLang]];
            } elseif ($creating) {
                $slide->image[$idLang] = $stored[reset($images)];
            }
            if (($slide->url[$idLang] ?? '') !== '' && !self::safeLink((string) $slide->url[$idLang])) {
                return PiskeeConnectorHelper::error('The link "' . $slide->url[$idLang] . '" must start with https://, http:// or / (a page of the shop). Nothing was changed.');
            }
        }
        if (!$creating && array_key_exists('active', $args) && $args['active'] !== null) {
            $slide->active = (bool) $args['active'];
        }
        if ($creating) {
            $slide->position = $this->slideCount($idShop);
        }

        $error = PiskeeConnectorHelper::validate($slide);
        if ($error !== '') {
            return PiskeeConnectorHelper::error('Not saved: ' . $error);
        }
        $saved = $creating ? $slide->add() : $slide->update();
        if (!$saved) {
            return PiskeeConnectorHelper::error('PrestaShop refused to save the slide.');
        }
        if (isset($args['position'])) {
            $this->placeSlide((int) $slide->id, max(1, (int) $args['position']), $idShop);
        }
        self::clearModuleCache(self::SLIDER);

        $after = $this->slideData(new Ps_HomeSlide((int) $slide->id), $idShop);

        return PiskeeConnectorHelper::json([
            'id_slide' => (int) $slide->id,
            'created' => $creating,
            'slide' => $after,
            'previous' => $before,
            'message' => $after['active'] ? 'The slide is live on the home page.' : 'The slide is saved but inactive: customers do not see it until it is activated (save_slide active: true).',
        ]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function configureSlider(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        if (!self::moduleReady(self::SLIDER)) {
            return self::missing(self::SLIDER, 'Image slider');
        }
        $before = $this->sliderSettings($c['id_shop']);
        if (isset($args['speed_ms'])) {
            $speed = (int) $args['speed_ms'];
            if ($speed < 1000 || $speed > 30000) {
                return PiskeeConnectorHelper::error('speed_ms must be between 1000 and 30000 (time each slide stays, in milliseconds).');
            }
            self::setConf('HOMESLIDER_SPEED', $speed, $c['id_shop']);
        }
        if (isset($args['pause_on_hover'])) {
            self::setConf('HOMESLIDER_PAUSE_ON_HOVER', $args['pause_on_hover'] ? 1 : 0, $c['id_shop']);
        }
        if (isset($args['loop'])) {
            self::setConf('HOMESLIDER_WRAP', $args['loop'] ? 1 : 0, $c['id_shop']);
        }
        self::clearModuleCache(self::SLIDER);

        return PiskeeConnectorHelper::json(['settings' => $this->sliderSettings($c['id_shop']), 'previous' => $before]);
    }

    // ------------------------------------------------------------------
    // Banner and custom text
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function setBanner(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $idShop = $c['id_shop'];
        if (!self::moduleReady(self::BANNER)) {
            return self::missing(self::BANNER, 'Banner');
        }
        $translations = PiskeeConnectorHelper::translations($args['translations'] ?? null, $idShop);
        if (!$translations['ok']) {
            return PiskeeConnectorHelper::error($translations['error']);
        }
        $before = $this->banner($idShop);
        $languages = PiskeeConnectorHelper::languages($idShop);
        $common = trim((string) ($args['image_url'] ?? ''));

        $values = ['BANNER_IMG' => [], 'BANNER_LINK' => [], 'BANNER_DESC' => []];
        foreach (array_keys($values) as $key) {
            foreach ($languages as $idLang) {
                $values[$key][$idLang] = (string) self::conf($key, $idShop, $idLang);
            }
        }
        $stored = [];
        foreach ($languages as $idLang) {
            $fields = $translations['map'][$idLang] ?? [];
            $url = trim((string) ($fields['image_url'] ?? $common));
            if ($url !== '') {
                if (!isset($stored[$url])) {
                    $file = $this->storeImage($url, _PS_MODULE_DIR_ . self::BANNER . '/img/');
                    if ($file['error'] !== '') {
                        return PiskeeConnectorHelper::error('Image ' . $url . ': ' . $file['error'] . ' Nothing was changed.');
                    }
                    $stored[$url] = $file['name'];
                }
                $values['BANNER_IMG'][$idLang] = $stored[$url];
            }
            if (array_key_exists('link', $fields)) {
                $link = trim((string) $fields['link']);
                if ($link !== '' && !self::safeLink($link)) {
                    return PiskeeConnectorHelper::error('The link "' . $link . '" must start with https://, http:// or /. Nothing was changed.');
                }
                $values['BANNER_LINK'][$idLang] = $link;
            }
            if (array_key_exists('description', $fields)) {
                $values['BANNER_DESC'][$idLang] = trim(strip_tags((string) $fields['description']));
            }
        }
        foreach ($values as $key => $byLang) {
            self::setConf($key, $byLang, $idShop);
        }
        self::clearModuleCache(self::BANNER);

        return PiskeeConnectorHelper::json(['banner' => $this->banner($idShop), 'previous' => $before, 'shown_on' => self::hooksOf(self::BANNER, $idShop)]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function setCustomText(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $idShop = $c['id_shop'];
        if (!self::moduleReady(self::TEXT)) {
            return self::missing(self::TEXT, 'Custom text block');
        }
        Module::getInstanceByName(self::TEXT); // Loads CustomText.
        $translations = PiskeeConnectorHelper::translations($args['translations'] ?? null, $idShop);
        if (!$translations['ok'] || $translations['map'] === []) {
            return PiskeeConnectorHelper::error($translations['error'] !== '' ? $translations['error'] : 'Give the text in at least one language.');
        }
        $before = $this->customText($idShop);
        $idInfo = (int) CustomText::getCustomTextIdByShop($idShop);
        $info = new CustomText($idInfo > 0 ? $idInfo : null);
        $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $fallback = $translations['map'][$defaultLang] ?? reset($translations['map']);
        foreach (PiskeeConnectorHelper::languages($idShop) as $idLang) {
            $fields = $translations['map'][$idLang] ?? null;
            if ($fields === null && $idInfo > 0) {
                continue;
            }
            $html = (string) (($fields ?? $fallback)['html'] ?? '');
            if (!Configuration::get('PS_ALLOW_HTML_IFRAME') && stripos($html, '<iframe') !== false) {
                return PiskeeConnectorHelper::error('Iframes are refused by the shop settings (Shop Parameters > General). Nothing was changed.');
            }
            $info->text[$idLang] = $html;
        }
        $error = PiskeeConnectorHelper::validate($info);
        if ($error !== '') {
            return PiskeeConnectorHelper::error('Not saved: ' . $error . ' (scripts and unsafe HTML are refused).');
        }
        if (!$info->save()) {
            return PiskeeConnectorHelper::error('PrestaShop refused to save the text.');
        }
        if ($idInfo <= 0) {
            Db::getInstance()->insert('info_shop', ['id_info' => (int) $info->id, 'id_shop' => $idShop], false, true, Db::INSERT_IGNORE);
        }
        self::clearModuleCache(self::TEXT);

        return PiskeeConnectorHelper::json(['custom_text' => $this->customText($idShop), 'previous' => $before, 'shown_on' => self::hooksOf(self::TEXT, $idShop)]);
    }

    // ------------------------------------------------------------------
    // Home page products and social links
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function configureHomeProducts(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $idShop = $c['id_shop'];
        $changes = [];
        foreach (self::PRODUCT_BLOCKS as $setting => [$module, $key, $min, $max]) {
            if (!isset($args[$setting])) {
                continue;
            }
            if (!Module::isInstalled($module)) {
                return PiskeeConnectorHelper::error('The module ' . $module . ' is not installed, so ' . $setting . ' cannot be set. No change was made.');
            }
            $value = (int) $args[$setting];
            if ($value < $min || $value > $max) {
                return PiskeeConnectorHelper::error($setting . ' must be between ' . $min . ' and ' . $max . '. Nothing was changed.');
            }
            $changes[$key] = [$setting, $value, $module];
        }
        if (isset($args['featured_category'])) {
            $idCategory = (int) $args['featured_category'];
            if (!PiskeeConnectorHelper::inShop(new Category($idCategory, null, $idShop), $idShop)) {
                return PiskeeConnectorHelper::error('Category ' . $idCategory . ' not found. Nothing was changed.');
            }
            $changes['HOME_FEATURED_CAT'] = ['featured_category', $idCategory, 'ps_featuredproducts'];
        }
        if (isset($args['featured_random'])) {
            $changes['HOME_FEATURED_RANDOMIZE'] = ['featured_random', $args['featured_random'] ? 1 : 0, 'ps_featuredproducts'];
        }
        if ($changes === []) {
            return PiskeeConnectorHelper::error('Give at least one setting to change.');
        }
        $out = [];
        $modules = [];
        foreach ($changes as $key => [$setting, $value, $module]) {
            $out[$setting] = ['from' => self::conf($key, $idShop), 'to' => $value];
            self::setConf($key, $value, $idShop);
            $modules[$module] = true;
        }
        foreach (array_keys($modules) as $module) {
            self::clearModuleCache($module);
        }

        return PiskeeConnectorHelper::json(['changes' => $out]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function setSocialLinks(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        if (!self::moduleReady(self::SOCIAL)) {
            return self::missing(self::SOCIAL, 'Social media follow links');
        }
        $links = is_array($args['links'] ?? null) ? $args['links'] : [];
        if ($links === []) {
            return PiskeeConnectorHelper::error('links must give at least one network.');
        }
        $before = $this->social($c['id_shop']);
        foreach ($links as $network => $url) {
            if (!isset(self::NETWORKS[$network])) {
                return PiskeeConnectorHelper::error('Unknown network "' . $network . '". Networks: ' . implode(', ', array_keys(self::NETWORKS)) . '. Nothing was changed.');
            }
            $url = trim((string) $url);
            if ($url !== '' && !preg_match('#^https?://[^\s"<>]+$#i', $url)) {
                return PiskeeConnectorHelper::error('The address for ' . $network . ' must start with https://. Nothing was changed.');
            }
        }
        foreach ($links as $network => $url) {
            self::setConf(self::NETWORKS[$network], trim((string) $url), $c['id_shop']);
        }
        self::clearModuleCache(self::SOCIAL);

        return PiskeeConnectorHelper::json(['social_links' => $this->social($c['id_shop']), 'previous' => $before, 'note' => 'An empty address hides that network.']);
    }

    // ------------------------------------------------------------------
    // Blocks of the home page
    // ------------------------------------------------------------------

    /**
     * Sets which modules the home page shows, in which order.
     *
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function setHomeBlocks(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $idShop = $c['id_shop'];
        $wanted = array_values(array_unique(array_map('strval', is_array($args['blocks'] ?? null) ? $args['blocks'] : [])));
        if ($wanted === []) {
            return PiskeeConnectorHelper::error('blocks must list, in order, every module the home page shows. Call get_appearance first.');
        }
        $idHook = (int) Hook::getIdByName(self::HOME_HOOK);
        if ($idHook <= 0) {
            return PiskeeConnectorHelper::error('The hook displayHome does not exist in this shop.');
        }
        $current = array_column($this->homeBlocks($idShop), 'module');
        $instances = [];
        foreach ($wanted as $name) {
            if (in_array($name, self::NOT_A_BLOCK, true)) {
                return PiskeeConnectorHelper::error($name . ' is not a home page block. Nothing was changed.');
            }
            $module = Module::isInstalled($name) ? Module::getInstanceByName($name) : false;
            if (!$module || !Module::isEnabled($name)) {
                return PiskeeConnectorHelper::error('The module ' . $name . ' is not installed or is disabled. Nothing was changed.');
            }
            if (!in_array($name, $current, true) && !method_exists($module, 'hookDisplayHome') && !($module instanceof PrestaShop\PrestaShop\Core\Module\WidgetInterface)) {
                return PiskeeConnectorHelper::error('The module ' . $name . ' cannot be shown on the home page. Nothing was changed.');
            }
            $instances[$name] = $module;
        }

        $removed = array_values(array_diff($current, $wanted));
        foreach ($removed as $name) {
            $module = Module::getInstanceByName($name);
            if ($module) {
                $module->unregisterHook(self::HOME_HOOK, [$idShop]);
            }
        }
        foreach ($wanted as $name) {
            if (!in_array($name, $current, true)) {
                $instances[$name]->registerHook(self::HOME_HOOK, [$idShop]);
            }
        }
        foreach ($wanted as $index => $name) {
            Db::getInstance()->update('hook_module', ['position' => $index + 1],
                '`id_hook` = ' . $idHook . ' AND `id_shop` = ' . $idShop . ' AND `id_module` = ' . (int) $instances[$name]->id);
        }
        Cache::clean('hook_module_*');
        Tools::clearCache();

        return PiskeeConnectorHelper::json([
            'home_page_blocks' => $this->homeBlocks($idShop),
            'removed' => $removed,
            'previous' => $current,
            'note' => 'Removed blocks keep their settings: put them back by adding their module name to blocks.',
        ]);
    }

    // ------------------------------------------------------------------
    // Reading
    // ------------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    private function homeBlocks(int $idShop): array
    {
        $idHook = (int) Hook::getIdByName(self::HOME_HOOK);
        if ($idHook <= 0) {
            return [];
        }
        $rows = Db::getInstance()->executeS('SELECT m.`name`, m.`active`, hm.`position` FROM `' . _DB_PREFIX_ . 'hook_module` hm'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'module` m ON m.`id_module` = hm.`id_module`'
            . ' WHERE hm.`id_hook` = ' . $idHook . ' AND hm.`id_shop` = ' . $idShop . ' ORDER BY hm.`position` ASC') ?: [];
        $out = [];
        foreach ($rows as $row) {
            $module = Module::getInstanceByName((string) $row['name']);
            $out[] = ['position' => count($out) + 1, 'module' => (string) $row['name'], 'name' => $module ? (string) $module->displayName : (string) $row['name'], 'enabled' => (bool) $row['active']];
        }

        return $out;
    }

    /**
     * @return array<int, string>
     */
    private static function hooksOf(string $name, int $idShop): array
    {
        if (!Module::isInstalled($name)) {
            return [];
        }
        $rows = Db::getInstance()->executeS('SELECT h.`name` FROM `' . _DB_PREFIX_ . 'hook_module` hm'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'hook` h ON h.`id_hook` = hm.`id_hook`'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'module` m ON m.`id_module` = hm.`id_module`'
            . " WHERE m.`name` = '" . pSQL($name) . "' AND hm.`id_shop` = " . $idShop . " AND h.`name` LIKE 'display%' AND h.`name` NOT IN ('displayHeader', 'displayBackOfficeHeader')") ?: [];

        return array_values(array_map(function ($row) {
            return (string) $row['name'];
        }, $rows));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function slides(int $idShop, int $idLang, bool $full): array
    {
        Module::getInstanceByName(self::SLIDER);
        $ids = Db::getInstance()->executeS('SELECT hss.`id_homeslider_slides` AS id FROM `' . _DB_PREFIX_ . 'homeslider` hs'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'homeslider_slides` hss ON hss.`id_homeslider_slides` = hs.`id_homeslider_slides`'
            . ' WHERE hs.`id_shop` = ' . $idShop . ' ORDER BY hss.`position` ASC, hss.`id_homeslider_slides` ASC') ?: [];
        $out = [];
        foreach ($ids as $row) {
            $slide = new Ps_HomeSlide((int) $row['id']);
            $out[] = $full ? $this->slideData($slide, $idShop) : ['id_slide' => (int) $slide->id, 'active' => (bool) $slide->active];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function slideData(Ps_HomeSlide $slide, int $idShop): array
    {
        $link = Context::getContext()->link;
        $translations = [];
        foreach (PiskeeConnectorHelper::languages($idShop) as $iso => $idLang) {
            $image = (string) ($slide->image[$idLang] ?? '');
            $translations[$iso] = [
                'title' => (string) ($slide->title[$idLang] ?? ''),
                'legend' => (string) ($slide->legend[$idLang] ?? ''),
                'description' => (string) ($slide->description[$idLang] ?? ''),
                'url' => (string) ($slide->url[$idLang] ?? ''),
                'image' => $image === '' ? '' : $link->getMediaLink(_MODULE_DIR_ . self::SLIDER . '/images/' . $image),
            ];
        }

        return [
            'id_slide' => (int) $slide->id,
            'position' => $this->slideIndex((int) $slide->id, $idShop),
            'active' => (bool) $slide->active,
            'translations' => $translations,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sliderSettings(int $idShop): array
    {
        return [
            'speed_ms' => (int) self::conf('HOMESLIDER_SPEED', $idShop),
            'pause_on_hover' => (bool) self::conf('HOMESLIDER_PAUSE_ON_HOVER', $idShop),
            'loop' => (bool) self::conf('HOMESLIDER_WRAP', $idShop),
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function banner(int $idShop): array
    {
        $link = Context::getContext()->link;
        $out = [];
        foreach (PiskeeConnectorHelper::languages($idShop) as $iso => $idLang) {
            $image = (string) self::conf('BANNER_IMG', $idShop, $idLang);
            $out[$iso] = [
                'image' => $image === '' ? '' : $link->getMediaLink(_MODULE_DIR_ . self::BANNER . '/img/' . $image),
                'link' => (string) self::conf('BANNER_LINK', $idShop, $idLang),
                'description' => (string) self::conf('BANNER_DESC', $idShop, $idLang),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private function customText(int $idShop): array
    {
        Module::getInstanceByName(self::TEXT);
        $idInfo = (int) CustomText::getCustomTextIdByShop($idShop);
        $out = [];
        $info = $idInfo > 0 ? new CustomText($idInfo) : null;
        foreach (PiskeeConnectorHelper::languages($idShop) as $iso => $idLang) {
            $out[$iso] = $info ? (string) ($info->text[$idLang] ?? '') : '';
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private function social(int $idShop): array
    {
        $out = [];
        foreach (self::NETWORKS as $network => $key) {
            $out[$network] = (string) self::conf($key, $idShop);
        }

        return $out;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function slideInShop(int $idSlide, int $idShop): bool
    {
        return (bool) Db::getInstance()->getValue('SELECT 1 FROM `' . _DB_PREFIX_ . 'homeslider` WHERE `id_homeslider_slides` = ' . $idSlide . ' AND `id_shop` = ' . $idShop);
    }

    private function slideCount(int $idShop): int
    {
        return (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'homeslider` WHERE `id_shop` = ' . $idShop);
    }

    /**
     * @return array<int, int>
     */
    private function slideIds(int $idShop): array
    {
        $rows = Db::getInstance()->executeS('SELECT hss.`id_homeslider_slides` AS id FROM `' . _DB_PREFIX_ . 'homeslider` hs'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'homeslider_slides` hss ON hss.`id_homeslider_slides` = hs.`id_homeslider_slides`'
            . ' WHERE hs.`id_shop` = ' . $idShop . ' ORDER BY hss.`position` ASC, hss.`id_homeslider_slides` ASC') ?: [];

        return array_map(function ($row) {
            return (int) $row['id'];
        }, $rows);
    }

    private function slideIndex(int $idSlide, int $idShop): int
    {
        $index = array_search($idSlide, $this->slideIds($idShop), true);

        return $index === false ? 0 : $index + 1;
    }

    private function placeSlide(int $idSlide, int $position, int $idShop): void
    {
        $ids = array_values(array_filter($this->slideIds($idShop), function ($id) use ($idSlide) {
            return $id !== $idSlide;
        }));
        $position = min(max(1, $position), count($ids) + 1);
        array_splice($ids, $position - 1, 0, [$idSlide]);
        foreach ($ids as $index => $id) {
            Db::getInstance()->update('homeslider_slides', ['position' => $index], '`id_homeslider_slides` = ' . (int) $id);
        }
    }

    /**
     * Downloads a public image and saves it, re-encoded, in a module folder.
     *
     * @return array{name:string, error:string}
     */
    private function storeImage(string $url, string $dir): array
    {
        $download = $this->images()->download($url);
        if ($download['error'] !== '') {
            return ['name' => '', 'error' => $download['error']];
        }
        $info = @getimagesize($download['path']);
        $extension = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? 'jpg';
        $name = 'piskee-' . bin2hex(random_bytes(8)) . '.' . $extension;
        if (!is_dir($dir) || !is_writable($dir)) {
            @unlink($download['path']);

            return ['name' => '', 'error' => 'The folder ' . basename($dir) . ' of the module cannot be written.'];
        }
        // Re-encoding keeps only the picture: nothing hidden in the file survives.
        $ok = ImageManager::resize($download['path'], $dir . $name, null, null, $extension === 'jpg' ? 'jpg' : $extension);
        @unlink($download['path']);
        if (!$ok) {
            return ['name' => '', 'error' => 'PrestaShop could not process this image.'];
        }

        return ['name' => $name, 'error' => ''];
    }

    protected function images(): PiskeeConnectorImages
    {
        return new PiskeeConnectorImages();
    }

    private static function safeLink(string $url): bool
    {
        return (bool) preg_match('#^(https?://[^\s"<>]+|/[^\s"<>]*|\#[^\s"<>]*)$#i', $url);
    }

    private static function moduleReady(string $name): bool
    {
        return Module::isInstalled($name) && Module::isEnabled($name);
    }

    /**
     * @return array<string, mixed>
     */
    private static function missing(string $name, string $label): array
    {
        return PiskeeConnectorHelper::error('The native module "' . $label . '" (' . $name . ') is not installed or is disabled. '
            . 'The merchant can enable it in Modules > Module Manager. If the theme uses a third-party module instead, it is configured in that module\'s own page.');
    }

    /**
     * @return mixed
     */
    private static function conf(string $key, int $idShop, ?int $idLang = null)
    {
        return Configuration::get($key, $idLang, (int) Shop::getGroupFromShop($idShop), $idShop);
    }

    /**
     * @param mixed $value
     */
    private static function setConf(string $key, $value, int $idShop): void
    {
        if (Shop::isFeatureActive()) {
            Configuration::updateValue($key, $value, false, (int) Shop::getGroupFromShop($idShop), $idShop);
        } else {
            Configuration::updateValue($key, $value);
        }
    }

    private static function clearModuleCache(string $name): void
    {
        $module = Module::getInstanceByName($name);
        if ($module && method_exists($module, 'clearCache')) {
            try {
                $module->clearCache();
            } catch (Throwable $e) {
                // Falls back to the global template cache below.
            }
        }
        Tools::clearCache();
    }
}
