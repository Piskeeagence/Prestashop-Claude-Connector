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
 * The tool call being run for the assistant, if any. Add-ons (Piskee Pro's
 * change history) read it to know that a change comes from Claude, from
 * which tool, connection and employee.
 */
class PiskeeConnectorCall
{
    /** @var array{ref:string, tool:string, writes:bool, id_shop:int, id_employee:int, id_client:int}|null */
    private static $current = null;

    /**
     * @param array{tool:string, writes:bool, id_shop:int, id_employee:int, id_client:int} $call
     */
    public static function begin(array $call): void
    {
        $call['ref'] = bin2hex(random_bytes(8));
        self::$current = $call;
    }

    public static function end(): void
    {
        self::$current = null;
    }

    /**
     * @return array{ref:string, tool:string, writes:bool, id_shop:int, id_employee:int, id_client:int}|null
     */
    public static function current(): ?array
    {
        return self::$current;
    }
}
