<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value', 'group', 'type', 'updated_by'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
