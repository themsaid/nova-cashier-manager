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

        $this->app->booted(function () {
            $this->routes();
        });
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


    /**
     * Register the tool's routes.
     *
     * @return void
     */
    protected function routes()
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        \Route::middleware(['nova'])
            ->prefix('nova-cashier-tool-api')
            ->group(__DIR__.'/routes.php');
    }
}
