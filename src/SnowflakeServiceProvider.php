<?php

namespace Userlynk\Snowflake;

use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;
use Userlynk\Snowflake\Connections\ConnectionInterface;
use Userlynk\Snowflake\Connections\MySqlConnection;
use Userlynk\Snowflake\Connections\PostgresConnection;

class SnowflakeServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if (App::runningInConsole()) {
            $this->publishConfig();

            Snowflake::register();
        }
    }

    public function register()
    {
        $this->mergeConfig();

        $this->app->bind(ConnectionInterface::class, function ($app) {
            return $app['db.connection']->getDriverName() === 'pgsql'
                ? new PostgresConnection()
                : new MySqlConnection();
        });
    }

    protected function publishConfig()
    {
        $this->publishes([
            __DIR__.'/../config/snowflake.php' => config_path('snowflake.php'),
        ]);
    }

    protected function mergeConfig()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/snowflake.php',
            'snowflake'
        );
    }
}
