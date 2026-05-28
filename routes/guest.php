<?php

use Illuminate\Support\Facades\Route;
use Platform\Process\Http\Controllers\ProcessCertificatePublicController;

Route::get('/p/{token}', [ProcessCertificatePublicController::class, 'show'])->name('process.certificate.public');
Route::get('/p/{token}/pdf', [ProcessCertificatePublicController::class, 'pdf'])->name('process.certificate.public.pdf');
