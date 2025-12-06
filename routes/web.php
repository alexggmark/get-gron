<?php

use App\Http\Controllers\ScanController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('scan', [ScanController::class, 'create'])->name('scan.create');
Route::post('scan', [ScanController::class, 'store'])->name('scan.store');
Route::get('scan/{scan}', [ScanController::class, 'show'])->name('scan.show');

require __DIR__.'/settings.php';
