<?php

use App\Http\Controllers\HostDemoController;
use App\Http\Controllers\WidgetPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', WidgetPageController::class)->name('widget');
Route::get('/host', HostDemoController::class)->name('widget.host');
