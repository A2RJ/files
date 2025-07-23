<?php

use App\Http\Controllers\FileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/generate-pdf', [FileController::class, 'generatePdf']);
// Contoh akses: POST http://localhost:8000/api/generate-pdf
// Body (JSON): { "nama": "Nama Anda", ... }
