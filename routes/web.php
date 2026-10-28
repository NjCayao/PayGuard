<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
});

Route::get('/app/{any?}', function () {
    return view('app');
})->where('any', '.*');
