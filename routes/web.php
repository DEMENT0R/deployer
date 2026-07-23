<?php

use App\Http\Controllers\Admin\InstanceController as AdminInstanceController;
use App\Http\Controllers\Admin\QueueController as AdminQueueController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\InstanceController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('instances.index')
        : redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/instances', [InstanceController::class, 'index'])->name('instances.index');
    Route::get('/instances/{instance}', [InstanceController::class, 'show'])->name('instances.show');
    Route::get('/instances/{instance}/branches', [BranchController::class, 'index'])->name('instances.branches.index');
    Route::post('/instances/{instance}/branches/refresh', [BranchController::class, 'refresh'])->name('instances.branches.refresh');
    Route::post('/instances/{instance}/deploy', [DeployController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('instances.deploy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('queues', [AdminQueueController::class, 'index'])->name('queues.index');
        Route::post('queues/failed/{uuid}/retry', [AdminQueueController::class, 'retry'])->name('queues.retry');
        Route::delete('queues/failed/{uuid}', [AdminQueueController::class, 'forget'])->name('queues.forget');
        Route::resource('instances', AdminInstanceController::class)->except(['show']);
        Route::resource('users', AdminUserController::class)->except(['show']);
    });
});

require __DIR__.'/auth.php';
