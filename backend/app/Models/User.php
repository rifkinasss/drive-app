<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Notifications\QueuedPasswordResetNotification;
use Illuminate\Auth\Passwords\CanResetPassword as CanResetPasswordTrait;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements CanResetPassword
{
    use CanResetPasswordTrait, HasFactory, Notifiable;

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new QueuedPasswordResetNotification($token));
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'quota_bytes',
        'used_bytes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'quota_bytes' => 'integer',
            'used_bytes' => 'integer',
            'role' => UserRole::class,
            'status' => UserStatus::class,
        ];
    }

    public function invitations()
    {
        return $this->hasMany(UserInvitation::class);
    }

    public function emailVerificationTokens()
    {
        return $this->hasMany(EmailVerificationToken::class);
    }

    public function files()
    {
        return $this->hasMany(File::class, 'owner_id');
    }

    public function activities()
    {
        return $this->hasMany(Activity::class);
    }
}
