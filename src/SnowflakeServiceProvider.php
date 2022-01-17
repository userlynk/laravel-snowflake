<?php

namespace Userlynk\Snowflake;

use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;

class SnowflakeServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if (App::runningInConsole()) {
            $this->publishConfig();

            Snowflake::prepareDatabase();
            Snowflake::registerMacros();
            Snowflake::applyTriggers();
        }
    }

    public function register()
    {
        $this->mergeConfig();
    }

    protected function mergeConfig()
    {
        $configPath = __DIR__.'/../config/snowflake.php';

        $this->mergeConfigFrom($configPath, 'snowflake');
    }

    protected function publishConfig()
    {
        $configPath = __DIR__.'/../config/snowflake.php';

        $this->publishes([$configPath => $this->getConfigPath()], 'config');
    }

    protected function getConfigPath(): string
    {
        return config_path('snowflake.php');
    }
}
