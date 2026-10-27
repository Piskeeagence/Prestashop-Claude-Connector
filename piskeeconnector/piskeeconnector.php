<?php
/**
 * Claude Connector by Piskee
 *
 * Connects a PrestaShop store to Claude (and any assistant speaking the Model
 * Context Protocol) with its own MCP server and OAuth 2.1 authorization
 * server: catalogue, combinations, images, stock, customer service, sales
 * figures and draft child themes.
 *
 * Independent module, not affiliated with PrestaShop SA or Anthropic, PBC.
 *
 * @author    Piskee <louisalexandre@piskee.com>
 * @copyright 2026 Piskee
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License v3.0 (AFL-3.0)
 */
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/PiskeeConnectorBrand.php';
require_once __DIR__ . '/classes/PiskeeConnectorI18n.php';
require_once __DIR__ . '/classes/PiskeeConnectorHelper.php';
require_once __DIR__ . '/classes/PiskeeConnectorLicense.php';
require_once __DIR__ . '/classes/PiskeeConnectorCatalog.php';
require_once __DIR__ . '/classes/PiskeeConnectorVariants.php';
require_once __DIR__ . '/classes/PiskeeConnectorMerchandising.php';
require_once __DIR__ . '/classes/PiskeeConnectorImages.php';
require_once __DIR__ . '/classes/PiskeeConnectorOperations.php';
require_once __DIR__ . '/classes/PiskeeConnectorOrders.php';
require_once __DIR__ . '/classes/PiskeeConnectorCms.php';
require_once __DIR__ . '/classes/PiskeeConnectorThemes.php';
require_once __DIR__ . '/classes/PiskeeConnectorTools.php';
require_once __DIR__ . '/classes/PiskeeConnectorOAuth.php';
require_once __DIR__ . '/classes/PiskeeConnectorMcp.php';
require_once __DIR__ . '/classes/PiskeeConnectorHttp.php';
require_once __DIR__ . '/classes/PiskeeConnectorConsent.php';

class Piskeeconnector extends Module
{
    private const CONSENT_TAB = 'AdminPiskeeConnectorConsent';

    public function __construct()
    {
        $this->name = 'piskeeconnector';
        $this->tab = 'administration';
        $this->version = '2.0.3';
        $this->author = 'Piskee';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => '9.99.99'];

        parent::__construct();

