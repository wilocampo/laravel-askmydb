<?php

namespace AskMyDB\Laravel;

use Illuminate\Support\ServiceProvider;

class AskMyDBServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/askmydb.php', 'askmydb');

        $this->app->singleton(AskMyDB::class, function ($app) {
            return new AskMyDB();
        });

        $this->app->alias(AskMyDB::class, 'askmydb');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/askmydb.php' => config_path('askmydb.php'),
        ], 'askmydb-config');

        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'askmydb');
    }
}
