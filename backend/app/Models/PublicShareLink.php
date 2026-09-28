<?php

namespace App\Models;

use App\Enums\PublicSharePermission;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PublicShareLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'owner_id',
        'shareable_type',
        'shareable_id',
        'token_hash',
        'token_encrypted',
        'enabled',
        'permission',
        'password_hash',
        'allow_download',
        'expires_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (PublicShareLink $link): void {
            $link->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'permission' => PublicSharePermission::class,
            'allow_download' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
