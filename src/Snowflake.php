<?php

namespace Userlynk\Snowflake;

use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ForeignIdColumnDefinition;
use Illuminate\Database\Schema\Grammars\PostgresGrammar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;

class Snowflake
{
    /**
     * @return void
     */
    public static function prepareDatabase()
    {
        // Setup global sequence.
        DB::statement('CREATE SEQUENCE global_sid_seq;');

        // Ensure necessary functions are set up for each migration.
        Event::listen(MigrationsStarted::class, function () {

            // Create sid_generator() function.
            DB::statement("
            CREATE OR REPLACE FUNCTION sid_generator()
                RETURNS bigint
                LANGUAGE 'plpgsql'
            AS $BODY$
            DECLARE
                our_epoch bigint := 1314220021721;
                seq_id bigint;
                now_millis bigint;
                -- the id of this DB shard, must be set for each
                -- schema shard you have - you could pass this as a parameter too
                shard_id int := 1;
                result bigint:= 0;
            BEGIN
                SELECT nextval('global_sid_seq') % 1024 INTO seq_id;
            
                SELECT FLOOR(EXTRACT(EPOCH FROM clock_timestamp()) * 1000) INTO now_millis;
                result := (now_millis - our_epoch) << 23;
                result := result | (shard_id << 10);
                result := result | (seq_id);
                return result;
            END;
            $BODY$;
            ");

            // Create set_sid() function.
            DB::statement("
            CREATE OR REPLACE FUNCTION set_sid()
                RETURNS TRIGGER
                LANGUAGE plpgsql AS
                '
                BEGIN
                    NEW.sid = sid_generator();
                    RETURN NEW;
                END;
                ';
            ");
        });
    }

    /**
     * Register the snowflake macro.
     *
     * @return void
     */
    public static function registerMacros()
    {
        self::registerGrammarMacros();
        self::registerBlueprintMacros();
    }

    protected function registerGrammarMacros()
    {
        PostgresGrammar::macro('compileTrigger', function (Blueprint $blueprint, Fluent $command) {
            return sprintf(
                'create trigger %s before insert on %s for each row execute procedure %s',
                $command->name,
                $this->wrapTable($blueprint),
                $command->function
            );
        });

        MySqlGrammar::macro('compileTrigger', function (Blueprint $blueprint, Fluent $command) {
            return sprintf(
                'create trigger %s before insert on %s for each row call procedure %s',
                $command->name,
                $this->wrapTable($blueprint),
                $command->function
            );
        });
    }

    protected function registerBlueprintMacros()
    {
        Blueprint::macro('addTrigger', function ($function, $name = null) {
            if (! Str::endsWith($function, '()')) {
                $function = $function.'()';
            }

            // If no name was specified for trigger index, we will create one using a basic
            // convention of the function name prefixed with trigger_.
            $functionBase = Str::snake(str_replace('()', '', $function));
            $name = $name ?: "trigger_{$functionBase}";

            $this->addCommand('trigger', [
                'name' => $name,
                'function' => $function,
            ]);
        });

        Blueprint::macro('snowflake', function () {
            $this->unsignedBigInteger('sid')->index()->addTrigger('set_sid');
        });

        Blueprint::macro('foreignSnowflake', function ($column) {
            $this->addColumnDefinition(new ForeignIdColumnDefinition($this, [
                'type' => 'integer',
                'name' => $column,
            ]));
        });
    }

    /**
     * Apply triggers to any existing games with a "sid" column.
     *
     * @return void
     */
    public static function applyTriggers()
    {
        DB::statement("
            DO $$
            DECLARE
                t text;
            BEGIN
                FOR t IN
                    SELECT table_name FROM information_schema.columns WHERE column_name = 'sid'
                LOOP
                    EXECUTE format('DROP TRIGGER IF EXISTS trigger_set_sid', t,t);
                    EXECUTE format('CREATE TRIGGER trigger_set_sid BEFORE INSERT ON %I FOR EACH ROW EXECUTE PROCEDURE set_sid()', t,t);
                END loop;
            END;
            $$ language 'plpgsql';
        ");
    }

    /**
     * Add the trigger to a new table.
     *
     * @param string $table
     * @return void
     */
    public static function addTrigger(string $table)
    {
        DB::statement('CREATE TRIGGER trigger_set_sid BEFORE INSERT ON ? FOR EACH ROW EXECUTE PROCEDURE set_sid();', [$table]);
    }
}
