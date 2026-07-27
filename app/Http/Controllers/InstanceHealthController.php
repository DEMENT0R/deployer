<?php

namespace App\Http\Controllers;

use App\Models\Instance;
use App\Services\InstanceHealthService;
use Illuminate\Http\JsonResponse;

class InstanceHealthController extends Controller
{
    public function show(Instance $instance, InstanceHealthService $healthService): JsonResponse
    {
        $this->authorize('view', $instance);

        return response()->json($healthService->check($instance));
    }
}
