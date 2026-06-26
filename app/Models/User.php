<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'avatar', 'bio', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
        'password'          => 'hashed',
    ];

    public function articles()
    {
        return $this->hasMany(Article::class, 'author_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** Queue the reset email so it sends out-of-band (uses the branded message). */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\QueuedResetPassword($token));
    }

    public function getInitialsAttribute(): string
    {
        $parts = explode(' ', trim($this->name));
        $first = strtoupper(substr($parts[0], 0, 1));
        $second = isset($parts[1]) ? strtoupper(substr($parts[1], 0, 1)) : '';
        return $first . $second;
    }
}
