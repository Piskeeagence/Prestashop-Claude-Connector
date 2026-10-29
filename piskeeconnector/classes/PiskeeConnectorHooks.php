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
 * Where modules show on the shop (Design > Positions in the back office):
 * which modules each zone (display hook) shows, in which order, and on
 * which pages they are hidden. Changing it touches no theme code.
 */
class PiskeeConnectorHooks
{
    /** Modules Piskee places itself. */
    private const OWN = ['piskeeconnector', 'piskeeconnectorpro'];

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function listModuleHooks(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $idShop = $c['id_shop'];
        $hook = trim((string) ($args['hook'] ?? ''));
        $module = trim((string) ($args['module'] ?? ''));

        if ($hook !== '') {
            $idHook = (int) Hook::getIdByName($hook);
            if ($idHook <= 0) {
                return PiskeeConnectorHelper::error('Hook "' . $hook . '" does not exist. Call list_module_hooks without arguments to see the zones of the shop.');
            }

            return PiskeeConnectorHelper::json(['hook' => $hook, 'modules' => self::modulesOn($idHook, $idShop)]);
        }
        if ($module !== '') {
            $instance = Module::isInstalled($module) ? Module::getInstanceByName($module) : false;
            if (!$instance) {
                return PiskeeConnectorHelper::error('Module "' . $module . '" is not installed.');
            }
            $on = Db::getInstance()->executeS('SELECT h.`name`, hm.`position`, hm.`id_hook` FROM `' . _DB_PREFIX_ . 'hook_module` hm'
                . ' INNER JOIN `' . _DB_PREFIX_ . 'hook` h ON h.`id_hook` = hm.`id_hook`'
                . ' WHERE hm.`id_module` = ' . (int) $instance->id . ' AND hm.`id_shop` = ' . $idShop . " AND h.`name` LIKE 'display%' ORDER BY h.`name`") ?: [];
            $hooks = [];
            foreach ($on as $row) {
                $hooks[] = [
                    'hook' => (string) $row['name'],
                    'position' => self::position((int) $row['id_hook'], (int) $instance->id, $idShop),
                    'hidden_on' => self::exceptions((int) $row['id_hook'], (int) $instance->id, $idShop),
                ];
            }

            return PiskeeConnectorHelper::json([
                'module' => $module,
                'name' => (string) $instance->displayName,
                'enabled' => Module::isEnabled($module),
                'shown_in' => $hooks,
                'can_show_in' => self::possibleHooks($instance),
            ]);
        }

        // The zones of the shop that show something.
        $rows = Db::getInstance()->executeS('SELECT h.`name`, COUNT(*) AS modules FROM `' . _DB_PREFIX_ . 'hook_module` hm'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'hook` h ON h.`id_hook` = hm.`id_hook`'
            . ' WHERE hm.`id_shop` = ' . $idShop . " AND h.`name` LIKE 'display%' AND h.`name` NOT LIKE 'displayAdmin%' AND h.`name` NOT LIKE 'displayBackOffice%' AND h.`name` NOT LIKE 'displayDashboard%'"
            . ' GROUP BY h.`name` ORDER BY h.`name`') ?: [];

        return PiskeeConnectorHelper::json([
            'zones' => array_map(static function ($row) {
                return ['hook' => (string) $row['name'], 'modules' => (int) $row['modules']];
            }, $rows),
            'note' => 'Each zone (hook) is a place of the theme: displayTop (header), displayNav1/displayNav2 (top bar), displayHome (home page), displayFooter, displayFooterBefore, '
                . 'displayLeftColumn, displayProductAdditionalInfo... Call with hook to see its modules in order, or with module to see where it shows and where it could.',
        ]);
    }

