<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $quota = max(0, (int) $this->quota_bytes);
        $used = max(0, (int) $this->used_bytes);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role?->value,
            'status' => $this->status?->value,
            'emailVerifiedAt' => $this->email_verified_at?->toISOString(),
            'quotaBytes' => $quota,
            'usedBytes' => $used,
            'availableBytes' => max($quota - $used, 0),
            'usagePercentage' => $quota > 0 ? round(($used / $quota) * 100, 2) : 0,
            'createdAt' => $this->created_at?->toISOString(),
            'lastActiveAt' => $this->last_active_at?->toISOString(),
        ];
    }
}
