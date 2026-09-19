<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShareResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->user ?? $this->recipient;

        return [
            'id' => $this->uuid,
            'permission' => $this->permission === null ? 'owner' : $this->permission->value,
            'type' => $this->permission === null ? 'owner' : 'recipient',
            'user' => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
            ],
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
