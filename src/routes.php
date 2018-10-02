<?php

Route::get('/resource/{resourceId}', 'CashierToolController@user');
Route::post('/resource/{resourceId}/cancel', 'CashierToolController@cancelSubscription');
Route::post('/resource/{resourceId}/resume', 'CashierToolController@resumeSubscription');
Route::post('/resource/{resourceId}/update', 'CashierToolController@updateSubscription');
Route::post('/resource/{resourceId}/refund/{chargeId}', 'CashierToolController@refundCharge');