<?php

namespace App\Support;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Per-inbox rate limiting shared by every subscribe path (comment gate +
 * newsletter widget), so the confirmation-email cap can't be bypassed by
 * alternating endpoints or by sub-addressing tricks.
 */
class SubscribeThrottle
{
    public const MAX_PER_DAY = 3;
    private const BURST_SECONDS = 120;   // 1 per 2 minutes
    private const DAY_SECONDS   = 86400; // MAX_PER_DAY per 24h

    /**
     * Reduce an address to the inbox it actually reaches: strip +tags for
     * everyone, and dots for Gmail (which ignores them). Used for keys only —
     * the address typed is still what we store and send to.
     */
    public static function canonicalEmail(string $email): string
    {
        $email = Str::lower(trim($email));
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($domain === '' || $local === '') {
            return $email;
        }

        $local = Str::before($local, '+');

        if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
            $local = str_replace('.', '', $local);
        }

        return $local . '@' . $domain;
    }

    public static function burstKey(string $email): string
    {
        return 'subscribe-burst:' . sha1(self::canonicalEmail($email));
    }

    public static function dayKey(string $email): string
    {
        return 'subscribe-day:' . sha1(self::canonicalEmail($email));
    }

    /** True if this inbox has hit the burst cooldown or the daily cap. */
    public static function tooMany(string $email): bool
    {
        return RateLimiter::tooManyAttempts(self::burstKey($email), 1)
            || RateLimiter::tooManyAttempts(self::dayKey($email), self::MAX_PER_DAY);
    }

    /** Record a successful send. */
    public static function hit(string $email): void
    {
        RateLimiter::hit(self::burstKey($email), self::BURST_SECONDS);
        RateLimiter::hit(self::dayKey($email), self::DAY_SECONDS);
    }

    /** Release the burst cooldown (e.g. when a queued send fails) so the user can retry. */
    public static function clearBurst(string $email): void
    {
        RateLimiter::clear(self::burstKey($email));
    }
}
