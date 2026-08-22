<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    private const PER_PAGE = 50;

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'action' => ['nullable', 'string', 'max:64'],
        ]);

        $action = $validated['action'] ?? null;

        $entries = AuditLog::query()
            ->when($action, fn ($query) => $query->where('action', $action))
            ->with(['user:id,name', 'instance:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (AuditLog $entry) => [
                'id' => $entry->id,
                'created_at' => $entry->created_at?->toIso8601String(),
                'action' => $entry->action,
                'subject' => $entry->subject,
                'summary' => $entry->summary,
                'user' => $entry->user?->name,
                'instance' => $entry->instance ? [
                    'id' => $entry->instance->id,
                    'name' => $entry->instance->name,
                ] : null,
            ]);

        return Inertia::render('Admin/Audit/Index', [
            'entries' => $entries,
            'filters' => ['action' => $action],
            // Список действий берём из самого журнала: он же и есть перечень того, что уже писалось.
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
