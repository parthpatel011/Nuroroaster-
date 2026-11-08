<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_code' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{1,32}$/'],
            'full_name' => ['required', 'string', 'min:1', 'max:120'],
        ];
    }
}
