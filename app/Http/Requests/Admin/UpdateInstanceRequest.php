<?php

namespace App\Http\Requests\Admin;

class UpdateInstanceRequest extends StoreInstanceRequest
{
    /**
     * Без copy_files: дубль файлов бывает только у создания инстанса.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->baseRules();
    }
}
