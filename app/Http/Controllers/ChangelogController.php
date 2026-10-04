<?php

namespace App\Http\Controllers;

use App\Support\Changelog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ChangelogController extends Controller
{
    public function index(Request $request): Response
    {
        // Секции датированы по местному времени автора, отметка — в UTC: запись «на завтра»
        // по UTC иначе висела бы непрочитанной до полуночи. Открытая страница — это прочитанная
        // самая свежая секция, какой бы датой её ни подписали.
        $latest = Changelog::latestDate();
        $seenAt = $latest !== null ? now()->max(Carbon::parse($latest)) : now();

        $request->user()->forceFill(['changelog_seen_at' => $seenAt])->save();

        return Inertia::render('Changelog/Index', [
            'entries' => Changelog::entries(),
        ]);
    }
}
