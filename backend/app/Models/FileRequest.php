<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FileRequest extends Model
{
    protected $fillable = ['uuid', 'owner_id', 'folder_id', 'title', 'token_hash', 'token_encrypted', 'enabled', 'expires_at'];

    protected static function booted(): void
    {
        static::creating(fn (FileRequest $request): ?string => $request->uuid ??= (string) Str::uuid());
    }

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'expires_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function owner()
    {
        return $this->belongsTo(User::class);
    }

    public function folder()
    {
        return $this->belongsTo(Folder::class);
    }
}
