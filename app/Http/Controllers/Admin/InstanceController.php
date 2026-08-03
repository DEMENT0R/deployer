<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeployAction;
use App\Enums\DeployStatus;
use App\Enums\UserRole;
use App\Exceptions\EnvWriteException;
use App\Exceptions\PathValidationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInstanceEnvRequest;
use App\Http\Requests\Admin\StoreInstanceRequest;
use App\Http\Requests\Admin\UpdateInstanceEnvRequest;
use App\Http\Requests\Admin\UpdateInstanceRequest;
use App\Jobs\DeployInstanceJob;
use App\Models\Deployment;
use App\Models\Instance;
use App\Models\User;
use App\Services\InstanceEnvService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InstanceController extends Controller
{
    public function index(InstanceEnvService $envService): Response
    {
        $instances = Instance::query()
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Instances/Index', [
            'instances' => $instances->map(fn (Instance $instance) => [
                'id' => $instance->id,
                'name' => $instance->name,
                'path' => $instance->path,
                'url' => $instance->url,
                'repository_url' => $instance->repository_url,
                'is_active' => $instance->is_active,
                'users_count' => $instance->users_count,
            ]),
            // Имя БД лежит в .env целевого проекта: по походу в файловую систему на инстанс.
            // Таблица не должна этого ждать — Inertia дотянет отдельным запросом.
            'databases' => Inertia::defer(fn () => $instances->mapWithKeys(
                fn (Instance $instance) => [$instance->id => $envService->databaseName($instance)]
            )),
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

    public function createEnv(
        StoreInstanceEnvRequest $request,
        Instance $instance,
        InstanceEnvService $envService,
    ): RedirectResponse {
        try {
            $envService->create($instance, $request->validated()['source']);
        } catch (EnvWriteException|PathValidationException $exception) {
            return back()->withErrors(['env' => $exception->getMessage()]);
        }

        return back()->with('success', '.env created.');
    }

    public function updateEnv(
        UpdateInstanceEnvRequest $request,
        Instance $instance,
        InstanceEnvService $envService,
    ): RedirectResponse {
        try {
            $changed = $envService->update($instance, $request->validated()['values']);
        } catch (EnvWriteException|PathValidationException $exception) {
            return back()->withErrors(['env' => $exception->getMessage()]);
        }

        return back()->with('success', $changed === []
            ? 'Nothing to update.'
            : 'Updated: '.implode(', ', $changed).'.');
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Instances/Form', [
            'instance' => null,
            'testers' => $this->testersList(),
        ]);
    }

    public function duplicate(Instance $instance): Response
    {
        return Inertia::render('Admin/Instances/Form', [
            'instance' => null,
            // path/url обнуляем: копия должна лежать в своём каталоге и иметь свой URL.
            // Имя screen-сессии и порт — тоже: они уникальны, копия поднимается своими.
            'prefill' => [
                'source_instance_id' => $instance->id,
                'source_path' => $instance->path,
                'name' => $instance->name.' (copy)',
                'path' => '',
                'url' => '',
                'repository_url' => $instance->repository_url,
                'platform' => $instance->platform->value,
                'git_remote' => $instance->git_remote,
                'default_branch' => $instance->default_branch,
                'composer_command' => $instance->composer_command,
                'migrate_command' => $instance->migrate_command,
                'frontend_command' => $instance->frontend_command,
                'allowed_path_prefix' => $instance->allowed_path_prefix,
                'screen_session' => null,
                'serve_port' => null,
                'is_active' => $instance->is_active,
                'tester_ids' => $instance->users()->pluck('users.id'),
            ],
            'testers' => $this->testersList(),
        ]);
    }

    public function store(StoreInstanceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $testerIds = $data['tester_ids'] ?? [];
        $copyFiles = (bool) ($data['copy_files'] ?? false);
        $sourceInstanceId = $data['source_instance_id'] ?? null;
        unset($data['tester_ids'], $data['copy_files'], $data['source_instance_id']);

        $instance = Instance::create($data);
        $instance->users()->sync($testerIds);

        if ($copyFiles && $sourceInstanceId) {
            // Копирование каталога — минуты работы, поэтому идёт деплоем: очередь, живой лог,
            // проверка «каталог назначения пуст» в PathValidator::resolveForClone уже в джобе.
            $deployment = Deployment::create([
                'instance_id' => $instance->id,
                'source_instance_id' => $sourceInstanceId,
                'user_id' => $request->user()->id,
                'action' => DeployAction::Copy,
                'status' => DeployStatus::Pending,
            ]);

            DeployInstanceJob::dispatch($deployment->id);

            return redirect()->route('instances.show', $instance)
                ->with('success', 'Instance created. Copying files.');
        }

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
                'url' => $instance->url,
                'repository_url' => $instance->repository_url,
                'platform' => $instance->platform->value,
                'git_remote' => $instance->git_remote,
                'default_branch' => $instance->default_branch,
                'composer_command' => $instance->composer_command,
                'migrate_command' => $instance->migrate_command,
                'frontend_command' => $instance->frontend_command,
                'allowed_path_prefix' => $instance->allowed_path_prefix,
                'screen_session' => $instance->screen_session,
                'serve_port' => $instance->serve_port,
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
     * Первичный clone репозитория в каталог инстанса. Оформлен как деплой (action=clone),
     * чтобы вывод шёл в ту же страницу с живым логом; серьёзную проверку пути и «каталог пуст»
     * делает PathValidator::resolveForClone уже в джобе.
     */
    public function clone(Instance $instance): RedirectResponse
    {
        if (blank($instance->repository_url)) {
            return back()->withErrors(['clone' => 'Set a repository URL before cloning.']);
        }

        if ($instance->deployments()->active()->exists()) {
            return back()->withErrors(['clone' => 'A deployment is already in progress for this instance.']);
        }

        $deployment = Deployment::create([
            'instance_id' => $instance->id,
            'user_id' => request()->user()->id,
            'action' => DeployAction::Clone,
            'status' => DeployStatus::Pending,
        ]);

        DeployInstanceJob::dispatch($deployment->id);

        return redirect()->route('instances.show', $instance)
            ->with('success', 'Clone queued.');
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
