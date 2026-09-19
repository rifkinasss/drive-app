<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemSettingGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'group' => $this->resource['group'],
            'settings' => $this->resource['settings'],
        ];
    }
}
