<?php

namespace App\Http\Controllers;

use App\Models\Instance;
use App\Services\InstanceBackupService;
use Illuminate\Http\JsonResponse;

class InstanceBackupController extends Controller
{
    public function index(Instance $instance, InstanceBackupService $backups): JsonResponse
    {
        $this->authorize('restore', $instance);

        return response()->json([
            'directory' => $backups->directory($instance),
            'dumps' => $backups->list($instance),
        ]);
    }
}
