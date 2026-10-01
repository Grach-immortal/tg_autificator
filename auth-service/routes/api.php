<?php

use App\Http\Controllers\Api\CheckTelegramIdsController;
use App\Http\Controllers\Api\ListTelegramIdsController;
use App\Http\Controllers\Api\VerifyCodeController;
use App\Http\Controllers\Api\WidgetCodeController;
use Illuminate\Support\Facades\Route;

Route::get('/widget/code', WidgetCodeController::class)
    ->middleware('throttle:widget-code')
    ->name('api.widget.code');

Route::post('/internal/codes/verify', VerifyCodeController::class)
    ->middleware('internal')
    ->name('api.internal.codes.verify');

Route::post('/internal/tg-ids/check', CheckTelegramIdsController::class)
    ->middleware('internal')
    ->name('api.internal.tg-ids.check');

Route::get('/internal/tg-ids', ListTelegramIdsController::class)
    ->middleware('internal')
    ->name('api.internal.tg-ids.index');