    /**
     * Attaches a module to a zone, detaches it, or moves it inside the zone.
     *
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function setModuleHook(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $idShop = $c['id_shop'];
        $action = (string) ($args['action'] ?? '');
        $name = trim((string) ($args['module'] ?? ''));
        $hook = trim((string) ($args['hook'] ?? ''));
        if (!in_array($action, ['attach', 'detach', 'move'], true)) {
            return PiskeeConnectorHelper::error('action must be attach, detach or move.');
        }
        if (!preg_match('/^display[A-Za-z0-9_]+$/', $hook) || preg_match('/^display(Admin|BackOffice|Dashboard)/i', $hook)) {
            return PiskeeConnectorHelper::error('Only zones of the shop front (hooks starting with "display", not back office ones) can be changed here.');
        }
        if (in_array($name, self::OWN, true)) {
            return PiskeeConnectorHelper::error('Piskee places its own modules itself (the mega menu is published with publish_mega_menu).');
        }
        $module = Module::isInstalled($name) ? Module::getInstanceByName($name) : false;
        if (!$module) {
            return PiskeeConnectorHelper::error('Module "' . $name . '" is not installed.');
        }
        $idHook = (int) Hook::getIdByName($hook);
        $before = $idHook > 0 ? self::state($idHook, (int) $module->id, $idShop) : ['attached' => false];

        if ($action === 'attach') {
            if (!$before['attached'] && !self::canShow($module, $hook)) {
                return PiskeeConnectorHelper::error('The module ' . $name . ' cannot show in ' . $hook . '. It can show in: ' . implode(', ', self::possibleHooks($module) ?: ['(no front zone)']) . '.');
            }
            if (!$before['attached'] && !$module->registerHook($hook, [$idShop])) {
                return PiskeeConnectorHelper::error('PrestaShop refused to attach ' . $name . ' to ' . $hook . '.');
            }
            $idHook = (int) Hook::getIdByName($hook);
        } elseif (!$before['attached']) {
            return PiskeeConnectorHelper::error('The module ' . $name . ' is not in ' . $hook . '.');
        }
        if ($action === 'detach') {
            $module->unregisterHook($hook, [$idShop]);
            $module->unregisterExceptions($idHook, [$idShop]);
        } else {
            if (isset($args['position'])) {
                self::place($idHook, (int) $module->id, max(1, (int) $args['position']), $idShop);
            }
            if (array_key_exists('hidden_on', $args) && is_array($args['hidden_on'])) {
                $pages = array_values(array_unique(array_filter(array_map(static function ($page) {
                    return preg_replace('/[^a-z0-9_-]/', '', Tools::strtolower((string) $page));
                }, $args['hidden_on']))));
                $module->unregisterExceptions($idHook, [$idShop]);
                if ($pages !== []) {
                    $module->registerExceptions($idHook, $pages, [$idShop]);
                }
            }
        }
        Cache::clean('hook_module_*');
        Cache::clean('hook_*');
        Tools::clearCache();
        $after = self::state($idHook, (int) $module->id, $idShop);
        PiskeeConnectorHelper::recordChange('ModuleHook', (int) $module->id, $name . ' @ ' . $hook, 'update', ['hook' => $hook] + $before, ['hook' => $hook] + $after);

        return PiskeeConnectorHelper::json([
            'module' => $name,
            'hook' => $hook,
            'before' => $before,
            'after' => $after,
            'zone_now' => self::modulesOn($idHook, $idShop),
        ]);
    }

    /**
     * Puts the module back in the state given (used to undo a change).
     *
     * @param array<string, mixed> $state
     */
    public static function restore(string $name, string $hook, array $state, int $idShop): bool
    {
        $module = Module::getInstanceByName($name);
        if (!$module) {
            return false;
        }
        $idHook = (int) Hook::getIdByName($hook);
        if (empty($state['attached'])) {
            $module->unregisterHook($hook, [$idShop]);
            $module->unregisterExceptions($idHook, [$idShop]);
        } else {
            if ($idHook <= 0 || !self::state($idHook, (int) $module->id, $idShop)['attached']) {
                $module->registerHook($hook, [$idShop]);
                $idHook = (int) Hook::getIdByName($hook);
            }
            self::place($idHook, (int) $module->id, max(1, (int) ($state['position'] ?? 1)), $idShop);
            $module->unregisterExceptions($idHook, [$idShop]);
            if (!empty($state['hidden_on'])) {
                $module->registerExceptions($idHook, (array) $state['hidden_on'], [$idShop]);
            }
        }
        Cache::clean('hook_module_*');
        Tools::clearCache();

        return true;
    }

