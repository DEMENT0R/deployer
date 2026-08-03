<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ScreenException;
use App\Http\Controllers\Controller;
use App\Models\Instance;
use App\Services\ScreenMonitorService;
use App\Services\ScreenSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScreenController extends Controller
{
    public function index(Request $request, ScreenMonitorService $screens): Response
    {
        $validated = $request->validate([
            'user' => ['nullable', 'string', 'max:64'],
        ]);

        $user = trim($validated['user'] ?? '') ?: null;

        return Inertia::render('Admin/Screens/Index', [
            'filter' => $user,
            'instances' => $this->servableInstances(),
            // Подпроцесс ps не должен задерживать первую отрисовку; поллинг ходит с only.
            'screen' => Inertia::defer(fn () => $screens->snapshot($user)),
        ]);
    }

    public function store(Request $request, ScreenSessionService $sessions): RedirectResponse
    {
        $validated = $request->validate([
            'instance_id' => ['required', 'integer', 'exists:instances,id'],
        ]);

        $instance = Instance::findOrFail($validated['instance_id']);

        try {
            $session = $sessions->start($instance);
        } catch (ScreenException $exception) {
            return back()->withErrors(['screen' => $exception->getMessage()]);
        }

        return back()->with('success', "Session \"{$session}\" started.");
    }

    public function destroy(Request $request, ScreenSessionService $sessions): RedirectResponse
    {
        $validated = $request->validate([
            // Имена сессий на хосте бывают любые (`2712.pts-0.host`), запрещаем только пробелы.
            'session' => ['required', 'string', 'max:255', 'regex:/^\S+$/'],
        ]);

        try {
            $sessions->stop($validated['session']);
        } catch (ScreenException $exception) {
            return back()->withErrors(['screen' => $exception->getMessage()]);
        }

        return back()->with('success', "Session \"{$validated['session']}\" stopped.");
    }

    /**
     * @return list<array{id: int, name: string, screen_session: string, serve_port: int, url: ?string}>
     */
    private function servableInstances(): array
    {
        return Instance::query()
            ->whereNotNull('screen_session')
            ->whereNotNull('serve_port')
            ->orderBy('name')
            ->get(['id', 'name', 'url', 'screen_session', 'serve_port'])
            ->map(fn (Instance $instance) => [
                'id' => $instance->id,
                'name' => $instance->name,
                'url' => $instance->url,
                'screen_session' => $instance->screen_session,
                'serve_port' => $instance->serve_port,
            ])
            ->all();
    }
}
