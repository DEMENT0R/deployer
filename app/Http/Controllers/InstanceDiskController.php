<?php

namespace App\Http\Controllers;

use App\Exceptions\PathValidationException;
use App\Models\Instance;
use App\Services\InstanceDiskService;
use Illuminate\Http\JsonResponse;

class InstanceDiskController extends Controller
{
    /** Обход vendor и node_modules — по кнопке, а не в составе списка инстансов: это секунды. */
    public function dependencies(Instance $instance, InstanceDiskService $disk): JsonResponse
    {
        $this->authorize('view', $instance);

        try {
            return response()->json($disk->dependencies($instance));
        } catch (PathValidationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
