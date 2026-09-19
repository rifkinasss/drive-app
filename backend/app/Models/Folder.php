<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Folder extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'owner_id',
        'parent_id',
        'trashed_at',
        'trash_batch_id',
        'is_starred',
    ];

    protected static function booted(): void
    {
        static::creating(function (Folder $folder): void {
            $folder->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'trashed_at' => 'datetime',
            'is_starred' => 'boolean',
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

    public function parent()
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Folder::class, 'parent_id');
    }

    public function files()
    {
        return $this->hasMany(File::class, 'folder_id');
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('owner_id', $user->getKey());
    }

    public function scopeNotTrashed(Builder $query): Builder
    {
        return $query->whereNull('trashed_at');
    }

    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }
}
