<?php

use App\Http\Controllers\FileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [FileController::class, 'pdf']);
Route::get('/download-pdf', [FileController::class, 'downloadPdf'])->name('download.pdf');
// Contoh akses: GET http://localhost:8000/api/download-pdf?filename=surat_xxxxx.pdf&signature=xxxx
