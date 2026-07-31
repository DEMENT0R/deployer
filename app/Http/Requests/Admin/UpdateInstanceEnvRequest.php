<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateInstanceEnvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Пустое поле формы Laravel превращает в null (ConvertEmptyStringsToNull), а для .env
     * пустая строка — осмысленное значение: «ключ есть, значение пустое».
     */
    protected function prepareForValidation(): void
    {
        $values = $this->input('values');

        if (is_array($values)) {
            $this->merge(['values' => array_map(
                fn ($value) => is_scalar($value) ? (string) $value : ($value === null ? '' : $value),
                $values,
            )]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'values' => ['required', 'array'],
            // Перевод строки в значении разорвал бы файл на две строки, из которых вторая — мусор.
            'values.*' => ['present', 'string', 'max:4096', 'regex:/^[^\r\n]*$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'values.*.regex' => 'A value cannot span multiple lines.',
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var list<string> $visibleKeys */
                $visibleKeys = config('deployer.env_visible_keys', []);
                $unknown = array_diff(array_keys((array) $this->input('values', [])), $visibleKeys);

                if ($unknown !== []) {
                    $validator->errors()->add('values', 'Unknown variable: '.implode(', ', $unknown).'.');
                }
            },
        ];
    }
}
