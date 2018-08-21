<?php

use Carbon\Carbon;
use Stripe\Stripe;

Route::get('/nova-cashier-tool-api/user/{id}', 'Themsaid\CashierTool\CashierToolController@user');
Route::post('/nova-cashier-tool-api/user/{id}/cancel', 'Themsaid\CashierTool\CashierToolController@cancelSubscription');
Route::post('/nova-cashier-tool-api/user/{id}/resume', 'Themsaid\CashierTool\CashierToolController@resumeSubscription');
Route::post('/nova-cashier-tool-api/user/{id}/update', 'Themsaid\CashierTool\CashierToolController@updateSubscription');
Route::post('/nova-cashier-tool-api/user/{id}/refund/{chargeId}', 'Themsaid\CashierTool\CashierToolController@refundCharge');