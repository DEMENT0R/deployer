<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ScreenMonitorService;
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
            // Подпроцесс ps не должен задерживать первую отрисовку; поллинг ходит с only.
            'screen' => Inertia::defer(fn () => $screens->snapshot($user)),
        ]);
    }
}
