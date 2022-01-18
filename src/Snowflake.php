<?php

namespace Userlynk\Snowflake;

use Illuminate\Support\Facades\Facade;
use Userlynk\Snowflake\Connections\ConnectionInterface;

/**
 * @method static void register(): void
 *
 * @see \Userlynk\Snowflake\Connections\BaseConnection
 */
class Snowflake extends Facade
{
    /**
     * @method static void register(): void
     *
     * @see \Userlynk\Snowflake\Connections\BaseConnection
     */
    protected static function getFacadeAccessor()
    {
        return ConnectionInterface::class;
    }
}
