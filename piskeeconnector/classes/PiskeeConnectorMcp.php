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
 * Model Context Protocol server over Streamable HTTP, stateless: every POST
 * carries one JSON-RPC message and gets one JSON answer.
 */
class PiskeeConnectorMcp
{
    public const SUPPORTED_VERSIONS = ['2025-11-25', '2025-06-18', '2025-03-26'];

    /** @var array{id_employee:int, id_shop:int, scopes:array<int, string>, id_client:int} */
    private $grant;

    /** @var Employee */
    private $employee;

    /** @var string */
    private $lang;

    /**
     * @param array{id_employee:int, id_shop:int, scopes:array<int, string>, id_client:int} $grant
     */
    public function __construct(array $grant)
    {
        $this->grant = $grant;
        $this->employee = new Employee($grant['id_employee']);
        $this->lang = PiskeeConnectorI18n::lang(Language::getIsoById((int) $this->employee->id_lang) ?: 'en');
    }

    /**
     * @param mixed $message decoded JSON-RPC message
     *
     * @return array<string, mixed>|null null for notifications
     */
    public function handle($message): ?array
    {
        if (!is_array($message) || ($message['jsonrpc'] ?? '') !== '2.0' || !is_string($message['method'] ?? null)) {
            return self::error(null, -32600, 'Invalid Request');
        }
        $id = $message['id'] ?? null;
        $isNotification = !array_key_exists('id', $message);
        $params = is_array($message['params'] ?? null) ? $message['params'] : [];

        if ($isNotification) {
            return null;
        }

        try {
            switch ($message['method']) {
                case 'initialize':
                    return self::result($id, $this->initialize($params));
                case 'ping':
                    return self::result($id, new stdClass());
                case 'tools/list':
                    return self::result($id, ['tools' => $this->listTools()]);
                case 'tools/call':
                    return self::result($id, $this->callTool($params));
                case 'prompts/list':
                    return self::result($id, ['prompts' => $this->listPrompts()]);
                case 'prompts/get':
                    $prompt = $this->getPrompt($params);

                    return $prompt === null ? self::error($id, -32602, 'Unknown prompt') : self::result($id, $prompt);
                case 'resources/list':
                    return self::result($id, ['resources' => []]);
                case 'resources/templates/list':
                    return self::result($id, ['resourceTemplates' => []]);
                default:
                    return self::error($id, -32601, 'Method not found');
            }
        } catch (Throwable $e) {
            PrestaShopLogger::addLog('Piskee: ' . get_class($e) . ' in ' . $message['method'], 3, null, 'PiskeeConnector');

            return self::error($id, -32603, 'Internal error');
        }
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function initialize(array $params): array
    {
        $asked = (string) ($params['protocolVersion'] ?? '');

        return [
            'protocolVersion' => in_array($asked, self::SUPPORTED_VERSIONS, true) ? $asked : self::SUPPORTED_VERSIONS[1],
            'capabilities' => [
                'tools' => ['listChanged' => false],
                'prompts' => ['listChanged' => false],
            ],
            'serverInfo' => [
                'name' => 'piskee-prestashop',
                'title' => 'Piskee for PrestaShop',
                'version' => Module::getInstanceByName('piskeeconnector')->version ?? '1.0.0',
            ],
            'instructions' => 'You are connected to the PrestaShop shop "' . Configuration::get('PS_SHOP_NAME', null, null, $this->grant['id_shop']) . '". '
                . 'Call shop_info first to learn the languages and settings. Read an object before changing it. '
                . 'Every change needs the merchant\'s approval; never invent prices, EANs, discounts or product facts. '
                . 'Theme work happens only in draft child themes; only the merchant puts a theme live. '
                . 'Answer the merchant in their own language. '
                . PiskeeConnectorBrand::helpLine(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listTools(): array
    {
        $out = [];
        foreach ($this->tools() as $tool) {
            $writes = $tool['permission'][0] !== 'view';
            $out[] = [
                'name' => $tool['name'],
                'title' => $tool['title'],
                'description' => $tool['description'],
                'inputSchema' => $tool['input'],
                'annotations' => [
                    'title' => $tool['title'],
                    'readOnlyHint' => !$writes,
                    'destructiveHint' => $writes,
                    'idempotentHint' => !$writes,
                    'openWorldHint' => false,
                ],
            ];
        }

        return $out;
    }

    /**
     * Tools this grant may see: its scopes, and the employee's own rights.
     *
     * @return array<string, array<string, mixed>>
     */
    private function tools(): array
    {
        $declaration = PiskeeConnectorTools::declaration($this->lang);
        $out = [];
        foreach ($declaration['tools'] as $tool) {
            if (array_diff($tool['scopes'], $this->grant['scopes']) !== []) {
                continue;
            }
            [$action, $tab] = $tool['permission'];
            if (!$this->employee->can($action, $tab)) {
                continue;
            }
            $out[$tool['name']] = $tool;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function callTool(array $params): array
    {
        $name = (string) ($params['name'] ?? '');
        $tools = $this->tools();
        if (!isset($tools[$name])) {
            return self::toolError('Tool "' . $name . '" is not available: it does not exist, or the connected employee or this connection lacks the right to use it.');
        }
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        $context = Context::getContext();
        $context->employee = $this->employee;

        try {
            $result = call_user_func($tools[$name]['handler'], $arguments, [
                'id_shop' => $this->grant['id_shop'],
                'id_employee' => $this->grant['id_employee'],
                'id_lang' => (int) $this->employee->id_lang,
            ]);
        } catch (Throwable $e) {
            PrestaShopLogger::addLog('Piskee tool ' . $name . ': ' . Tools::substr($e->getMessage(), 0, 500), 3, null, 'PiskeeConnector');

            return self::toolError('The tool failed on the shop side. Nothing is known to have changed; read the object again before retrying. If it keeps failing: ' . PiskeeConnectorBrand::helpLine());
        }

        if (!is_array($result)) {
            return self::toolError('The tool returned nothing usable.');
        }
        if (!empty($result['error'])) {
            return self::toolError((string) ($result['text'] ?? 'Error'));
        }
        if (array_key_exists('json', $result)) {
            return ['content' => [['type' => 'text', 'text' => (string) json_encode($result['json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)]], 'isError' => false];
        }

        return ['content' => [['type' => 'text', 'text' => (string) ($result['text'] ?? '')]], 'isError' => false];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listPrompts(): array
    {
        $out = [];
        foreach (self::prompts($this->lang) as $name => $prompt) {
            if (array_diff($prompt['scopes'], $this->grant['scopes']) !== []) {
                continue;
            }
            $entry = ['name' => $name, 'title' => $prompt['title'], 'description' => $prompt['description']];
            if ($prompt['arguments'] !== []) {
                $entry['arguments'] = $prompt['arguments'];
            }
            $out[] = $entry;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>|null
     */
    private function getPrompt(array $params): ?array
    {
        $name = (string) ($params['name'] ?? '');
        $prompts = self::prompts($this->lang);
        if (!isset($prompts[$name]) || array_diff($prompts[$name]['scopes'], $this->grant['scopes']) !== []) {
            return null;
        }
        $text = (string) file_get_contents(_PS_MODULE_DIR_ . 'piskeeconnector/prompts/' . $name . '.md');
        $text = (string) preg_replace('/^---.*?---\s*/s', '', $text);

        // Values typed by the merchant go below the guide, never inside it.
        $lines = [];
        $given = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
        foreach ($prompts[$name]['arguments'] as $argument) {
            $value = trim(str_replace(["\r", "\n"], ' ', (string) ($given[$argument['name']] ?? '')));
            if ($value !== '') {
                $lines[] = '- ' . $argument['name'] . ': ' . Tools::substr($value, 0, 2000);
            }
        }
        if ($lines !== []) {
            $text .= "\n\n## This request\n\n" . implode("\n", $lines);
        }

        return [
            'description' => $prompts[$name]['description'],
            'messages' => [['role' => 'user', 'content' => ['type' => 'text', 'text' => $text]]],
        ];
    }

    /**
     * @return array<string, array{title:string, description:string, scopes:array<int, string>, arguments:array<int, array<string, mixed>>}>
     */
    public static function prompts(string $lang): array
    {
        return [
            'daily-brief' => [
                'title' => PiskeeConnectorI18n::promptText('daily-brief', 'title', $lang),
                'description' => PiskeeConnectorI18n::promptText('daily-brief', 'description', $lang),
                'scopes' => ['sales.read', 'catalog.read', 'service.read'],
                'arguments' => [],
            ],
            'catalog-optimisation' => [
                'title' => PiskeeConnectorI18n::promptText('catalog-optimisation', 'title', $lang),
                'description' => PiskeeConnectorI18n::promptText('catalog-optimisation', 'description', $lang),
                'scopes' => ['catalog.read', 'catalog.write'],
                'arguments' => [['name' => 'focus', 'description' => PiskeeConnectorI18n::promptText('catalog-optimisation', 'arg.focus', $lang), 'required' => false]],
            ],
            'theme-headings-breadcrumb' => [
                'title' => PiskeeConnectorI18n::promptText('theme-headings-breadcrumb', 'title', $lang),
                'description' => PiskeeConnectorI18n::promptText('theme-headings-breadcrumb', 'description', $lang),
                'scopes' => ['theme.read', 'theme.write'],
                'arguments' => [],
            ],
        ];
    }

    /**
     * @param mixed $id
     * @param mixed $result
     *
     * @return array<string, mixed>
     */
    private static function result($id, $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    /**
     * @param mixed $id
     *
     * @return array<string, mixed>
     */
    public static function error($id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }

    /**
     * @return array<string, mixed>
     */
    private static function toolError(string $text): array
    {
        return ['content' => [['type' => 'text', 'text' => $text]], 'isError' => true];
    }
}
