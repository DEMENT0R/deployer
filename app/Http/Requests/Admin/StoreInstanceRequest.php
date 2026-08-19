<?php

namespace App\Http\Requests\Admin;

use App\Enums\Platform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInstanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'copy_files' => $this->boolean('copy_files'),
            // Пустое поле формы приходит строкой и валится на правиле url — считаем его «не задано».
            'url' => filled($this->input('url')) ? trim((string) $this->input('url')) : null,
            'repository_url' => filled($this->input('repository_url')) ? trim((string) $this->input('repository_url')) : null,
            'screen_session' => filled($this->input('screen_session')) ? trim((string) $this->input('screen_session')) : null,
            'serve_port' => filled($this->input('serve_port')) ? $this->input('serve_port') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge($this->baseRules(), [
            'copy_files' => ['boolean'],
            'source_instance_id' => ['nullable', 'integer', 'exists:instances,id', 'required_if:copy_files,true'],
        ]);
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            // Копируем файлы через rsync — на Windows-инстансе такому действию взяться неоткуда.
            function (Validator $validator): void {
                if ($this->boolean('copy_files') && $this->input('platform') === Platform::Windows->value) {
                    $validator->errors()->add('copy_files', 'Copying files is only supported on Linux instances.');
                }
            },
            // Порознь эти два поля бесполезны: по имени сессию находят, по порту поднимают.
            function (Validator $validator): void {
                $session = $this->input('screen_session');
                $port = $this->input('serve_port');

                if (blank($session) && filled($port)) {
                    $validator->errors()->add('screen_session', 'Set a screen session name together with the serve port.');
                }

                if (filled($session) && blank($port)) {
                    $validator->errors()->add('serve_port', 'Set a serve port together with the screen session name.');
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function baseRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Два инстанса на одном каталоге затирают деплой друг друга — путь уникален.
            'path' => [
                'required',
                'string',
                'max:1024',
                Rule::unique('instances', 'path')->ignore($this->route('instance')),
            ],
            'url' => ['nullable', 'string', 'max:1024', 'url:http,https'],
            // Репозиторий для первичного clone. Не проверяем как url: бывают SSH (git@…) и file-адреса.
            'repository_url' => ['nullable', 'string', 'max:2048'],
            'platform' => ['required', Rule::enum(Platform::class)],
            'git_remote' => ['required', 'string', 'max:255'],
            'default_branch' => ['required', 'string', 'max:255', 'regex:'.config('deployer.branch_pattern')],
            'composer_command' => ['nullable', 'string', 'max:1024'],
            'cache_command' => ['nullable', 'string', 'max:1024'],
            'backup_command' => ['nullable', 'string', 'max:1024'],
            'migrate_command' => ['required', 'string', 'max:1024'],
            'frontend_command' => ['required', 'string', 'max:1024'],
            'allowed_path_prefix' => ['nullable', 'string', 'max:1024'],
            // Имя уезжает в argv команды screen, поэтому только безопасный алфавит.
            'screen_session' => [
                'nullable',
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('instances', 'screen_session')->ignore($this->route('instance')),
            ],
            'serve_port' => [
                'nullable',
                'integer',
                'min:1',
                'max:65535',
                Rule::unique('instances', 'serve_port')->ignore($this->route('instance')),
            ],
            'is_active' => ['boolean'],
            'tester_ids' => ['array'],
            'tester_ids.*' => ['integer', 'exists:users,id'],
        ];
    }
}
