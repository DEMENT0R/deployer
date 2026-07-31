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
     * Копируем файлы через rsync — на Windows-инстансе такому действию взяться неоткуда.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->boolean('copy_files') && $this->input('platform') === Platform::Windows->value) {
                    $validator->errors()->add('copy_files', 'Copying files is only supported on Linux instances.');
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
            'migrate_command' => ['required', 'string', 'max:1024'],
            'frontend_command' => ['required', 'string', 'max:1024'],
            'allowed_path_prefix' => ['nullable', 'string', 'max:1024'],
            'is_active' => ['boolean'],
            'tester_ids' => ['array'],
            'tester_ids.*' => ['integer', 'exists:users,id'],
        ];
    }
}
