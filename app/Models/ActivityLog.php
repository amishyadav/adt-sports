<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class ActivityLog extends Model
{
    public $timestamps = false; // immutable log — created_at is set on write, never updated

    protected $fillable = ['user_id', 'action', 'subject_type', 'subject_id', 'description', 'ip', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record an admin action. Never throws into the caller — an audit-log
     * failure must not break the action it was trying to record.
     */
    public static function record(string $action, ?Model $subject = null, ?string $description = null): void
    {
        try {
            static::create([
                'user_id'      => Auth::id(),
                'action'       => $action,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id'   => $subject?->getKey(),
                'description'  => $description,
                'ip'           => request()?->ip(),
                'created_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
