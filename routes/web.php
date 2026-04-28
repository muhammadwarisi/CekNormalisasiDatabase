<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NormalizationController;

Route::get('/', function () {
    return view('welcome');
});

/**
 * Normalization Routes - PRE-PROCESSOR untuk Algoritma Demba
 * 
 * Routes ini menangani analisis pelanggaran 1NF dari file SQL yang diupload user.
 * Sistem ini adalah pre-processor sebelum algoritma Demba dijalankan.
 * Demba mengasumsikan input sudah dalam 1NF, sistem ini membantu mengidentifikasi
 * dan merekomendasikan perbaikan untuk mencapai 1NF.
 */
Route::prefix('normalization')->name('normalization.')->group(function () {
    Route::get('/upload', [NormalizationController::class, 'showUpload'])->name('upload');
    Route::post('/upload', [NormalizationController::class, 'uploadAndAnalyze'])->name('upload.post');
    Route::get('/results', [NormalizationController::class, 'showResults'])->name('results');
    Route::get('/detail', [NormalizationController::class, 'getDetail'])->name('detail');
    Route::get('/report', [NormalizationController::class, 'generateReport'])->name('report');
    Route::post('/clear', [NormalizationController::class, 'clearSession'])->name('clear');
});
