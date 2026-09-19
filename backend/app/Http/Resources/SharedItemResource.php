<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class SharedItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $item = $this->item;
        $isFile = $this->type === 'file';

        $result = [
            'type' => $this->type,
            'id' => $item->uuid,
            'name' => $isFile ? $item->original_name : $item->name,
            'owner' => ['id' => $this->owner->getKey(), 'name' => $this->owner->name],
            'permission' => $this->permission->value,
            'sharedAt' => $this->sharedAt ? Carbon::parse($this->sharedAt)->toISOString() : null,
            'mimeType' => $isFile ? $item->mime_type : null,
            'sizeBytes' => $isFile ? $item->size_bytes : null,
        ];

        if (isset($this->recipientCount)) {
            $result['recipientCount'] = $this->recipientCount;
            $result['recipients'] = $this->recipients;
            $result['publicLinkEnabled'] = (bool) ($this->publicLinkEnabled ?? false);
        }

        return $result;
    }
}
