<?php

namespace App\Http\Requests\Admin;

use App\Enums\Platform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            // Пустое поле формы приходит строкой и валится на правиле url — считаем его «не задано».
            'url' => filled($this->input('url')) ? trim((string) $this->input('url')) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->baseRules();
    }

    /**
     * @return array<string, mixed>
     */
    protected function baseRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'path' => ['required', 'string', 'max:1024'],
            'url' => ['nullable', 'string', 'max:1024', 'url:http,https'],
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
