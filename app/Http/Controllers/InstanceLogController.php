<?php

namespace App\Http\Controllers;

use App\Exceptions\PathValidationException;
use App\Models\Instance;
use App\Services\InstanceLogService;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;

class InstanceLogController extends Controller
{
    public function show(Instance $instance, InstanceLogService $logs): JsonResponse
    {
        $this->authorize('view', $instance);

        try {
            return response()->json($logs->tail($instance));
        } catch (PathValidationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    /** Чистка лога — тем же, кто может деплоить: это их стенд и их же мусор в логе. */
    public function destroy(Instance $instance, InstanceLogService $logs): JsonResponse
    {
        $this->authorize('deploy', $instance);

        try {
            $cleared = $logs->clear($instance);
        } catch (PathValidationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        if (! $cleared) {
            return response()->json(['message' => 'The log file is missing or not writable.'], 422);
        }

        Audit::record('log.cleared', $instance->name, null, $instance);

        return response()->json($logs->tail($instance));
    }
}
