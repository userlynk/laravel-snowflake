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
        $this->registerEvents();
        $this->registerGrammarMacros();
        $this->registerBlueprintMacros();
        $this->applyTriggers();
    }

    /**
     * @return void
     */
    public function registerBlueprintMacros(): void
    {
        Blueprint::macro('snowflake', function ($column = 'sid') {
            $column = $this->unsignedBigInteger($column)->unique();

            $this->addCommand('removeSidTrigger');
            $this->addCommand('addSidTrigger');

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
