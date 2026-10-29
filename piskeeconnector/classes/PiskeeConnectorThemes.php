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

use Symfony\Component\Yaml\Yaml;

/**
 * Theme work, under three rules enforced here and nowhere else:
 *
 * 1. Writes only ever go to a theme that no shop is using. The live theme
 *    and the parent of a live theme are read-only.
 * 2. Only templates, stylesheets and scripts can be written: never PHP,
 *    never the theme configuration.
 * 3. Every write and every removal keeps a backup that can be restored.
 *
 * Putting a draft live is the merchant's own click, in the module page.
 */
class PiskeeConnectorThemes
{
    private const WRITABLE_EXTENSIONS = ['tpl', 'css', 'js'];
    private const READABLE_EXTENSIONS = ['tpl', 'css', 'js', 'yml', 'json'];
    private const WRITABLE_ROOTS = ['templates/', 'modules/', 'assets/css/', 'assets/js/'];
    private const MAX_FILE_BYTES = 524288;

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function themes(): array
    {
        $active = self::activeThemes();
        $out = [];
        foreach (glob(_PS_ALL_THEMES_DIR_ . '*', GLOB_ONLYDIR) ?: [] as $dir) {
            $name = basename($dir);
            $config = self::config($name);
            if ($config === null) {
                continue;
            }
            $parent = (string) ($config['parent'] ?? '');
            $out[] = [
                'name' => $name,
                'display_name' => (string) ($config['display_name'] ?? $name),
                'version' => (string) ($config['version'] ?? ''),
                'parent' => $parent,
                'is_child' => $parent !== '',
                'live_on_shops' => $active[$name] ?? [],
                'editable' => empty($active[$name]) && !self::isParentOfLive($name, $active),
                'created_by_connector' => !empty($config['piskee_connector']),
            ];
        }

        return $out;
    }

