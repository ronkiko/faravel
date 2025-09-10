<?php

use Vendor\Route;

Route::get('/', [App\Controllers\HelloController::class, 'index']);
