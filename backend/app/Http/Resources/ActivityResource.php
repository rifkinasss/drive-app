<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'action' => $this->action->value,
            'actor' => $this->actor ? [
                'id' => $this->actor->getKey(),
                'name' => $this->actor->name,
            ] : null,
            'subject' => $this->subject_uuid || $this->subject_name ? [
                'type' => $this->subject_type,
                'id' => $this->subject_uuid,
                'name' => $this->subject_name,
            ] : null,
            'metadata' => $this->metadata ?? [],
            'createdAt' => $this->created_at?->utc()->toISOString(),
        ];
    }
}
