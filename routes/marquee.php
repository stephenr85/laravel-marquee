<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Rushing\Marquee\Http\Controllers\PreviewController;

Route::get('marquee/preview', PreviewController::class)
    ->name('marquee.preview')
    ->middleware(['web', 'signed']);
