<?php

namespace Userlynk\Snowflake\Connections;

interface ConnectionInterface
{
    public function prepareDatabase();

    public function registerEvents();

    public function registerGrammarMacros();

    public function registerBlueprintMacros();

    public function applyTriggers();
}
