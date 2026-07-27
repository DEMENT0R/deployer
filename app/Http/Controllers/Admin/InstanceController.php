<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInstanceRequest;
use App\Http\Requests\Admin\UpdateInstanceRequest;
use App\Models\Instance;
use App\Models\User;
use App\Services\InstanceEnvService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InstanceController extends Controller
{
    public function index(): Response
    {
        $instances = Instance::query()
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (Instance $instance) => [
                'id' => $instance->id,
                'name' => $instance->name,
                'path' => $instance->path,
                'is_active' => $instance->is_active,
                'users_count' => $instance->users_count,
            ]);

        return Inertia::render('Admin/Instances/Index', [
            'instances' => $instances,
        ]);
    }

    public function env(Instance $instance, InstanceEnvService $envService): Response
    {
        return Inertia::render('Admin/Instances/Env', [
            'instance' => [
                'id' => $instance->id,
                'name' => $instance->name,
                'path' => $instance->path,
            ],
            'env' => $envService->inspect($instance),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Instances/Form', [
            'instance' => null,
            'testers' => $this->testersList(),
        ]);
    }

    public function store(StoreInstanceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $testerIds = $data['tester_ids'] ?? [];
        unset($data['tester_ids']);

        $instance = Instance::create($data);
        $instance->users()->sync($testerIds);

        return redirect()->route('admin.instances.index')
            ->with('success', 'Instance created.');
    }

    public function edit(Instance $instance): Response
    {
        $instance->load('users');

        return Inertia::render('Admin/Instances/Form', [
            'instance' => [
                'id' => $instance->id,
                'name' => $instance->name,
                'path' => $instance->path,
                'platform' => $instance->platform->value,
                'git_remote' => $instance->git_remote,
                'default_branch' => $instance->default_branch,
                'composer_command' => $instance->composer_command,
                'migrate_command' => $instance->migrate_command,
                'frontend_command' => $instance->frontend_command,
                'allowed_path_prefix' => $instance->allowed_path_prefix,
                'is_active' => $instance->is_active,
                'tester_ids' => $instance->users->pluck('id'),
            ],
            'testers' => $this->testersList(),
        ]);
    }

    public function update(UpdateInstanceRequest $request, Instance $instance): RedirectResponse
    {
        $data = $request->validated();
        $testerIds = $data['tester_ids'] ?? [];
        unset($data['tester_ids']);

        $instance->update($data);
        $instance->users()->sync($testerIds);

        return redirect()->route('admin.instances.index')
            ->with('success', 'Instance updated.');
    }

    public function destroy(Instance $instance): RedirectResponse
    {
        $instance->delete();

        return redirect()->route('admin.instances.index')
            ->with('success', 'Instance deleted.');
    }

    /**
     * @return list<array{id: int, name: string, email: string}>
     */
    private function testersList(): array
    {
        return User::query()
            ->where('role', UserRole::Tester)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->all();
    }
}
