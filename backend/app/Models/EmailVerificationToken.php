<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailVerificationToken extends Model
{
    protected $fillable = [
        'user_id',
        'token_hash',
        'token_ciphertext',
        'expires_at',
        'sent_at',
        'verified_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'token_ciphertext' => 'encrypted',
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'verified_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
