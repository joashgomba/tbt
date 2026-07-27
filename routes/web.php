<?php

use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware(['web', 'set.locale'])
    ->group(base_path('routes/api.php'));

Route::view('/{any?}', 'app')->where('any', '.*');
