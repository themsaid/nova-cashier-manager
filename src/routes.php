<?php

Route::get('/user/{user_id}/subscriptions/{subscription_id?}', 'Themsaid\CashierTool\CashierToolController@user');
Route::post('/user/{user_id}/subscriptions/{subscription_id?}/cancel', 'Themsaid\CashierTool\CashierToolController@cancelSubscription');
Route::post('/user/{user_id}/subscriptions/{subscription_id?}/resume', 'Themsaid\CashierTool\CashierToolController@resumeSubscription');
Route::post('/user/{user_id}/subscriptions/{subscription_id?}/update', 'Themsaid\CashierTool\CashierToolController@updateSubscription');
Route::post('/user/{id}/refund/{chargeId}', 'Themsaid\CashierTool\CashierToolController@refundCharge');