<?php

use App\Http\Controllers\FileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/pdf', [FileController::class, 'pdf']);

Route::post('/generate-pdf', [FileController::class, 'generateAndDownload']);
Route::delete('/delete-pdf/{filename}', [FileController::class, 'delete']);
