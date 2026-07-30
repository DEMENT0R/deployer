<?php

namespace App\Http\Requests;

use App\Enums\DeployAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeploymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('deploy', $this->route('instance')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $action = DeployAction::tryFrom($this->input('action', ''));

        return [
            'action' => ['required', Rule::in(DeployAction::userTriggerable())],
            'branch' => [
                Rule::requiredIf(fn () => $action?->requiresBranch() ?? false),
                'nullable',
                'string',
                'regex:'.config('deployer.branch_pattern'),
            ],
        ];
    }
}
