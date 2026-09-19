<?php

namespace App\Models;

use App\Enums\ActivityAction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Activity extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'user_id',
        'actor_id',
        'action',
        'subject_type',
        'subject_id',
        'subject_uuid',
        'subject_name',
        'metadata',
        'created_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (Activity $activity): void {
            $activity->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'action' => ActivityAction::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