    /**
     * @return array{attached:bool, position?:int, hidden_on?:array<int, string>}
     */
    private static function state(int $idHook, int $idModule, int $idShop): array
    {
        $position = self::position($idHook, $idModule, $idShop);
        if ($position === 0) {
            return ['attached' => false];
        }

        return ['attached' => true, 'position' => $position, 'hidden_on' => self::exceptions($idHook, $idModule, $idShop)];
    }

    /**
     * Rank of a module in a zone, from 1; 0 when it is not there.
     */
    private static function position(int $idHook, int $idModule, int $idShop): int
    {
        $ids = array_map('intval', array_column(Db::getInstance()->executeS('SELECT `id_module` FROM `' . _DB_PREFIX_ . 'hook_module` WHERE `id_hook` = ' . $idHook
            . ' AND `id_shop` = ' . $idShop . ' ORDER BY `position` ASC') ?: [], 'id_module'));
        $index = array_search($idModule, $ids, true);

        return $index === false ? 0 : $index + 1;
    }

    /**
     * @return array<int, string>
     */
    private static function exceptions(int $idHook, int $idModule, int $idShop): array
    {
        return array_values(array_unique(array_map('strval', array_column(Db::getInstance()->executeS('SELECT `file_name` FROM `' . _DB_PREFIX_ . 'hook_module_exceptions`'
            . ' WHERE `id_hook` = ' . $idHook . ' AND `id_module` = ' . $idModule . ' AND `id_shop` = ' . $idShop) ?: [], 'file_name'))));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function modulesOn(int $idHook, int $idShop): array
    {
        $rows = Db::getInstance()->executeS('SELECT m.`id_module`, m.`name`, m.`active` FROM `' . _DB_PREFIX_ . 'hook_module` hm'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'module` m ON m.`id_module` = hm.`id_module`'
            . ' WHERE hm.`id_hook` = ' . $idHook . ' AND hm.`id_shop` = ' . $idShop . ' ORDER BY hm.`position` ASC') ?: [];
        $out = [];
        foreach ($rows as $i => $row) {
            $instance = Module::getInstanceByName((string) $row['name']);
            $out[] = [
                'position' => $i + 1,
                'module' => (string) $row['name'],
                'name' => $instance ? (string) $instance->displayName : (string) $row['name'],
                'enabled' => (bool) $row['active'],
                'hidden_on' => self::exceptions($idHook, (int) $row['id_module'], $idShop),
            ];
        }

        return $out;
    }

    private static function place(int $idHook, int $idModule, int $position, int $idShop): void
    {
        $db = Db::getInstance();
        $ids = array_values(array_filter(array_map('intval', array_column($db->executeS('SELECT `id_module` FROM `' . _DB_PREFIX_ . 'hook_module` WHERE `id_hook` = ' . $idHook
            . ' AND `id_shop` = ' . $idShop . ' ORDER BY `position` ASC') ?: [], 'id_module')), static function ($id) use ($idModule) {
                return $id !== $idModule;
            }));
        $position = min($position, count($ids) + 1);
        array_splice($ids, $position - 1, 0, [$idModule]);
        foreach ($ids as $index => $id) {
            $db->update('hook_module', ['position' => $index + 1], '`id_hook` = ' . $idHook . ' AND `id_shop` = ' . $idShop . ' AND `id_module` = ' . (int) $id);
        }
    }

    private static function canShow(Module $module, string $hook): bool
    {
        return is_callable([$module, 'hook' . ucfirst($hook)]) || $module instanceof PrestaShop\PrestaShop\Core\Module\WidgetInterface;
    }

    /**
     * @return array<int, string>
     */
    private static function possibleHooks(Module $module): array
    {
        if ($module instanceof PrestaShop\PrestaShop\Core\Module\WidgetInterface) {
            return ['any front zone (the module is a widget)'];
        }
        $out = [];
        foreach (get_class_methods($module) as $method) {
            if (preg_match('/^hook(display[A-Za-z0-9_]+)$/i', $method, $m) && !preg_match('/^display(Admin|BackOffice|Dashboard|Header$)/i', $m[1])) {
                $out[] = lcfirst($m[1]);
            }
        }
        sort($out);

        return array_values(array_unique($out));
    }
}
