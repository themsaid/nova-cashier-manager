<?php

namespace Themsaid\CashierTool;

use Laravel\Nova\Nova;
use Laravel\Nova\Tool;

class CashierTool extends Tool
{
    /**
     * Perform any tasks that need to happen on tool registration.
     *
     * @return void
     */
    public function boot()
    {
        Nova::script('nova-cashier-tool', __DIR__.'/../dist/js/tool.js');
        Nova::style('nova-cashier-tool', __DIR__.'/../dist/css/tool.css');
    }
}
