<?php

use Illuminate\Support\Facades\Route;
use AskMyDB\Laravel\Http\Controllers\AskMyDBController;

Route::middleware('web')->prefix('askmydb')->name('askmydb.')->group(function () {
	Route::get('/', [AskMyDBController::class, 'index'])->name('index');
	Route::post('/ask', [AskMyDBController::class, 'ask'])->name('ask');
	Route::get('/schema.json', [AskMyDBController::class, 'schemaJson'])->name('schema');
});
