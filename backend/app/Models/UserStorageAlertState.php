<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserStorageAlertState extends Model
{
    protected $fillable = ['user_id', 'notified_thresholds'];

    protected function casts(): array
    {
        return ['notified_thresholds' => 'json'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
