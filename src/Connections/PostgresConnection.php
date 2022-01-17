<?php

namespace Userlynk\Snowflake\Connections;

use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\PostgresGrammar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Fluent;

class PostgresConnection extends BaseConnection
{
    /**
     * @return void
     */
    public function prepareDatabase()
    {
        // Setup global sequence.
        DB::statement('CREATE SEQUENCE IF NOT EXISTS global_sid_seq;');

        // Ensure necessary functions are set up for each migration.
        Event::listen(MigrationsStarted::class, function () {

            // Create sid_generator() function.
            DB::statement("
            CREATE OR REPLACE FUNCTION sid_generator()
                RETURNS bigint
                LANGUAGE 'plpgsql'
            AS \$BODY\$
            DECLARE
                our_epoch bigint := 1314220021721;
                seq_id bigint;
                now_millis bigint;
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
            \$BODY\$;
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
     * @return void
     */
    public function registerGrammarMacros(): void
    {
        PostgresGrammar::macro('compileSidTrigger', function (Blueprint $blueprint, Fluent $command) {
            return sprintf(
                'create trigger trigger_set_sid before insert on %s for each row execute procedure set_sid()',
                $this->wrapTable($blueprint),
            );
        });
    }

    /**
     * Apply triggers to any existing tables with a "sid" column.
     *
     * @return void
     */
    public function applyTriggers()
    {
        DB::statement("
            DO $$
            DECLARE
                t text;
            BEGIN
                FOR t IN
                    SELECT table_name FROM information_schema.columns WHERE column_name = 'sid'
                LOOP
                    EXECUTE format('DROP TRIGGER IF EXISTS trigger_set_sid ON %I;', t,t);
                    EXECUTE format('CREATE TRIGGER trigger_set_sid BEFORE INSERT ON %I FOR EACH ROW EXECUTE PROCEDURE set_sid();', t,t);
                END loop;
            END;
            $$ language 'plpgsql';
        ");
    }
}
