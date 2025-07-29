<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
// routes/api.php

Route::group(
    ['namespace' => 'App\Http\Controllers\API', 'middleware' => 'throttle:500,10'],
    function () {

        Route::post('/register',  'AuthController@register');
        Route::post('/login', action: 'AuthController@login');

        Route::middleware(['auth:api'])->group(function () {
            Route::get('/me',  'AuthController@me');
            Route::post('/logout',  'AuthController@logout');
            Route::post('/refresh',  'AuthController@refresh');
        });
    }
);
