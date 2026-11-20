<?php

use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\AttemptController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ModuleController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');

    Route::post('/boards', [BoardController::class, 'store'])->name('boards.store');
    Route::get('/boards/{board:slug}', [BoardController::class, 'show'])->name('boards.show');
    Route::patch('/boards/{board:slug}', [BoardController::class, 'update'])->name('boards.update');
    Route::delete('/boards/{board:slug}', [BoardController::class, 'destroy'])->name('boards.destroy');
    Route::get('/boards/{board:slug}/template', [BoardController::class, 'template'])->name('boards.template');
    Route::post('/boards/{board:slug}/import', [ImportController::class, 'store'])->name('import.store');

    Route::get('/import/{token}', [ImportController::class, 'show'])->whereUuid('token')->name('import.show');
    Route::post('/import/{token}', [ImportController::class, 'confirm'])->whereUuid('token')->name('import.confirm');

    Route::get('/modules/{module}', [ModuleController::class, 'show'])->name('modules.show');
    Route::get('/modules/{module}/bank', [ModuleController::class, 'bank'])->name('modules.bank');
    Route::patch('/modules/{module}/status', [ModuleController::class, 'updateStatus'])->name('modules.status');
    Route::post('/modules/{module}/tiers/{tier}/attempts', [AttemptController::class, 'store'])->name('attempts.store');

    Route::get('/attempts/{attempt}', [AttemptController::class, 'show'])->name('attempts.show');
    Route::post('/attempts/{attempt}/answer', [AttemptController::class, 'answer'])->name('attempts.answer');
    Route::post('/attempts/{attempt}/skip', [AttemptController::class, 'skip'])->name('attempts.skip');
    Route::post('/attempts/{attempt}/next', [AttemptController::class, 'next'])->name('attempts.next');
    Route::post('/attempts/{attempt}/finish', [AttemptController::class, 'finish'])->name('attempts.finish');
});
