<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssignmentRequest extends FormRequest
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
            'employee_id' => ['required', 'uuid'],
            'shift_definition_id' => ['required', 'uuid'],
            'duty_date' => ['required', 'date_format:Y-m-d'],
            'lock_version' => ['required', 'integer', 'min:0'],
        ];
    }
}
