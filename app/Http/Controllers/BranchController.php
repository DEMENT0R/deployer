<?php

namespace App\Http\Controllers;

use App\Exceptions\DeployException;
use App\Models\Instance;
use App\Services\Deploy\GitBranchResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index(Request $request, Instance $instance, GitBranchResolver $branchResolver): JsonResponse
    {
        $this->authorize('view', $instance);

        $data = $branchResolver->resolve($instance);

        return response()->json($data);
    }

    public function refresh(Request $request, Instance $instance, GitBranchResolver $branchResolver): JsonResponse
    {
        $this->authorize('view', $instance);

        try {
            $data = $branchResolver->refresh($instance);
        } catch (DeployException $e) {
            return response()->json(['message' => trim($e->getMessage())], 422);
        }

        return response()->json($data);
    }
}
