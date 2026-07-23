<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\QueueMonitorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;

class QueueController extends Controller
{
    public function index(QueueMonitorService $monitor): Response
    {
        return Inertia::render('Admin/Queues/Index', $monitor->snapshot());
    }

    public function retry(string $uuid): RedirectResponse
    {
        Artisan::call('queue:retry', ['id' => [$uuid]]);

        return back()->with('success', 'Job queued for retry.');
    }

    public function forget(string $uuid): RedirectResponse
    {
        Artisan::call('queue:forget', ['id' => $uuid]);

        return back()->with('success', 'Failed job removed.');
    }
}
