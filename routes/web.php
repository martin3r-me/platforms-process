<?php

use Illuminate\Support\Facades\Route;
use Platform\Process\Http\Controllers\ProcessCertificatePdfController;
use Platform\Process\Livewire\Process\Index as ProcessIndex;
use Platform\Process\Livewire\Process\Show as ProcessShow;
use Platform\Process\Livewire\Run\Show as RunShow;

// Module root → redirect to process list
Route::get('/', fn () => redirect()->route('process.processes.index'))->name('process.dashboard');

// Processes
Route::get('/processes', ProcessIndex::class)->name('process.processes.index');
Route::get('/processes/status/{status}', ProcessIndex::class)->name('process.processes.index.status');
Route::get('/processes/{process}', ProcessShow::class)->name('process.processes.show');
Route::get('/processes/{process}/runs/{run}', RunShow::class)->name('process.processes.runs.show');
Route::get('/processes/{process}/certificate.pdf', ProcessCertificatePdfController::class)->name('process.processes.certificate.pdf');
