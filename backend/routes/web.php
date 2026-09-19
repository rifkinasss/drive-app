<?php

use Illuminate\Support\Facades\Route;

if (config('docs.enabled', true)) {
    Route::view('/docs', 'docs')->name('docs.landing');
} else {
    Route::get('/docs', fn (): never => abort(404))->name('docs.disabled');
}
Route::view('/', 'backend-home')->name('backend.home');
