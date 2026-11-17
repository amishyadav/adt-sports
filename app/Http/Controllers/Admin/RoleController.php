<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Roles, from spatie/laravel-permission.
 *
 * This screen only names roles. Which permissions a role carries is set on the
 * Permissions screen, where the whole roles-by-permissions grid is visible at
 * once — deciding that one role at a time makes it hard to see what differs
 * between them.
 */
class RoleController extends Controller
{
    /**
     * Everything is created against the web guard, the only one the panel uses.
     *
     * Roles are unique on (name, guard_name) rather than name alone, so every
     * query here is scoped to the guard as well — otherwise a role belonging to
     * some future api guard would show up and be editable from this screen.
     */
    private const GUARD = 'web';

    public function index(Request $request)
    {
        $query = Role::query()
            ->where('guard_name', self::GUARD)
            ->withCount(['permissions', 'users'])
            ->orderBy('name');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $roles = $query->paginate(20)->withQueryString();

        return view('admin.roles.index', compact('roles'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('roles', 'name')->where('guard_name', self::GUARD),
            ],
        ], [
            'name.unique' => 'A role with that name already exists.',
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => self::GUARD]);

        ActivityLog::record('role.created', null, 'Created role "' . $role->name . '"');

        return back()->with('success', 'Role created.');
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('roles', 'name')
                    ->where('guard_name', self::GUARD)
                    ->ignore($role->id),
            ],
        ], [
            'name.unique' => 'A role with that name already exists.',
        ]);

        $was = $role->name;
        $role->update(['name' => $data['name']]);

        /*
         * Renaming is a real hazard with this package: roles are referenced by
         * NAME in code — hasRole('editor'), @role('editor'), middleware
         * 'role:editor' — so a rename silently breaks every one of those. The
         * panel does not gate on these roles yet, so nothing is broken today,
         * but the warning is worth carrying.
         */
        $message = $was === $role->name
            ? 'Role updated.'
            : "Role renamed to \"{$role->name}\". Anywhere the old name was written down in code will need updating.";

        ActivityLog::record('role.updated', null, 'Renamed role "' . $was . '" to "' . $role->name . '"');

        return back()->with('success', $message);
    }

    public function destroy(Role $role)
    {
        /*
         * Refuse while anyone still holds it. Deleting would cascade the pivot
         * rows away and silently strip those users of the role, which is the
         * sort of thing nobody notices until something stops working.
         */
        $holders = $role->users()->count();

        if ($holders > 0) {
            return back()->with(
                'error',
                "Cannot delete: {$holders} " . Str::plural('user', $holders)
                    . ' still ' . ($holders === 1 ? 'holds' : 'hold') . " the \"{$role->name}\" role."
            );
        }

        $name = $role->name;

        // The role_has_permissions rows go with it, by foreign key.
        $role->delete();

        ActivityLog::record('role.deleted', null, 'Deleted role "' . $name . '"');

        return back()->with('success', 'Role deleted.');
    }
}
