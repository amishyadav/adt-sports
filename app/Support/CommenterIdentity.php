<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * A lightweight, cookie-based commenter identity — no accounts/passwords.
 *
 * Two cookies are issued when someone subscribes-to-comment:
 *  - adt_commenter       (encrypted, httpOnly): the authoritative {name,email},
 *                         read server-side to attribute and store a comment.
 *  - adt_commenter_name  (plaintext, JS-readable; excluded from cookie
 *                         encryption in bootstrap/app.php): the display name
 *                         only, so the cached article page can toggle the
 *                         subscribe-gate vs the comment box client-side.
 */
class CommenterIdentity
{
    public const COOKIE      = 'adt_commenter';
    public const NAME_COOKIE = 'adt_commenter_name';
    private const TTL_MINUTES = 60 * 24 * 365; // 1 year

    /** Decode the authoritative identity, or null if absent/malformed. */
    public static function get(Request $request): ?array
    {
        $raw = $request->cookie(self::COOKIE);
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $data = json_decode($raw, true);
        if (! is_array($data) || empty($data['name']) || empty($data['email'])) {
            return null;
        }

        return ['name' => $data['name'], 'email' => $data['email']];
    }

    /** Cookies to attach to a response after a successful subscribe-to-comment. */
    public static function issue(string $name, string $email): array
    {
        return [
            Cookie::make(self::COOKIE, json_encode(['name' => $name, 'email' => $email]), self::TTL_MINUTES, httpOnly: true),
            Cookie::make(self::NAME_COOKIE, $name, self::TTL_MINUTES, httpOnly: false),
        ];
    }

    /** Cookies to clear on sign-out. */
    public static function forget(): array
    {
        return [
            Cookie::forget(self::COOKIE),
            Cookie::forget(self::NAME_COOKIE),
        ];
    }
}
