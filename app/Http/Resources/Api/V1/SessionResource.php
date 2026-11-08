<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Tenancy\Models\Membership;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Membership */
class SessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
            'membership' => [
                'id' => $this->id,
                'role' => $this->role,
                'tenant' => [
                    'id' => $this->tenant->id,
                    'name' => $this->tenant->name,
                    'timezone' => $this->tenant->timezone,
                ],
            ],
        ];
    }
}