        $fr = isset($this->context->language) && Tools::strtolower((string) $this->context->language->iso_code) === 'fr';
        $this->displayName = $fr ? 'Piskee, l\'assistant IA de votre boutique (compatible Claude)' : 'Piskee, the AI assistant for your shop (works with Claude)';
        $this->description = $fr
            ? 'Pilotez votre boutique avec Claude : catalogue, déclinaisons, images, stocks, service client et thème enfant. Module indépendant, non officiel.'
            : 'Run your shop with Claude: catalogue, combinations, images, stock, customer service and child theme. Independent, unofficial module.';
        $this->confirmUninstall = $fr
            ? 'Tous les assistants connectés seront déconnectés. Continuer ?'
            : 'Every connected assistant will be disconnected. Continue?';
    }

    public function install(): bool
    {
        $ok = parent::install()
            && $this->installDatabase()
            && $this->installTab()
            && $this->registerHook('moduleRoutes')
            && Configuration::updateValue('PISKEECONNECTOR_ENABLED', 1)
            && Configuration::updateValue('PISKEECONNECTOR_ADMIN_DIR', $this->adminDir());
        if ($ok) {
            self::refreshRoutes();
        }

        return $ok;
    }

    /**
     * The consent page is a back office route declared in config/routes.yml:
     * PrestaShop only sees a new route once its Symfony cache is rebuilt.
     */
    public static function refreshRoutes(): void
    {
        try {
            Tools::clearSf2Cache();
        } catch (Throwable $e) {
            // The merchant can still clear the cache from Advanced parameters > Performance.
        }
    }

    public function uninstall(): bool
    {
        $db = Db::getInstance();
        foreach (['client', 'request', 'code', 'token'] as $table) {
            $db->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'piskeeconnector_' . $table . '`');
        }
        $idTab = (int) Tab::getIdFromClassName(self::CONSENT_TAB);
        if ($idTab > 0) {
            (new Tab($idTab))->delete();
        }
        // The licence key and the monthly usage counter are kept: reinstalling
        // must neither lose a paid licence nor reset the free quota.
        foreach (['PISKEECONNECTOR_ENABLED', 'PISKEECONNECTOR_ADMIN_DIR', 'PISKEECONNECTOR_PREVIOUS_THEME'] as $key) {
            Configuration::deleteByName($key);
        }

        return parent::uninstall();
    }

    /**
     * Discovery addresses at the root of the site, as RFC 8414 and RFC 9728
     * place them for an issuer and a resource that have a path.
     *
     * @return array<string, array<string, mixed>>
     */
    public function hookModuleRoutes(): array
    {
        $route = static function (string $rule, string $type): array {
            return [
                'controller' => 'metadata',
                'rule' => $rule,
                'keywords' => [],
                'params' => ['fc' => 'module', 'module' => 'piskeeconnector', 'controller' => 'metadata', 'type' => $type],
            ];
        };

        return [
            'module-piskeeconnector-wk-server' => $route('.well-known/oauth-authorization-server/module/piskeeconnector', 'server'),
            'module-piskeeconnector-wk-openid' => $route('.well-known/openid-configuration/module/piskeeconnector', 'server'),
            'module-piskeeconnector-wk-server-suffix' => $route('module/piskeeconnector/.well-known/oauth-authorization-server', 'server'),
            'module-piskeeconnector-wk-openid-suffix' => $route('module/piskeeconnector/.well-known/openid-configuration', 'server'),
            'module-piskeeconnector-wk-resource' => $route('.well-known/oauth-protected-resource/module/piskeeconnector/mcp', 'resource'),
        ];
    }

    public function getContent(): string
    {
        // The authorization endpoint needs the back office folder to send the
        // merchant to the consent screen; it is only known from here.
        if ((string) Configuration::get('PISKEECONNECTOR_ADMIN_DIR') !== $this->adminDir()) {
            Configuration::updateValue('PISKEECONNECTOR_ADMIN_DIR', $this->adminDir());
        }
        if (!Tab::getIdFromClassName(self::CONSENT_TAB)) {
            $this->installTab();
        }
        PiskeeConnectorOAuth::purge();

        $lang = $this->uiLang();
        $t = PiskeeConnectorI18n::ui($lang);
        $idShop = (int) $this->context->shop->id;
        $notice = '';
        $noticeType = 'success';
        $test = null;

        if (Tools::isSubmit('piskee_action')) {
            if (!hash_equals($this->csrfToken(), (string) Tools::getValue('piskee_token'))) {
                $notice = 'Invalid security token. Reload the page and try again.';
                $noticeType = 'error';
            } else {
                [$notice, $noticeType, $test] = $this->handleAction((string) Tools::getValue('piskee_action'), $idShop, $t);
            }
        }

        $checks = $this->checks($t);
        $ready = !in_array(false, array_column($checks, 'ok'), true);

        $groups = [];
        foreach (PiskeeConnectorTools::summary() as $name => $info) {
            $groups[$info['group']]['label'] = PiskeeConnectorI18n::groupLabel($info['group'], $lang);
            $groups[$info['group']]['tools'][] = [
                'name' => $name,
                'title' => PiskeeConnectorI18n::toolTitle($name, $lang),
                'writes' => $info['writes'],
            ];
        }

        $themes = [];
        foreach (PiskeeConnectorThemes::themes() as $theme) {
            $theme['live_here'] = (string) $this->context->shop->theme_name === $theme['name'];
            $themes[] = $theme;
        }

        $docs = [];
        foreach (['fr' => 'docs/README.fr.md', 'en' => 'docs/README.en.md'] as $code => $file) {
            $docs[$code] = is_file(__DIR__ . '/' . $file) ? $this->markdown((string) file_get_contents(__DIR__ . '/' . $file)) : '';
        }

        $used = PiskeeConnectorLicense::used($idShop);
        $this->context->smarty->assign([
            'pk' => [
                't' => $t,
                'lang' => $lang,
                'switch_url' => $this->pageUrl(['pklang' => $lang === 'fr' ? 'en' : 'fr']),
                'action_url' => $this->pageUrl([]),
                'token' => $this->csrfToken(),
                'notice' => $notice,
                'notice_type' => $noticeType,
                'test' => $test,
                'version' => $this->version,
                'checks' => $checks,
                'ready' => $ready,
                'enabled' => (bool) Configuration::get('PISKEECONNECTOR_ENABLED'),
                'mcp_url' => PiskeeConnectorOAuth::resource(),
                'connections' => PiskeeConnectorOAuth::connections(),
                'groups' => $groups,
                'tool_count' => count(PiskeeConnectorTools::summary()),
                'themes' => $themes,
                'previous_theme' => (string) Configuration::get('PISKEECONNECTOR_PREVIOUS_THEME', null, null, $idShop),
                'plan' => [
                    'license' => PiskeeConnectorLicense::status($idShop),
                    'used' => $used,
                    'allowance' => PiskeeConnectorLicense::FREE_MONTHLY_WRITES,
                    'percent' => min(100, (int) round($used * 100 / PiskeeConnectorLicense::FREE_MONTHLY_WRITES)),
                    'price' => $lang === 'fr' ? PiskeeConnectorLicense::PRO_PRICE : PiskeeConnectorLicense::PRO_PRICE_EN,
                    'onboarding' => $lang === 'fr' ? PiskeeConnectorLicense::ONBOARDING_PRICE : PiskeeConnectorLicense::ONBOARDING_PRICE_EN,
                ],
                'docs' => $docs,
                'logo' => $this->_path . 'views/img/piskee-white.png',
                'logo_olive' => $this->_path . 'views/img/piskee-olive.png',
                'brand' => [
                    'website' => PiskeeConnectorBrand::link(PiskeeConnectorBrand::WEBSITE, 'about'),
                    'contact' => PiskeeConnectorBrand::link(PiskeeConnectorBrand::CONTACT_URL, 'help'),
                    'contact_pro' => PiskeeConnectorBrand::link(PiskeeConnectorBrand::CONTACT_URL, 'pro'),
                    'whatsapp' => PiskeeConnectorBrand::WHATSAPP_URL,
                    'phone' => PiskeeConnectorBrand::PHONE,
                    'email' => PiskeeConnectorBrand::EMAIL,
                    'linkedin' => PiskeeConnectorBrand::LINKEDIN_URL,
                    'repo' => PiskeeConnectorBrand::REPOSITORY_URL,
                ],
            ],
        ]);

        $this->context->controller->addCSS($this->_path . 'views/css/admin.css?v=' . $this->version);
        $this->context->controller->addJS($this->_path . 'views/js/admin.js?v=' . $this->version);

        return $this->display(__FILE__, 'views/templates/admin/configure.tpl');
    }

    /**
     * @param array<string, string> $t
     *
     * @return array{0:string, 1:string, 2:array<int, array<string, mixed>>|null}
     */
    private function handleAction(string $action, int $idShop, array $t): array
    {
        switch ($action) {
            case 'toggle_server':
                $on = !Configuration::get('PISKEECONNECTOR_ENABLED');
                Configuration::updateValue('PISKEECONNECTOR_ENABLED', $on ? 1 : 0);

                return [$on ? $t['server_on_done'] : $t['server_off_done'], 'success', null];
            case 'run_test':
                $test = $this->selfTest();
                $ok = !in_array(false, array_column($test, 'ok'), true);

                return [$ok ? $t['test_ok'] : $t['test_ko'], $ok ? 'success' : 'error', $test];
            case 'revoke':
                $family = (string) Tools::getValue('family');
                if (preg_match('/^[A-Za-z0-9_-]{16,64}$/', $family)) {
                    PiskeeConnectorOAuth::revokeFamily($family);
                }

                return [$t['revoked'], 'success', null];
            case 'save_license':
                $status = PiskeeConnectorLicense::save((string) Tools::getValue('license_key'), $idShop);
                if ($status['valid']) {
                    return [$t['license_ok'], 'success', null];
                }
                if ($status['error'] === '') {
                    return [$t['license_removed'], 'success', null];
                }

                return [$t['license_error_' . $status['error']] ?? $t['license_error_malformed'], 'error', null];
            case 'activate_theme':
                $error = PiskeeConnectorThemes::activate((string) Tools::getValue('theme'), $idShop);

                return $error === '' ? [$t['theme_done'], 'success', null] : [$error, 'error', null];
            case 'rollback_theme':
                $error = PiskeeConnectorThemes::rollback($idShop);

                return $error === '' ? [$t['rollback_done'], 'success', null] : [$error, 'error', null];
        }

        return ['', 'success', null];
    }

    /**
     * @param array<string, string> $t
     *
     * @return array<int, array{key:string, label:string, ok:bool}>
     */
    private function checks(array $t): array
    {
        return [
            ['key' => 'server', 'label' => $t['check_server'], 'ok' => (bool) Configuration::get('PISKEECONNECTOR_ENABLED')],
            ['key' => 'https', 'label' => $t['check_https'], 'ok' => (bool) Configuration::get('PS_SSL_ENABLED')],
            ['key' => 'rewrite', 'label' => $t['check_rewrite'], 'ok' => (bool) Configuration::get('PS_REWRITING_SETTINGS')],
            ['key' => 'admin', 'label' => $t['check_admin'], 'ok' => (string) Configuration::get('PISKEECONNECTOR_ADMIN_DIR') !== '' && (bool) Tab::getIdFromClassName(self::CONSENT_TAB)],
        ];
    }

    /**
     * Calls the shop's own public addresses from the server, as an assistant
     * would.
     *
     * @return array<int, array{label:string, ok:bool, detail:string}>
     */
    private function selfTest(): array
    {
        $base = PiskeeConnectorOAuth::baseUrl();
        $probe = static function (string $url, string $method = 'GET') {
            if (!function_exists('curl_init')) {
                return [0, '', ''];
            }
            $headers = [];
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_USERAGENT => 'ClaudeConnectorByPiskee/2.0 (self-test)',
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
                CURLOPT_HEADERFUNCTION => static function ($ch, $line) use (&$headers) {
                    $pair = explode(':', $line, 2);
                    if (count($pair) === 2) {
                        $headers[strtolower(trim($pair[0]))] = trim($pair[1]);
                    }

                    return strlen($line);
                },
            ]);
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, '{"jsonrpc":"2.0","id":1,"method":"ping"}');
            }
            $body = (string) curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);

            return [$status, $body, (string) ($headers['www-authenticate'] ?? '')];
        };

        $out = [];
        [$s, $b] = $probe($base . '/.well-known/oauth-protected-resource/module/piskeeconnector/mcp');
        $json = json_decode($b, true);
        $out[] = ['label' => 'Protected resource metadata (RFC 9728)', 'ok' => $s === 200 && is_array($json) && ($json['resource'] ?? '') === PiskeeConnectorOAuth::resource(), 'detail' => 'HTTP ' . $s];
        [$s, $b] = $probe($base . '/.well-known/oauth-authorization-server/module/piskeeconnector');
        $json = json_decode($b, true);
        $out[] = ['label' => 'Authorization server metadata (RFC 8414)', 'ok' => $s === 200 && is_array($json) && ($json['issuer'] ?? '') === PiskeeConnectorOAuth::issuer(), 'detail' => 'HTTP ' . $s];
        [$s, , $auth] = $probe(PiskeeConnectorOAuth::resource(), 'POST');
        $out[] = ['label' => 'MCP endpoint asks for authentication', 'ok' => $s === 401 && stripos($auth, 'resource_metadata') !== false, 'detail' => 'HTTP ' . $s . ($s === 401 && $auth === '' ? ' (WWW-Authenticate header missing)' : '')];
        // Without a back office session the consent page sends to the login page.
        [$s] = $probe($base . '/' . $this->adminDir() . '/index.php/modules/piskeeconnector/consent');
        $out[] = ['label' => 'Consent page of the back office', 'ok' => in_array($s, [200, 301, 302, 303], true), 'detail' => 'HTTP ' . $s . ($s === 404 ? ' (clear the cache in Advanced parameters > Performance)' : '')];

        return $out;
    }

    private function installDatabase(): bool
    {
        $p = _DB_PREFIX_;
        $engine = _MYSQL_ENGINE_;
        $queries = [
            "CREATE TABLE IF NOT EXISTS `{$p}piskeeconnector_client` (
                `id_client` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `client_id` VARCHAR(64) NOT NULL,
                `client_secret_hash` CHAR(64) NOT NULL DEFAULT '',
                `client_name` VARCHAR(128) NOT NULL DEFAULT '',
                `client_uri` VARCHAR(255) NOT NULL DEFAULT '',
                `redirect_uris` TEXT NOT NULL,
                `auth_method` VARCHAR(32) NOT NULL DEFAULT 'none',
                `date_add` DATETIME NOT NULL,
                PRIMARY KEY (`id_client`),
                UNIQUE KEY `client_id` (`client_id`)
            ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS `{$p}piskeeconnector_request` (
                `id_request` VARCHAR(64) NOT NULL,
                `payload` TEXT NOT NULL,
                `expires` DATETIME NOT NULL,
                PRIMARY KEY (`id_request`)
            ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS `{$p}piskeeconnector_code` (
                `code_hash` CHAR(64) NOT NULL,
                `id_client` INT UNSIGNED NOT NULL,
                `id_employee` INT UNSIGNED NOT NULL,
                `id_shop` INT UNSIGNED NOT NULL,
                `redirect_uri` TEXT NOT NULL,
                `code_challenge` VARCHAR(128) NOT NULL,
                `scope` TEXT NOT NULL,
                `expires` DATETIME NOT NULL,
                `used` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`code_hash`)
            ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS `{$p}piskeeconnector_token` (
                `id_token` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `token_hash` CHAR(64) NOT NULL,
                `type` VARCHAR(8) NOT NULL,
                `family` VARCHAR(64) NOT NULL,
                `id_client` INT UNSIGNED NOT NULL,
                `id_employee` INT UNSIGNED NOT NULL,
                `id_shop` INT UNSIGNED NOT NULL,
                `scope` TEXT NOT NULL,
                `expires` DATETIME NOT NULL,
                `revoked` TINYINT(1) NOT NULL DEFAULT 0,
                `date_add` DATETIME NOT NULL,
                `last_used` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id_token`),
                UNIQUE KEY `token_hash` (`token_hash`),
                KEY `family` (`family`)
            ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4",
        ];
        foreach ($queries as $sql) {
            if (!Db::getInstance()->execute($sql)) {
                return false;
            }
        }

        return true;
    }

    private function installTab(): bool
    {
        if (Tab::getIdFromClassName(self::CONSENT_TAB)) {
            return true;
        }
        $tab = new Tab();
        $tab->class_name = self::CONSENT_TAB;
        $tab->module = $this->name;
        $tab->id_parent = -1;
        $tab->active = 1;
        foreach (Language::getLanguages(false) as $language) {
            $tab->name[(int) $language['id_lang']] = 'Piskee';
        }

        return (bool) $tab->add();
    }

    private function adminDir(): string
    {
        return defined('_PS_ADMIN_DIR_') ? basename(_PS_ADMIN_DIR_) : (string) Configuration::get('PISKEECONNECTOR_ADMIN_DIR');
    }

    private function uiLang(): string
    {
        $forced = (string) Tools::getValue('pklang', '');
        if (in_array($forced, ['fr', 'en'], true)) {
            $this->context->cookie->__set('piskee_lang', $forced);
            $this->context->cookie->write();

            return $forced;
        }
        $saved = (string) $this->context->cookie->__get('piskee_lang');
        if (in_array($saved, ['fr', 'en'], true)) {
            return $saved;
        }

        return PiskeeConnectorI18n::lang((string) $this->context->language->iso_code);
    }

    private function csrfToken(): string
    {
        // Bound to the employee and to their current back office session.
        $session = (string) ($this->context->cookie->session_token ?? $this->context->cookie->session_id ?? '');

        return hash_hmac('sha256', 'piskeeconnector|' . (int) $this->context->employee->id . '|' . $session . '|' . (string) $this->context->employee->passwd, _COOKIE_KEY_);
    }

    /**
     * The address of this very page with extra parameters, whatever the back
     * office router (legacy or Symfony) served it with.
     *
     * @param array<string, string> $params
     */
    private function pageUrl(array $params): string
    {
        return $this->context->link->getAdminLink('AdminModules', true, [], array_merge(['configure' => $this->name], $params));
    }

    /**
     * Just enough Markdown for the bundled documentation: headings, lists,
     * code, links, bold and paragraphs. Every piece of text is escaped first.
     */
    private function markdown(string $text): string
    {
        $html = '';
        $inList = false;
        $inCode = false;
        $paragraph = [];
        $flush = static function () use (&$paragraph, &$html) {
            if ($paragraph !== []) {
                $html .= '<p>' . implode(' ', $paragraph) . '</p>';
                $paragraph = [];
            }
        };
        $inline = static function (string $line): string {
            $line = htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
            $line = preg_replace('/`([^`]+)`/', '<code>$1</code>', $line) ?? $line;
            $line = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $line) ?? $line;
            $line = preg_replace('/\[([^\]]+)\]\((https:\/\/[^)\s"<>]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $line) ?? $line;

            return $line;
        };

        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            if (strpos($line, '```') === 0) {
                $flush();
                $html .= $inCode ? '</code></pre>' : '<pre><code>';
                $inCode = !$inCode;
                continue;
            }
            if ($inCode) {
                $html .= htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . "\n";
                continue;
            }
            if (preg_match('/^(#{1,4})\s+(.*)$/', $line, $m)) {
                $flush();
                if ($inList) {
                    $html .= '</ul>';
                    $inList = false;
                }
                $level = min(4, strlen($m[1]) + 1);
                $html .= '<h' . $level . '>' . $inline($m[2]) . '</h' . $level . '>';
                continue;
            }
            if (preg_match('/^\s*(?:[-*]|\d+\.)\s+(.*)$/', $line, $m)) {
                $flush();
                if (!$inList) {
                    $html .= '<ul>';
                    $inList = true;
                }
                $html .= '<li>' . $inline($m[1]) . '</li>';
                continue;
            }
            if (trim($line) === '') {
                $flush();
                if ($inList) {
                    $html .= '</ul>';
                    $inList = false;
                }
                continue;
            }
            $paragraph[] = $inline(trim($line));
        }
        $flush();
        if ($inCode) {
            $html .= '</code></pre>';
        }
        if ($inList) {
            $html .= '</ul>';
        }

        return $html;
    }
}
