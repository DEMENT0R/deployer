<?php

namespace App\Http\Controllers;

use App\Models\Instance;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * «Стенд сейчас занят мной». Не запрет, а предупреждение: деплой на занятый стенд проходит,
 * но тот, кто его запускает, видит, кем и до какого времени стенд занят.
 */
class InstanceHoldController extends Controller
{
    /** Сколько часов держать стенд, если срок не выбран. */
    private const DEFAULT_HOURS = 4;

    public function store(Request $request, Instance $instance): RedirectResponse
    {
        $this->authorize('deploy', $instance);

        $validated = $request->validate([
            'hours' => ['nullable', 'integer', 'min:1', 'max:72'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $hours = $validated['hours'] ?? self::DEFAULT_HOURS;

        // Занятый другим стенд не перехватываем молча: сначала пусть освободит владелец
        // или админ, иначе бронь ничего не значит.
        if ($instance->isHeld() && $instance->held_by_user_id !== $request->user()->id) {
            return back()->withErrors([
                'hold' => "The stand is held by {$instance->holder?->name} until ".
                    $instance->held_until->format('Y-m-d H:i').'.',
            ]);
        }

        $instance->update([
            'held_by_user_id' => $request->user()->id,
            'held_until' => now()->addHours($hours),
            'hold_note' => $validated['note'] ?? null,
        ]);

        Audit::record(
            'instance.held',
            $instance->name,
            "For {$hours}h".($validated['note'] ?? '' ? ": {$validated['note']}" : ''),
            $instance,
        );

        return back()->with('success', 'Stand marked as taken.');
    }

    public function destroy(Request $request, Instance $instance): RedirectResponse
    {
        $this->authorize('view', $instance);

        // Снять бронь может владелец и админ: чужая забытая бронь не должна держать стенд
        // до истечения срока.
        if ($instance->held_by_user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            abort(403);
        }

        $instance->update([
            'held_by_user_id' => null,
            'held_until' => null,
            'hold_note' => null,
        ]);

        Audit::record('instance.released', $instance->name, null, $instance);

        return back()->with('success', 'Stand released.');
    }
}
