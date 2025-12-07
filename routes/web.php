<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ScanController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/scan/{scan}', [DashboardController::class, 'show'])->name('dashboard.scan');
    Route::post('dashboard/scan', [DashboardController::class, 'store'])->middleware('throttle:scans')->name('dashboard.scan.store');
    Route::get('dashboard/scan/{scan}/status', [DashboardController::class, 'scanStatus'])->name('dashboard.scan.status');
});

Route::get('scan', [ScanController::class, 'create'])->name('scan.create');
Route::post('scan', [ScanController::class, 'store'])->middleware('throttle:scans')->name('scan.store');
Route::get('scan/{scan}', [ScanController::class, 'show'])->name('scan.show');

require __DIR__.'/settings.php';
