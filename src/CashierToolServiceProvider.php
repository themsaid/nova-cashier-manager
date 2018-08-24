<?php

namespace Themsaid\CashierTool;

use Illuminate\Support\ServiceProvider;

class CashierToolServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'nova-cashier-tool');

        $this->loadRoutesFrom(__DIR__.'/routes.php');

        $this->publishes([
            __DIR__ . '/config.php' => config_path('nova-cashier-manager.php')
        ], 'config');
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'nova-cashier-manager');
    }
}