    /**
     * True when a live theme inherits from this theme: changing it would change the live shop.
     *
     * @param array<string, array<int, string>> $active
     */
    private static function isParentOfLive(string $name, array $active): bool
    {
        foreach (array_keys($active) as $live) {
            $config = self::config((string) $live);
            if ((string) ($config['parent'] ?? '') === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Theme name => names of the shops using it.
     *
     * @return array<string, array<int, string>>
     */
    public static function activeThemes(): array
    {
        $out = [];
        // No query cache: the page shows the state right after a switch.
        foreach (Db::getInstance()->executeS('SELECT `name`, `theme_name` FROM `' . _DB_PREFIX_ . 'shop` WHERE `deleted` = 0', true, false) ?: [] as $row) {
            $out[(string) $row['theme_name']][] = (string) $row['name'];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function config(string $name): ?array
    {
        if (!self::validName($name)) {
            return null;
        }
        $file = _PS_ALL_THEMES_DIR_ . $name . '/config/theme.yml';
        if (!is_file($file)) {
            return null;
        }
        try {
            $data = Yaml::parseFile($file);
        } catch (Throwable $e) {
            return null;
        }

        return is_array($data) ? $data : null;
    }

    public static function validName(string $name): bool
    {
        return preg_match('/^[a-zA-Z0-9_-]{2,64}$/', $name) === 1;
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function listThemes(array $args, array $context): array
    {
        PiskeeConnectorHelper::boot($context);

        return PiskeeConnectorHelper::json([
            'themes' => self::themes(),
            'rules' => 'Every theme that is not live and is not the parent of a live theme can be modified (editable: true). The live theme is never changed: to change the live design, create a draft child theme, edit it, then ask the merchant to put it live from the Piskee module page in the back office. '
                . 'A stock theme such as classic or hummingbird can be modified when it is not used, but a PrestaShop update may replace its files: a child theme is safer for lasting work.',
        ]);
    }

    /**
     * Creates a draft child theme, either from a parent theme or as a copy of
     * an existing child theme (to keep working on a design already live).
     *
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function createChildTheme(array $args, array $context): array
    {
        PiskeeConnectorHelper::boot($context);
        $source = (string) ($args['source_theme'] ?? '');
        $name = Tools::strtolower((string) ($args['name'] ?? ''));
        $displayName = trim((string) ($args['display_name'] ?? ''));

        $sourceConfig = self::config($source);
        if ($sourceConfig === null) {
            return PiskeeConnectorHelper::error('Theme "' . $source . '" not found. Call list_themes.');
        }
        if (!self::validName($name) || is_dir(_PS_ALL_THEMES_DIR_ . $name)) {
            return PiskeeConnectorHelper::error('name must be 2 to 64 letters, digits, - or _, and not already used by another theme.');
        }

        $parent = (string) ($sourceConfig['parent'] ?? '');
        $isCopyOfChild = $parent !== '';
        if (!$isCopyOfChild) {
            $parent = $source;
        }
        $parentConfig = self::config($parent);
        if ($parentConfig === null) {
            return PiskeeConnectorHelper::error('The parent theme "' . $parent . '" is missing on the server.');
        }

        $dir = _PS_ALL_THEMES_DIR_ . $name . '/';
        if (!@mkdir($dir . 'config', 0755, true) || !@mkdir($dir . 'assets/css', 0755, true) || !@mkdir($dir . 'assets/js', 0755, true)) {
            self::removeTree($dir);

            return PiskeeConnectorHelper::error('The themes folder is not writable on this server.');
        }

        if ($isCopyOfChild) {
            $this->copyTree(_PS_ALL_THEMES_DIR_ . $source . '/', $dir, ['config/theme.yml']);
        }

        // A copy of a child keeps that child's own configuration (layouts,
        // hooks, image types): putting the copy live must not change them.
        // A new child starts from the whole parent configuration, so
        // PrestaShop finds every property it expects.
        $config = $isCopyOfChild ? $sourceConfig : $parentConfig;
        $config['parent'] = $parent;
        $config['name'] = $name;
        $config['display_name'] = $displayName !== '' ? $displayName : ($sourceConfig['display_name'] ?? $parent) . ' (draft)';
        $config['version'] = '1.0.0';
        if (!$isCopyOfChild) {
            $config['assets'] = ['use_parent_assets' => true];
        }
        // A draft never asks PrestaShop to reset, enable or disable modules.
        unset($config['global_settings']['modules']['to_reset'], $config['global_settings']['modules']['to_disable']);
        // PrestaShop's own theme switch (Design > Theme & Logo) moves every module the theme lists to
        // the hooks the theme lists. When the family is live, list the modules exactly where the shop
        // shows them today, so that switch keeps the home page, menu and footer as they are.
        $live = (string) Context::getContext()->shop->theme_name;
        $hooksCopied = false;
        if ($live !== '' && self::themeFamily($live) === $parent) {
            $current = self::liveModuleHooks((int) Context::getContext()->shop->id);
            if ($current !== []) {
                $config['global_settings']['hooks']['modules_to_hook'] = $current;
                $hooksCopied = true;
            }
        }
        $config['piskee_connector'] = ['created' => date('Y-m-d H:i:s'), 'source' => $source];
        if (file_put_contents($dir . 'config/theme.yml', Yaml::dump($config, 6, 2)) === false) {
            self::removeTree($dir);

            return PiskeeConnectorHelper::error('The theme configuration could not be written.');
        }

        foreach (['preview.png', 'preview.jpg'] as $preview) {
            if (is_file(_PS_ALL_THEMES_DIR_ . $parent . '/' . $preview) && !is_file($dir . $preview)) {
                copy(_PS_ALL_THEMES_DIR_ . $parent . '/' . $preview, $dir . $preview);
            }
        }
        // PrestaShop loads the first custom.css / custom.js found (child
        // first), so the child starts from the parent's own customisations.
        foreach (['assets/css/custom.css', 'assets/js/custom.js'] as $file) {
            if (!is_file($dir . $file)) {
                $inherited = _PS_ALL_THEMES_DIR_ . $parent . '/' . $file;
                if (is_file($inherited) && !is_link($inherited)) {
                    @copy($inherited, $dir . $file);
                } else {
                    file_put_contents($dir . $file, "/* Customisations of the " . $name . " child theme. */\n");
                }
            }
        }
        @file_put_contents($dir . 'index.php', "<?php\nheader('Location: ../');\nexit;\n");

        return PiskeeConnectorHelper::json([
            'theme' => $name,
            'parent' => $parent,
            'copied_from' => $isCopyOfChild ? $source : null,
            'live' => false,
            'module_placement' => $hooksCopied ? 'copied from the live shop' : 'from the parent theme',
            'message' => 'Draft child theme created. It is not live: edit it with write_theme_file, then the merchant puts it live from the Piskee module page in the back office '
                . '(it only changes the templates). ' . ($hooksCopied
                    ? 'The modules are listed where the live shop shows them, so a switch from Design > Theme & Logo keeps them in place too.'
                    : 'The live shop uses another theme family: a switch reconfigures modules, so check the home page, menu and footer after it.'),
        ]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function listThemeFiles(array $args, array $context): array
    {
        PiskeeConnectorHelper::boot($context);
        $theme = (string) ($args['theme'] ?? '');
        $config = self::config($theme);
        if ($config === null) {
            return PiskeeConnectorHelper::error('Theme "' . $theme . '" not found.');
        }
        $folder = trim((string) ($args['folder'] ?? 'templates'), '/');
        if (strpos($folder, '..') !== false) {
            return PiskeeConnectorHelper::error('folder cannot contain "..".');
        }

        $own = $this->files($theme, $folder);
        $parent = (string) ($config['parent'] ?? '');
        $inherited = $parent !== '' ? array_values(array_diff($this->files($parent, $folder), $own)) : [];

        return PiskeeConnectorHelper::json([
            'theme' => $theme,
            'folder' => $folder,
            'files_in_this_theme' => array_slice($own, 0, 400),
            'files_inherited_from_parent' => array_slice($inherited, 0, 400),
            'note' => $parent !== '' ? 'A file written in the child theme overrides the parent file with the same path.' : 'This theme is not a child theme: when it is not live and not the parent of a live theme, its own files can be modified (a PrestaShop or theme update may replace them).',
        ]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function readThemeFile(array $args, array $context): array
    {
        PiskeeConnectorHelper::boot($context);
        $theme = (string) ($args['theme'] ?? '');
        $path = ltrim((string) ($args['path'] ?? ''), '/');
        $config = self::config($theme);
        if ($config === null) {
            return PiskeeConnectorHelper::error('Theme "' . $theme . '" not found.');
        }
        if (!$this->safePath($path, self::READABLE_EXTENSIONS)) {
            return PiskeeConnectorHelper::error('This path cannot be read. Allowed: ' . implode(', ', self::READABLE_EXTENSIONS) . ' files inside the theme.');
        }

        $file = $this->resolve($theme, $path);
        $from = $theme;
        $parent = (string) ($config['parent'] ?? '');
        if ($file === null && $parent !== '') {
            $file = $this->resolve($parent, $path);
            $from = $parent;
        }
        if ($file === null) {
            return PiskeeConnectorHelper::error('File "' . $path . '" not found in ' . $theme . ($parent !== '' ? ' nor in its parent ' . $parent : '') . '.');
        }
        if (filesize($file) > self::MAX_FILE_BYTES) {
            return PiskeeConnectorHelper::error('This file is larger than 512 KB and is not returned.');
        }

        return PiskeeConnectorHelper::json([
            'theme' => $theme,
            'path' => $path,
            'read_from' => $from,
            'inherited_from_parent' => $from !== $theme,
            'content' => (string) file_get_contents($file),
        ]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function searchThemeFiles(array $args, array $context): array
    {
        PiskeeConnectorHelper::boot($context);
        $theme = (string) ($args['theme'] ?? '');
        $needle = (string) ($args['text'] ?? '');
        $config = self::config($theme);
        if ($config === null) {
            return PiskeeConnectorHelper::error('Theme "' . $theme . '" not found.');
        }
        if (Tools::strlen($needle) < 2) {
            return PiskeeConnectorHelper::error('text needs at least 2 characters.');
        }

        $parent = (string) ($config['parent'] ?? '');
        $own = $this->files($theme, '');
        $paths = array_fill_keys($own, $theme);
        if ($parent !== '') {
            foreach ($this->files($parent, '') as $path) {
                $paths[$path] = $paths[$path] ?? $parent;
            }
        }

        $hits = [];
        foreach ($paths as $path => $owner) {
            if (!preg_match('/\.(tpl|css|js)$/', $path)) {
                continue;
            }
            $file = $this->resolve($owner, $path);
            if ($file === null || filesize($file) > self::MAX_FILE_BYTES) {
                continue;
            }
            foreach (preg_split('/\R/', (string) file_get_contents($file)) ?: [] as $number => $line) {
                if (stripos($line, $needle) !== false) {
                    $hits[] = ['path' => $path, 'line' => $number + 1, 'from' => $owner, 'text' => Tools::substr(trim($line), 0, 200)];
                    if (count($hits) >= 150) {
                        break 2;
                    }
                }
            }
        }

        return PiskeeConnectorHelper::json(['theme' => $theme, 'text' => $needle, 'matches' => $hits, 'truncated' => count($hits) >= 150]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function writeThemeFile(array $args, array $context): array
    {
        PiskeeConnectorHelper::boot($context);
        $theme = (string) ($args['theme'] ?? '');
        $path = ltrim((string) ($args['path'] ?? ''), '/');
        $content = (string) ($args['content'] ?? '');

        $guard = $this->guardWritable($theme, $path);
        if ($guard !== '') {
            return PiskeeConnectorHelper::error($guard);
        }
        if (strlen($content) > self::MAX_FILE_BYTES) {
            return PiskeeConnectorHelper::error('content is larger than 512 KB.');
        }
        if (preg_match('/<\?(php\b|=|\s)/i', $content) || stripos($content, '{php') !== false) {
            return PiskeeConnectorHelper::error('PHP code is not allowed in theme files.');
        }
        if (substr($path, -4) === '.tpl') {
            $smarty = $this->checkSmarty($content);
            if ($smarty !== '') {
                return PiskeeConnectorHelper::error('Not written, the template looks broken: ' . $smarty);
            }
            $unsafe = self::unsafeSmarty($content);
            if ($unsafe !== '') {
                return PiskeeConnectorHelper::error('Not written: ' . $unsafe . ' Theme templates may only display shop data (variables, {l}, {hook}, {widget}, {include} of theme files, {block}, {if}, {foreach}, safe modifiers).');
            }
        }

        $target = _PS_ALL_THEMES_DIR_ . $theme . '/' . $path;
        if (!is_dir(dirname($target)) && !@mkdir(dirname($target), 0755, true)) {
            return PiskeeConnectorHelper::error('Cannot create the folder for this file.');
        }
        if (!self::insideTheme($theme, $path)) {
            return PiskeeConnectorHelper::error('This path leaves the theme folder (symbolic link) and cannot be written.');
        }
        $existed = is_file($target);
        $backup = $existed ? $this->backup($theme, $path, 'write') : null;
        if (file_put_contents($target, $content) === false) {
            return PiskeeConnectorHelper::error('The file could not be written (permissions).');
        }
        $this->clearCaches();

        return PiskeeConnectorHelper::json([
            'theme' => $theme,
            'path' => $path,
            'written' => true,
            'created' => !$existed,
            'backup_id' => $backup,
            'live' => false,
            'message' => 'Saved in the theme, which is not live. Nothing changed on the live shop.',
        ]);
    }

    /**
     * Removes a file from a draft child theme, so the parent's version is
     * used again.
     *
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function removeThemeFile(array $args, array $context): array
    {
        PiskeeConnectorHelper::boot($context);
        $theme = (string) ($args['theme'] ?? '');
        $path = ltrim((string) ($args['path'] ?? ''), '/');
        $guard = $this->guardWritable($theme, $path);
        if ($guard !== '') {
            return PiskeeConnectorHelper::error($guard);
        }
        $target = _PS_ALL_THEMES_DIR_ . $theme . '/' . $path;
        if (!is_file($target) || !self::insideTheme($theme, $path)) {
            return PiskeeConnectorHelper::error('This file does not exist in this theme.');
        }
        $backup = $this->backup($theme, $path, 'remove');
        @unlink($target);
        $this->clearCaches();

        return PiskeeConnectorHelper::json(['theme' => $theme, 'path' => $path, 'removed' => true, 'backup_id' => $backup]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function listThemeBackups(array $args, array $context): array
    {
        PiskeeConnectorHelper::boot($context);
        $theme = (string) ($args['theme'] ?? '');
        if (!self::validName($theme)) {
            return PiskeeConnectorHelper::error('Invalid theme name.');
        }
        $index = $this->index($theme);
        $path = (string) ($args['path'] ?? '');
        if ($path !== '') {
            $index = array_values(array_filter($index, static function (array $entry) use ($path) {
                return $entry['path'] === $path;
            }));
        }

        return PiskeeConnectorHelper::json(['theme' => $theme, 'backups' => array_slice(array_reverse($index), 0, 100)]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function restoreThemeFile(array $args, array $context): array
    {
        PiskeeConnectorHelper::boot($context);
        $theme = (string) ($args['theme'] ?? '');
        $id = (string) ($args['backup_id'] ?? '');
        $entry = null;
        foreach ($this->index($theme) as $candidate) {
            if ($candidate['id'] === $id) {
                $entry = $candidate;
            }
        }
        if ($entry === null) {
            return PiskeeConnectorHelper::error('Backup "' . $id . '" not found for theme ' . $theme . '.');
        }
        $guard = $this->guardWritable($theme, $entry['path']);
        if ($guard !== '') {
            return PiskeeConnectorHelper::error($guard);
        }

        $content = $this->readBackup($theme, $id);
        if ($content === null) {
            return PiskeeConnectorHelper::error('The backup file is missing.');
        }
        $target = _PS_ALL_THEMES_DIR_ . $theme . '/' . $entry['path'];
        if (!is_dir(dirname($target))) {
            @mkdir(dirname($target), 0755, true);
        }
        if (!self::insideTheme($theme, $entry['path'])) {
            return PiskeeConnectorHelper::error('This path leaves the theme folder (symbolic link) and cannot be written.');
        }
        $current = is_file($target) ? $this->backup($theme, $entry['path'], 'before-restore') : null;
        if (file_put_contents($target, $content) === false) {
            return PiskeeConnectorHelper::error('The file could not be written (permissions).');
        }
        $this->clearCaches();

        return PiskeeConnectorHelper::json(['theme' => $theme, 'path' => $entry['path'], 'restored_from' => $id, 'backup_of_replaced_version' => $current]);
    }

    /**
     * Reads a page of the live shop and lays out its heading structure,
     * title, meta description and breadcrumb.
     *
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function auditPage(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $shop = new Shop($c['id_shop']);
        $host = (string) ($shop->domain_ssl ?: $shop->domain);
        $url = trim((string) ($args['url'] ?? ''));
        if ($url === '' || $url[0] === '/') {
            $url = 'https://' . $host . ($url === '' ? $shop->getBaseURI() : $url);
        }
        if (!function_exists('curl_init')) {
            return PiskeeConnectorHelper::error('The cURL extension of PHP is missing on this server.');
        }

        // Redirects are followed by hand, each hop checked again: only http(s)
        // pages of this shop's own domain, on the standard ports.
        $html = '';
        $status = 0;
        for ($hop = 0; $hop <= 3; ++$hop) {
            $check = self::auditableUrl($url, $host);
            if ($check !== '') {
                return PiskeeConnectorHelper::error($check);
            }
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_PROXY => '',
                CURLOPT_USERAGENT => 'PiskeeForPrestaShop/2.0 (page audit)',
                CURLOPT_HEADER => false,
                CURLOPT_NOPROGRESS => false,
                CURLOPT_PROGRESSFUNCTION => static function ($ch, $total, $received) {
                    return $received > 5242880 ? 1 : 0;
                },
            ]);
            $html = (string) curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $location = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            curl_close($ch);
            if ($status >= 300 && $status < 400 && $location !== '') {
                $url = $location;
                continue;
            }
            break;
        }
        if ($status !== 200 || $html === '') {
            return PiskeeConnectorHelper::error('The page answered HTTP ' . $status . '.');
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $headings = [];
        $previous = 0;
        $issues = [];
        foreach ($xpath->query('//h1|//h2|//h3|//h4|//h5|//h6') ?: [] as $node) {
            $level = (int) substr($node->nodeName, 1);
            $text = trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '');
            $class = $node instanceof DOMElement ? $node->getAttribute('class') : '';
            if ($previous > 0 && $level > $previous + 1) {
                $issues[] = 'h' . $level . ' "' . Tools::substr($text, 0, 60) . '" skips a level after h' . $previous . '.';
            }
            if ($text === '') {
                $issues[] = 'An empty h' . $level . ' is present' . ($class !== '' ? ' (class "' . $class . '")' : '') . '.';
            }
            $headings[] = ['level' => $level, 'text' => Tools::substr($text, 0, 140), 'class' => $class];
            $previous = $level;
        }
        $h1 = count(array_filter($headings, static function (array $h) {
            return $h['level'] === 1;
        }));
        if ($h1 === 0) {
            $issues[] = 'No h1 on the page.';
        } elseif ($h1 > 1) {
            $issues[] = $h1 . ' h1 on the page: keep one.';
        }

        $meta = $xpath->query('//meta[@name="description"]/@content');
        $canonical = $xpath->query('//link[@rel="canonical"]/@href');
        $title = $xpath->query('//title');
        $jsonLdBreadcrumb = stripos($html, '"BreadcrumbList"') !== false;
        $visibleBreadcrumb = ($xpath->query('//*[contains(@class,"breadcrumb")]')->length ?? 0) > 0;
        if (!$visibleBreadcrumb) {
            $issues[] = 'No visible breadcrumb found.';
        }
        if (!$jsonLdBreadcrumb && stripos($html, 'schema.org/BreadcrumbList') === false) {
            $issues[] = 'No BreadcrumbList structured data found.';
        }

        return PiskeeConnectorHelper::json([
            'url' => $url,
            'title' => $title && $title->length ? trim($title->item(0)->textContent) : '',
            'meta_description' => $meta && $meta->length ? (string) $meta->item(0)->nodeValue : '',
            'canonical' => $canonical && $canonical->length ? (string) $canonical->item(0)->nodeValue : '',
            'breadcrumb' => ['visible' => $visibleBreadcrumb, 'structured_data' => $jsonLdBreadcrumb || stripos($html, 'schema.org/BreadcrumbList') !== false],
            'headings' => array_slice($headings, 0, 150),
            'issues' => $issues,
            'note' => 'This is the live theme as visitors see it. A draft theme cannot be audited here until it is live.',
        ]);
    }

    /**
     * Puts a draft live on one shop. Called only from the back office page,
     * by the merchant's own click: never exposed as a tool.
     */
    public static function activate(string $name, int $idShop): string
    {
        if (Shop::isFeatureActive() && Shop::getContext() !== Shop::CONTEXT_SHOP) {
            return 'Select one shop in the back office shop selector first: a theme is put live shop by shop.';
        }
        $config = self::config($name);
        if ($config === null || empty($config['parent'])) {
            return 'Only child themes can be put live from here.';
        }
        $shop = new Shop($idShop);
        $previous = (string) $shop->theme_name;
        if ($previous === $name) {
            return 'This theme is already live.';
        }
        if (self::themeFamily($name) !== self::themeFamily($previous)) {
            return 'This draft is based on "' . self::themeFamily($name) . '", but the live theme is based on "' . self::themeFamily($previous)
                . '". Changing theme family reconfigures modules, hooks and image sizes: do it from Design > Theme & Logo in PrestaShop, after a backup.';
        }
        $error = self::switchTheme($shop, $name);
        if ($error === '') {
            Configuration::updateValue('PISKEECONNECTOR_PREVIOUS_THEME', $previous, false, null, $idShop);
        }

        return $error;
    }

    /**
     * Back to the theme that was live before the last activation from here.
     */
    public static function rollback(int $idShop): string
    {
        if (Shop::isFeatureActive() && Shop::getContext() !== Shop::CONTEXT_SHOP) {
            return 'Select one shop in the back office shop selector first: a theme is put live shop by shop.';
        }
        $previous = (string) Configuration::get('PISKEECONNECTOR_PREVIOUS_THEME', null, null, $idShop);
        if ($previous === '' || self::config($previous) === null) {
            return 'No previous theme is known.';
        }
        $shop = new Shop($idShop);
        if (self::themeFamily($previous) !== self::themeFamily((string) $shop->theme_name)) {
            return 'The live theme was changed outside Piskee since then: go back from Design > Theme & Logo in PrestaShop.';
        }
        $error = self::switchTheme($shop, $previous);
        if ($error === '') {
            Configuration::updateValue('PISKEECONNECTOR_PREVIOUS_THEME', '', false, null, $idShop);
        }

        return $error;
    }

    /**
     * Modules of every display hook of the shop, in their order, as theme.yml lists them.
     *
     * @return array<string, array<int, mixed>>
     */
    private static function liveModuleHooks(int $idShop): array
    {
        $db = Db::getInstance();
        $rows = $db->executeS('SELECT h.`name` AS hook, m.`name` AS module, hm.`id_hook`, hm.`id_module` FROM `' . _DB_PREFIX_ . 'hook_module` hm'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'hook` h ON h.`id_hook` = hm.`id_hook`'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'module` m ON m.`id_module` = hm.`id_module`'
            . " WHERE hm.`id_shop` = " . $idShop . " AND h.`name` LIKE 'display%'"
            . ' ORDER BY h.`name` ASC, hm.`position` ASC') ?: [];
        // Pages where a module is hidden (Design > Positions > exceptions) are part of its placement.
        $exceptions = [];
        foreach ($db->executeS('SELECT `id_hook`, `id_module`, `file_name` FROM `' . _DB_PREFIX_ . 'hook_module_exceptions` WHERE `id_shop` = ' . $idShop) ?: [] as $row) {
            $exceptions[(int) $row['id_hook'] . '-' . (int) $row['id_module']][] = (string) $row['file_name'];
        }
        $out = [];
        foreach ($rows as $row) {
            $key = (int) $row['id_hook'] . '-' . (int) $row['id_module'];
            $out[(string) $row['hook']][] = isset($exceptions[$key])
                ? [(string) $row['module'] => ['except_pages' => array_values(array_unique($exceptions[$key]))]]
                : (string) $row['module'];
        }

        return $out;
    }

    /**
     * The parent theme a theme belongs to (itself for a parent theme).
     */
    private static function themeFamily(string $name): string
    {
        $config = self::config($name);
        $parent = (string) ($config['parent'] ?? '');

        return $parent !== '' ? $parent : $name;
    }

    /**
     * Switches the shop between themes of the same family (a parent and its
     * children). PrestaShop's own theme switch would also reset the modules
     * listed by the theme, rebuild image types and reorder hooks: the
     * merchant only asked for new templates, so only the theme changes.
     */
    private static function switchTheme(Shop $shop, string $name): string
    {
        $employee = Context::getContext()->employee;
        if (!$employee || !$employee->can('edit', 'AdminThemes')) {
            return 'Your employee profile is not allowed to change the theme.';
        }
        if (!self::validName($name) || !is_file(_PS_ALL_THEMES_DIR_ . $name . '/config/theme.yml')) {
            return 'Theme "' . $name . '" not found.';
        }
        // The page layouts the merchant chose (Design > Theme & Logo) are kept
        // in a per-shop file of the live theme: carry them over.
        $previous = (string) $shop->theme_name;
        $settings = null;
        $previousFile = _PS_CONFIG_DIR_ . 'themes/' . $previous . '/shop' . (int) $shop->id . '.json';
        if (self::validName($previous) && is_file($previousFile)) {
            $previousData = json_decode((string) file_get_contents($previousFile), true);
            $settings = is_array($previousData) ? ($previousData['theme_settings'] ?? null) : null;
        }

        $shop->theme_name = $name;
        if (!$shop->update()) {
            return 'PrestaShop refused to change the theme of this shop.';
        }
        $file = _PS_CONFIG_DIR_ . 'themes/' . $name . '/shop' . (int) $shop->id . '.json';
        @unlink($file);
        $config = self::config($name);
        if (is_array($config) && is_array($settings)) {
            $config['theme_settings'] = $settings;
            if (!is_dir(dirname($file))) {
                @mkdir(dirname($file), 0755, true);
            }
            @file_put_contents($file, json_encode($config));
        }
        Tools::clearSmartyCache();
        Media::clearCache();
        if (method_exists('Tools', 'clearSf2Cache')) {
            try {
                Tools::clearSf2Cache();
            } catch (Throwable $e) {
            }
        }

        return '';
    }

    private function guardWritable(string $theme, string $path): string
    {
        $config = self::config($theme);
        if ($config === null) {
            return 'Theme "' . $theme . '" not found.';
        }
        $active = self::activeThemes();
        if (!empty($active[$theme])) {
            return 'Theme "' . $theme . '" is live on ' . implode(', ', $active[$theme]) . ' and cannot be modified. Create a draft copy with create_child_theme (source_theme = "' . $theme . '").';
        }
        // A folder that is a link, or that is the same folder as a live
        // theme (or as the parent of a live theme), is never a draft.
        $link = _PS_ALL_THEMES_DIR_ . $theme;
        $real = realpath($link);
        if ($real === false || is_link($link) || dirname($real) !== realpath(_PS_ALL_THEMES_DIR_)) {
            return 'Theme "' . $theme . '" is a link to another folder and cannot be modified.';
        }
        foreach (array_keys($active) as $live) {
            $liveConfig = self::config((string) $live);
            $liveParent = (string) ($liveConfig['parent'] ?? '');
            if ($liveParent === $theme) {
                return 'Theme "' . $theme . '" is the parent of the live theme "' . $live . '": changing it would change the live shop.';
            }
            foreach ([(string) $live, $liveParent] as $used) {
                if ($used !== '' && self::validName($used) && realpath(_PS_ALL_THEMES_DIR_ . $used) === $real) {
                    return 'Theme "' . $theme . '" points to the folder of a live theme and cannot be modified.';
                }
            }
        }
        if (preg_match('/\.(php\d?|phtml|phar|pht|phps)(\.|$)/i', $path)) {
            return 'This file name is not allowed.';
        }
        if (!$this->safePath($path, self::WRITABLE_EXTENSIONS)) {
            return 'This path cannot be written. Allowed: .tpl, .css and .js files under ' . implode(', ', self::WRITABLE_ROOTS);
        }
        $allowed = false;
        foreach (self::WRITABLE_ROOTS as $root) {
            if (strpos($path, $root) === 0) {
                $allowed = true;
            }
        }

        return $allowed ? '' : 'Files can only be written under ' . implode(', ', self::WRITABLE_ROOTS);
    }

    /**
     * @param array<int, string> $extensions
     */
    private function safePath(string $path, array $extensions): bool
    {
        if ($path === '' || strpos($path, '..') !== false || strpos($path, "\0") !== false || strpos($path, '\\') !== false) {
            return false;
        }
        if (!preg_match('#^[A-Za-z0-9_./-]+$#', $path)) {
            return false;
        }

        return in_array(Tools::strtolower(pathinfo($path, PATHINFO_EXTENSION)), $extensions, true);
    }

    private function resolve(string $theme, string $path): ?string
    {
        $root = realpath(_PS_ALL_THEMES_DIR_ . $theme);
        $file = realpath(_PS_ALL_THEMES_DIR_ . $theme . '/' . $path);
        if ($root === false || $file === false || !is_file($file) || strncmp($file, $root . '/', strlen($root) + 1) !== 0) {
            return null;
        }

        return $file;
    }

    /**
     * Relative paths of the readable files of a theme under a folder.
     *
     * @return array<int, string>
     */
    private function files(string $theme, string $folder): array
    {
        $root = _PS_ALL_THEMES_DIR_ . $theme . '/';
        $start = $root . ($folder !== '' ? $folder . '/' : '');
        $realRoot = realpath($root);
        $realStart = realpath($start);
        if (!is_dir($start) || $realRoot === false || $realStart === false || ($realStart !== $realRoot && strncmp($realStart, $realRoot . '/', strlen($realRoot) + 1) !== 0)) {
            return [];
        }
        $out = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($start, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($root));
            if (strpos($relative, 'node_modules/') !== false || strpos($relative, '_dev/') === 0) {
                continue;
            }
            if (in_array(Tools::strtolower($file->getExtension()), self::READABLE_EXTENSIONS, true)) {
                $out[] = $relative;
            }
            if (count($out) >= 3000) {
                break;
            }
        }
        sort($out);

        return $out;
    }

    /**
     * Rejects a template whose Smarty blocks do not close.
     */
    private function checkSmarty(string $content): string
    {
        $stripped = preg_replace('/\{\*.*?\*\}/s', '', $content) ?? $content;
        $stripped = preg_replace('/\{literal\}.*?\{\/literal\}/s', '', $stripped) ?? $stripped;
        foreach (['block', 'if', 'foreach', 'for', 'capture', 'function', 'strip'] as $tag) {
            $open = preg_match_all('/\{' . $tag . '[\s}]/', $stripped);
            $close = preg_match_all('/\{\/' . $tag . '\}/', $stripped);
            if ($open !== $close) {
                return $open . ' {' . $tag . '} opened but ' . $close . ' {/' . $tag . '} closed.';
            }
        }

        return '';
    }

    /**
     * Backups are stored as .php files that stop at their first line: even
     * on a web server that ignores .htaccess (nginx), they cannot be read
     * from the web.
     */
    private const GUARD = "<?php exit; ?>\n";

    private function backupDir(string $theme): string
    {
        $base = _PS_ROOT_DIR_ . '/var/piskeeconnector/';
        $dir = $base . 'backups/' . $theme . '/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        foreach ([$base, $base . 'backups/', $dir] as $folder) {
            if (is_dir($folder) && !is_file($folder . 'index.php')) {
                @file_put_contents($folder . 'index.php', "<?php\nheader('HTTP/1.1 403 Forbidden');\nexit;\n");
            }
        }
        if (!is_file($base . '.htaccess')) {
            @file_put_contents($base . '.htaccess', "Require all denied\nDeny from all\n");
        }

        return $dir;
    }

    private function backup(string $theme, string $path, string $reason): ?string
    {
        $source = _PS_ALL_THEMES_DIR_ . $theme . '/' . $path;
        if (!is_file($source) || is_link($source)) {
            return null;
        }
        $id = date('Ymd-His') . '-' . bin2hex(random_bytes(6));
        $dir = $this->backupDir($theme);
        $content = file_get_contents($source);
        if ($content === false || @file_put_contents($dir . $id . '.bak.php', self::GUARD . $content) === false) {
            return null;
        }
        $index = $this->index($theme);
        $index[] = ['id' => $id, 'path' => $path, 'reason' => $reason, 'date' => date('Y-m-d H:i:s'), 'bytes' => strlen($content)];
        file_put_contents($dir . 'index.json.php', self::GUARD . json_encode(array_slice($index, -500)));

        return $id;
    }

    private function readBackup(string $theme, string $id): ?string
    {
        if (!preg_match('/^[0-9]{8}-[0-9]{6}-[0-9a-f]{8,12}$/', $id)) {
            return null;
        }
        $dir = $this->backupDir($theme);
        if (is_file($dir . $id . '.bak.php')) {
            $raw = (string) file_get_contents($dir . $id . '.bak.php');

            return strpos($raw, self::GUARD) === 0 ? substr($raw, strlen(self::GUARD)) : null;
        }
        // Backups written by 2.0.0.
        return is_file($dir . $id . '.bak') ? (string) file_get_contents($dir . $id . '.bak') : null;
    }

    /**
     * @return array<int, array{id:string, path:string, reason:string, date:string, bytes:int}>
     */
    private function index(string $theme): array
    {
        if (!self::validName($theme)) {
            return [];
        }
        $dir = $this->backupDir($theme);
        if (is_file($dir . 'index.json.php')) {
            $raw = (string) file_get_contents($dir . 'index.json.php');
            $data = json_decode(strpos($raw, self::GUARD) === 0 ? substr($raw, strlen(self::GUARD)) : '', true);
        } elseif (is_file($dir . 'index.json')) {
            $data = json_decode((string) file_get_contents($dir . 'index.json'), true);
            @unlink($dir . 'index.json');
        } else {
            $data = [];
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Checks that a path, once links are resolved, stays inside the theme.
     */
    private static function insideTheme(string $theme, string $path): bool
    {
        $themes = realpath(_PS_ALL_THEMES_DIR_);
        $link = rtrim(_PS_ALL_THEMES_DIR_, '/') . '/' . $theme;
        $root = realpath($link);
        if ($themes === false || $root === false || is_link($link) || dirname($root) !== $themes) {
            return false;
        }
        $target = $root . '/' . $path;
        if (is_link($target)) {
            return false;
        }
        $dir = dirname($target);
        while (!file_exists($dir) && $dir !== dirname($dir)) {
            $dir = dirname($dir);
        }
        $real = realpath($dir);

        return $real !== false && ($real === $root || strncmp($real, $root . '/', strlen($root) + 1) === 0);
    }

    private static function auditableUrl(string $url, string $host): string
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return 'This address is not valid.';
        }
        $scheme = Tools::strtolower((string) ($parts['scheme'] ?? ''));
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        // The shop domain may carry its own port (shop.example:8080): that one is allowed too.
        $shopHost = Tools::strtolower((string) (parse_url('http://' . $host, PHP_URL_HOST) ?: $host));
        $shopPort = (int) (parse_url('http://' . $host, PHP_URL_PORT) ?: 0);
        $ports = $shopPort > 0 ? [80, 443, $shopPort] : [80, 443];
        if (!in_array($scheme, ['http', 'https'], true) || !in_array($port, $ports, true) || isset($parts['user']) || isset($parts['pass'])) {
            return 'Only http(s) pages of this shop, on the standard ports, can be audited.';
        }
        if (Tools::strtolower((string) ($parts['host'] ?? '')) !== $shopHost) {
            return 'Only pages of this shop (' . $host . ') can be audited.';
        }

        return '';
    }

    /**
     * Smarty can call PHP functions, read files and constants from a
     * template. Theme templates written by an assistant may only display
     * shop data: anything else is refused before the file is written.
     */
    public static function unsafeSmarty(string $content): string
    {
        $code = preg_replace('/\{\*.*?\*\}/s', '', $content) ?? $content;
        $code = preg_replace('/\{literal\}.*?\{\/literal\}/s', '', $code) ?? $code;

        $rules = [
            '/\{\s*\/?\s*(fetch|eval|include_php|insert|php|math|config_load)\b/i' => 'the {fetch}, {eval}, {insert}, {php}, {math} and {config_load} tags are not allowed.',
            '/\$smarty\s*\.\s*(server|env|cookies|session|template_object|current_dir|template)\b|\$smarty\s*\.\s*const\s*\.\s*(?!(M_PI|PHP_EOL)\b)/i' => 'server variables and constants cannot be read from a template.',
            '/::(?![A-Z][A-Z0-9_]*\b(?!\s*\())/' => 'calls to PHP classes are not allowed (only class constants can be read).',
            '/\bfile\s*=\s*["\']?\s*(\/|[a-z]:|file:|eval:|string:|[a-z0-9+.-]+:\/\/|[^"\'\s}]*\.\.)/i' => 'templates can only include files of the theme (no absolute path, "..", or file:/eval:/string: resource).',
            '/\$_(GET|POST|SERVER|ENV|COOKIE|SESSION|REQUEST|FILES)\b|\$GLOBALS\b/' => 'PHP super globals cannot be read.',
            '/\b(assign|append)\s+var\s*=\s*["\']?smarty/i' => 'Smarty internals cannot be changed.',
        ];
        foreach ($rules as $pattern => $message) {
            if (preg_match($pattern, $code)) {
                return ucfirst($message);
            }
        }

        // Inside {...} tags: every function call and every modifier must be
        // on the allow list.
        $allowedFunctions = ['if', 'elseif', 'while', 'and', 'or', 'not', 'strpos', 'stripos', 'mt_rand', 'rand', 'stripslashes', 'file_exists', 'array_merge', 'ucwords', 'lcfirst', 'uniqid', 'str_pad', 'array_search', 'array_key_first', 'array_key_last', 'array_reverse', 'array_sum', 'array_unique', 'array_flip', 'array_column', 'is_float', 'http_build_query', 'urlencode', 'htmlspecialchars', 'str_repeat', 'strip_tags', 'mb_strlen', 'mb_substr', 'mb_strtolower', 'mb_strtoupper', 'nl2br', 'isset', 'empty', 'count', 'sizeof', 'in_array', 'is_array', 'array_key_exists', 'is_string', 'is_numeric', 'intval', 'floatval', 'strval', 'trim', 'strtolower', 'strtoupper', 'ucfirst', 'strlen', 'max', 'min', 'round', 'implode', 'nl2br', 'json_encode', 'array_keys', 'array_values', 'reset', 'end', 'current', 'key', 'substr', 'str_replace', 'sprintf', 'number_format', 'date', 'time', 'strtotime', 'mktime', 'gmdate', 'checkdate', 'abs', 'ceil', 'floor', 'is_null', 'is_bool', 'is_int', 'is_object', 'array_slice', 'range', 'explode'];
        $allowedModifiers = ['strpos', 'stripos', 'mt_rand', 'rand', 'date', 'strtotime', 'gmdate', 'ucwords', 'lcfirst', 'array_merge', 'array_reverse', 'str_pad', 'array_sum', 'array_unique', 'is_array', 'in_array', 'isset', 'empty', 'escape', 'default', 'truncate', 'strip_tags', 'replace', 'upper', 'lower', 'capitalize', 'count', 'count_characters', 'count_words', 'date_format', 'nl2br', 'spacify', 'string_format', 'strip', 'wordwrap', 'indent', 'regex_replace', 'cat', 'json_encode', 'intval', 'floatval', 'round', 'trim', 'implode', 'array_slice', 'number_format', 'urlencode', 'rawurlencode', 'htmlspecialchars', 'nofilter', 'classname', 'classnames', 'cleanHtml', 'unescape', 'strtolower', 'strtoupper', 'ucfirst', 'abs', 'str_replace', 'substr', 'sprintf', 'mb_strtolower', 'mb_strtoupper', 'mb_substr', 'json_decode', 'formatPrice', 'convertAndFormatPrice', 'boolval', 'strval', 'array_values', 'array_keys', 'implode', 'reset', 'end', 'max', 'min', 'sizeof', 'in_array', 'nl2br', 'ceil', 'floor', 'html_entity_decode', 'addslashes', 'stripslashes', 'md5', 'base64_encode', 'strlen'];
        if (preg_match_all('/\{(?!\s)([^{}]*)\}/', $code, $tags)) {
            foreach ($tags[1] as $tag) {
                $inner = preg_replace('/(["\'])(?:\\\\.|(?!\1).)*\1/s', '""', $tag) ?? $tag;
                if (preg_match_all('/\|\s*@?([A-Za-z_][A-Za-z0-9_]*)/', $inner, $mods)) {
                    foreach ($mods[1] as $modifier) {
                        if (!in_array($modifier, $allowedModifiers, true)) {
                            return 'the modifier "|' . $modifier . '" is not allowed.';
                        }
                    }
                }
                if (preg_match_all('/(?<![\w$.>\]])([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $inner, $calls)) {
                    foreach ($calls[1] as $function) {
                        if (!in_array(Tools::strtolower($function), $allowedFunctions, true)) {
                            return 'the function "' . $function . '()" is not allowed in a template.';
                        }
                    }
                }
                if (preg_match('/(\$\w+|\]|\.\w+)\s*\(/', $inner)) {
                    return 'calling a variable as a function is not allowed.';
                }
                if (preg_match('/[`]/', $inner)) {
                    return 'backticks are not allowed in a template tag.';
                }
            }
        }

        return '';
    }

    private static function removeTree(string $dir): void
    {
        if (!is_dir($dir) || is_link(rtrim($dir, '/'))) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }

    /**
     * @param array<int, string> $skip relative paths not to copy
     */
    private function copyTree(string $from, string $to, array $skip): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($from));
            if (in_array($relative, $skip, true) || $item->isLink()) {
                continue;
            }
            if ($item->isDir()) {
                @mkdir($to . $relative, 0755, true);
            } else {
                @copy($item->getPathname(), $to . $relative);
            }
        }
    }

    private function clearCaches(): void
    {
        Tools::clearSmartyCache();
        Media::clearCache();
    }
}
