<?php

namespace App\Http\Controllers;

use App\Support\Changelog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChangelogController extends Controller
{
    public function index(Request $request): Response
    {
        $request->user()->forceFill(['changelog_seen_at' => now()])->save();

        return Inertia::render('Changelog/Index', [
            'entries' => Changelog::entries(),
        ]);
    }
}
