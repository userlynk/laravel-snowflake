<?php

namespace Userlynk\Snowflake\Connections;

use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\MySqlGrammar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Fluent;

class MySqlConnection extends BaseConnection
{
    public function prepareDatabase()
    {
        //
    }

    public function registerEvents()
    {
        // Ensure necessary functions are set up for each migration.
        Event::listen(MigrationsStarted::class, function () {
            $shared_id = config('snowflake.shared_id');
            $epoch_start_of_time = config('snowflake.epoch_start_of_time');

            $this->wrapStatement('DROP FUNCTION IF EXISTS `sid_generator`$$');

            $this->wrapStatement("
            DELIMITER $$
            CREATE FUNCTION `sid_generator`() RETURNS BIGINT(20)
            DETERMINISTIC
            BEGIN
            DECLARE epoch BIGINT(20);
                DECLARE current_ms BIGINT(20);
                DECLARE node INTEGER;
                DECLARE incr BIGINT(20);
                DECLARE schema_node INTEGER default 1;
                
                SET node = ${shared_id};
                SET current_ms = round(UNIX_TIMESTAMP(CURTIME(4)) * 1000);
                SET epoch = {$epoch_start_of_time};
                
                SELECT LAST_INSERT_ID() INTO incr;                
                RETURN (current_ms - epoch) << 22 | (node << 12) | (incr % 4096);
            END$$
            DELIMITER ;
            ");
        });
    }

    public function registerGrammarMacros(): void
    {
        MySqlGrammar::macro('compileRemoveSidTrigger', function (Blueprint $blueprint, Fluent $command) {
            return sprintf(
                'DROP TRIGGER IF EXISTS %;',
                'trigger_set_sid_'.$blueprint->getTable(),
            );
        });

        MySqlGrammar::macro('compileAddSidTrigger', function (Blueprint $blueprint, Fluent $command) {
            return sprintf(
                '
                CREATE TRIGGER %s
                    BEFORE INSERT
                    ON %s FOR EACH ROW
                BEGIN                  
                    SET NEW.sid = sid_generator();
                END;
                ',
                'trigger_set_sid_'.$blueprint->getTable(),
                $this->wrapTable($blueprint),
                $this->wrapTable($blueprint),
            );
        });
    }

    public function applyTriggers()
    {
        // TODO: Implement applyTriggers() method.
    }

    /**
     * @param $statement
     * @return \Illuminate\Database\Query\Expression
     */
    protected function wrapStatement($statement): \Illuminate\Database\Query\Expression
    {
        return DB::raw($statement);
    }
}
