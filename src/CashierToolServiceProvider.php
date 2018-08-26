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
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/nova-cashier-manager.php' => config_path('nova-cashier-manager.php'),
            ], 'horizon-config');
        }

        $this->mergeConfigFrom(
            __DIR__.'/../config/nova-cashier-manager.php', 'nova-cashier-manager'
        );
    }
}
