<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    public $timestamps = false;

    protected $fillable = ['email', 'name', 'source', 'ip', 'created_at', 'verified_at', 'confirmation_token'];

    protected $casts = [
        'created_at'  => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function scopeVerified($q)
    {
        return $q->whereNotNull('verified_at');
    }
}
