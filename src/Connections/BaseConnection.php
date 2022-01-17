<?php

namespace Userlynk\Snowflake\Connections;

use Illuminate\Database\Schema\Blueprint;
use Userlynk\Snowflake\ForeignSnowflakeColumnDefinition;

abstract class BaseConnection implements ConnectionInterface
{
    /**
     * @return void
     */
    public function register()
    {
        $this->prepareDatabase();
        $this->registerMacros();
        $this->applyTriggers();
    }

    /**
     * Register the snowflake macro.
     *
     * @return void
     */
    public function registerMacros()
    {
        $this->registerGrammarMacros();
        $this->registerBlueprintMacros();
    }

    /**
     * @return mixed
     */
    abstract public function registerGrammarMacros(): void;

    /**
     * @return void
     */
    public static function registerBlueprintMacros(): void
    {
        Blueprint::macro('snowflake', function ($column = 'sid') {
            $column = $this->unsignedBigInteger($column)->unique();

            $this->addCommand('sidTrigger');

            return $column;
        });

        Blueprint::macro('foreignSnowflake', function ($column) {
            return $this->addColumnDefinition(new ForeignSnowflakeColumnDefinition($this, [
                'type' => 'bigInteger',
                'name' => $column,
                'autoIncrement' => false,
                'unsigned' => true,
            ]));
        });
    }
}
