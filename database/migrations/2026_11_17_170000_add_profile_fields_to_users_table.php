<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Give users the fields the new Add/Edit User screens collect, and make the
 * role column able to hold a role name chosen from the Roles module.
 *
 * The single `name` column stays, and stays populated. Around fifteen places
 * read it — article bylines, the public author page and its JSON-LD, the admin
 * topbar — so it is kept in step with first_name/last_name by a hook on the
 * User model rather than being replaced here.
 */
return new class extends Migration
{
    /** Guard the Roles module works in; see RoleController. */
    private const GUARD = 'web';

    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('first_name')->nullable()->after('name');
            $t->string('last_name')->nullable()->after('first_name');
            $t->string('phone', 30)->nullable()->after('email');
            $t->string('gender', 20)->nullable()->after('phone');

            // Plain string, not an enum: SQLite cannot alter a check constraint
            // in place, which is why team_matches.status took the same shape.
            $t->string('status', 20)->default('active')->after('role');
        });

        /*
         * role was enum('admin','editor'). The Role dropdown now lists whatever
         * the Roles module holds, so the column has to accept any of those
         * names. Widened rather than dropped because it is still the column the
         * admin gate reads — AdminMiddleware, EnsureAdmin and User::isAdmin().
         */
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('editor')->change();
        });

        $this->splitExistingNames();
        $this->seedRolesFromExistingUsers();
    }

    /**
     * Fill first_name/last_name from the name already on each row.
     *
     * Everything before the first space is the first name and the remainder is
     * the last name, which is the same split the initials accessor used, so
     * nothing visible changes for existing accounts.
     */
    private function splitExistingNames(): void
    {
        foreach (DB::table('users')->select('id', 'name')->orderBy('id')->cursor() as $u) {
            $parts = preg_split('/\s+/', trim((string) $u->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $first = array_shift($parts) ?? '';

            DB::table('users')->where('id', $u->id)->update([
                'first_name' => $first,
                'last_name'  => implode(' ', $parts),
            ]);
        }
    }

    /**
     * Make sure the Roles module has a role for every value the column already
     * holds, plus the two the panel enforces, and give each existing user the
     * matching role.
     *
     * Without this the Role dropdown on a brand new install would be empty, and
     * editing an existing user would show no role selected even though their
     * column says 'admin'.
     */
    private function seedRolesFromExistingUsers(): void
    {
        $rolesTable = config('permission.table_names.roles');
        $pivotTable = config('permission.table_names.model_has_roles');
        $morphKey   = config('permission.column_names.model_morph_key');

        if (! Schema::hasTable($rolesTable) || ! Schema::hasTable($pivotTable)) {
            return; // Roles module not installed; nothing to seed into.
        }

        $morphType = (new \App\Models\User())->getMorphClass();
        $now       = now();

        $names = DB::table('users')->distinct()->pluck('role')
            ->map(fn ($r) => trim((string) $r))
            ->filter()
            ->merge(['admin', 'editor'])
            ->unique();

        foreach ($names as $name) {
            $roleId = DB::table($rolesTable)
                ->where('name', $name)->where('guard_name', self::GUARD)->value('id');

            if (! $roleId) {
                $roleId = DB::table($rolesTable)->insertGetId([
                    'name'       => $name,
                    'guard_name' => self::GUARD,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $userIds = DB::table('users')->where('role', $name)->pluck('id');

            foreach ($userIds as $userId) {
                $already = DB::table($pivotTable)
                    ->where('role_id', $roleId)
                    ->where('model_type', $morphType)
                    ->where($morphKey, $userId)
                    ->exists();

                if (! $already) {
                    DB::table($pivotTable)->insert([
                        'role_id'    => $roleId,
                        'model_type' => $morphType,
                        $morphKey    => $userId,
                    ]);
                }
            }
        }

        // The package caches the role/permission map; a stale cache here would
        // hide the roles that were just created.
        if (app()->bound(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        /*
         * Put the enum back. Any role name outside the original two would
         * violate it, so those rows fall back to the lower tier first — the
         * same thing the old column would have stored.
         */
        DB::table('users')->whereNotIn('role', ['admin', 'editor'])->update(['role' => 'editor']);

        Schema::table('users', function (Blueprint $t) {
            $t->enum('role', ['admin', 'editor'])->default('editor')->change();
        });

        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['first_name', 'last_name', 'phone', 'gender', 'status']);
        });

        // Roles themselves are left alone: they are the Roles module's data now,
        // and may well have been edited there since this ran.
    }
};
