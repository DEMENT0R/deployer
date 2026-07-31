<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Admin\InstanceController as AdminInstanceController;
use App\Http\Controllers\Admin\QueueController as AdminQueueController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\DeploymentController;
use App\Http\Controllers\InstanceController;
use App\Http\Controllers\InstanceHealthController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('instances.index')
        : redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/activity', [ActivityController::class, 'index'])->name('activity.index');
    Route::get('/instances', [InstanceController::class, 'index'])->name('instances.index');
    Route::get('/instances/{instance}', [InstanceController::class, 'show'])->name('instances.show');
    Route::get('/instances/{instance}/health', [InstanceHealthController::class, 'show'])
        ->middleware('throttle:30,1')
        ->name('instances.health');
    Route::get('/instances/{instance}/branches', [BranchController::class, 'index'])->name('instances.branches.index');
    Route::post('/instances/{instance}/branches/refresh', [BranchController::class, 'refresh'])->name('instances.branches.refresh');
    Route::post('/instances/{instance}/deploy', [DeployController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('instances.deploy');
    Route::post('/instances/{instance}/rollback', [DeployController::class, 'rollback'])
        ->middleware('throttle:10,1')
        ->name('instances.rollback');

    // scopeBindings: деплой ищется среди деплоев этого инстанса, чужой id даёт 404.
    Route::scopeBindings()->group(function () {
        Route::get('/instances/{instance}/deployments/{deployment}', [DeploymentController::class, 'show'])
            ->name('instances.deployments.show');
        Route::get('/instances/{instance}/deployments/{deployment}/commits', [DeploymentController::class, 'commits'])
            ->middleware('throttle:30,1')
            ->name('instances.deployments.commits');
        Route::post('/instances/{instance}/deployments/{deployment}/cancel', [DeploymentController::class, 'cancel'])
            ->name('instances.deployments.cancel');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('queues', [AdminQueueController::class, 'index'])->name('queues.index');
        Route::post('queues/failed/{uuid}/retry', [AdminQueueController::class, 'retry'])->name('queues.retry');
        Route::delete('queues/failed/{uuid}', [AdminQueueController::class, 'forget'])->name('queues.forget');
        Route::get('instances/{instance}/env', [AdminInstanceController::class, 'env'])->name('instances.env');
        Route::get('instances/{instance}/duplicate', [AdminInstanceController::class, 'duplicate'])->name('instances.duplicate');
        Route::post('instances/{instance}/clone', [AdminInstanceController::class, 'clone'])->name('instances.clone');
        Route::resource('instances', AdminInstanceController::class)->except(['show']);
        Route::resource('users', AdminUserController::class)->except(['show']);
    });
});

require __DIR__.'/auth.php';
