<?php

namespace Userlynk\Snowflake\Connections;

interface ConnectionInterface
{
    public function prepareDatabase();

    public function registerMacros();

    public function applyTriggers();
}
