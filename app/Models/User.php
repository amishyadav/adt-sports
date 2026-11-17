<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /**
     * HasRoles brings the spatie/laravel-permission relationships: roles(),
     * permissions(), hasRole(), can(), and so on.
     *
     * Note there are two notions of "role" on this model and they are related
     * but not the same:
     *
     *   $user->role   a string column holding the name of the assigned role.
     *                 This is what isAdmin() and the admin middleware read, so
     *                 it is the one that decides what a user can reach.
     *   $user->roles  the role record itself, from the Roles module.
     *
     * The Add/Edit User screens write both: the chosen role is assigned through
     * HasRoles and its name is mirrored into the column. The column is kept as
     * the gate because no permission is enforced anywhere yet — switching the
     * gate over to can() before the roles carry the right permissions would
     * lock people out of the panel. Until that happens only the role names in
     * PANEL_ROLES actually open the panel.
     */
    use HasFactory, Notifiable, HasRoles;

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_INACTIVE = 'inactive';

    /** Values offered by the gender field; stored lowercase, shown capitalised. */
    public const GENDERS = ['male' => 'Male', 'female' => 'Female', 'other' => 'Other'];

    /**
     * Role names that currently grant access to the admin panel.
     *
     * Matched without regard to case, because role names are typed by hand on
     * the Roles screen and come out as "Admin" as often as "admin". A user
     * holding any other role is a valid account but cannot reach the panel yet.
     */
    public const PANEL_ROLES = ['admin', 'editor'];

    protected $fillable = [
        'name', 'first_name', 'last_name', 'email', 'phone', 'gender',
        'password', 'role', 'status', 'avatar', 'bio', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    /**
     * Mirror the database defaults in memory.
     *
     * Without this a freshly created model has status = null until it is
     * reloaded, because Eloquent does not read column defaults back after an
     * insert — and isActive() would then be false for an account that is in
     * fact active, which the login check and the admin middleware both act on.
     */
    protected $attributes = [
        'role'   => 'editor',
        'status' => self::STATUS_ACTIVE,
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
        'password'          => 'hashed',
    ];

    /**
     * Keep `name` and first_name/last_name in step, in whichever direction the
     * caller wrote.
     *
     * `name` cannot simply be dropped: article bylines, the public author page
     * and its JSON-LD, the admin topbar and the dashboard greeting all read it.
     * Rather than rewrite every one of those, it is maintained here, which also
     * leaves the user factory, the seeder and the Settings profile tab — all of
     * which only know about `name` — working untouched.
     *
     * First/last wins when both were written, since that is the detailed form.
     */
    protected static function booted(): void
    {
        static::saving(function (self $user) {
            $first = trim((string) $user->first_name);
            $last  = trim((string) $user->last_name);

            if ($user->isDirty(['first_name', 'last_name']) && ($first !== '' || $last !== '')) {
                $user->name = trim($first . ' ' . $last);
                return;
            }

            if ($user->isDirty('name')) {
                $parts = preg_split('/\s+/', trim((string) $user->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $user->first_name = array_shift($parts) ?? '';
                $user->last_name  = implode(' ', $parts);
            }
        });
    }

    public function articles()
    {
        return $this->hasMany(Article::class, 'author_id');
    }

    /**
     * Compared without case because the role column mirrors a role name typed
     * on the Roles screen, which may well read "Admin".
     */
    public function isAdmin(): bool
    {
        return strcasecmp(trim((string) $this->role), 'admin') === 0;
    }

    public function isActive(): bool
    {
        return strcasecmp(trim((string) $this->status), self::STATUS_ACTIVE) === 0;
    }

    /** Whether this account's role is one that currently opens the admin panel. */
    public function hasPanelAccess(): bool
    {
        return self::roleGrantsPanelAccess($this->role);
    }

    /** Same test against a bare role name, for the role picker's hint text. */
    public static function roleGrantsPanelAccess(?string $roleName): bool
    {
        $name = trim((string) $roleName);

        foreach (self::PANEL_ROLES as $granted) {
            if (strcasecmp($name, $granted) === 0) {
                return true;
            }
        }

        return false;
    }

    /** Queue the reset email so it sends out-of-band (uses the branded message). */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\QueuedResetPassword($token));
    }

    /**
     * Two letters for the avatar bubble: first name + last name when they are
     * set, otherwise split whatever is in `name`.
     *
     * preg_split rather than explode so a stray double space does not produce
     * an empty second initial, and mb_* so an accented name is not cut mid
     * character.
     */
    public function getInitialsAttribute(): string
    {
        $first = trim((string) $this->first_name);
        $last  = trim((string) $this->last_name);

        if ($first === '' && $last === '') {
            $parts = preg_split('/\s+/', trim((string) $this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $first = $parts[0] ?? '';
            $last  = $parts[1] ?? '';
        }

        return mb_strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1));
    }

    /** Full name for display, falling back to the stored `name`. */
    public function getFullNameAttribute(): string
    {
        $joined = trim(trim((string) $this->first_name) . ' ' . trim((string) $this->last_name));

        return $joined !== '' ? $joined : (string) $this->name;
    }
}
