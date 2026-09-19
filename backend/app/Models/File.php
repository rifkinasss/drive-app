<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class File extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'folder_id',
        'original_name',
        'stored_name',
        'disk',
        'path',
        'extension',
        'mime_type',
        'size_bytes',
        'checksum',
        'is_starred',
        'trashed_at',
        'trash_batch_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (File $file): void {
            $file->uuid ??= (string) Str::uuid();
            $file->stored_name ??= $file->uuid.'.bin';
        });
    }

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'is_starred' => 'boolean',
            'trashed_at' => 'datetime',
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

    public function folder()
    {
        return $this->belongsTo(Folder::class, 'folder_id');
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('owner_id', $user->getKey());
    }

    public function scopeNotTrashed(Builder $query): Builder
    {
        return $query->whereNull('trashed_at');
    }
}
