<?php

namespace App\Models;

use App\Enums\SharePermission;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class InternalShare extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'owner_id',
        'recipient_id',
        'shareable_type',
        'shareable_id',
        'permission',
    ];

    protected static function booted(): void
    {
        static::creating(function (InternalShare $share): void {
            $share->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return ['permission' => SharePermission::class];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function shareable()
    {
        return $this->morphTo();
    }
}
