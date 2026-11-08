<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class StoreRosterRequest extends FormRequest
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
            'ward_id' => ['required', 'uuid'],
            'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start', function (string $attribute, mixed $value, \Closure $fail): void {
                $start = $this->input('period_start');

                if (! is_string($start) || ! is_string($value)) {
                    return;
                }

                $days = CarbonImmutable::parse($start)->diffInDays(CarbonImmutable::parse($value), true);

                if ($days > 62) {
                    $fail('A roster period cannot be longer than 63 days.');
                }
            }],
        ];
    }
}
