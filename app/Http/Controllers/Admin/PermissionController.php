<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions, from spatie/laravel-permission, plus the grid that grants them
 * to roles.
 *
 * The grid is the point of this screen: permissions down the side, roles across
 * the top, a checkbox at each crossing. Granting one role at a time hides the
 * thing an administrator actually wants to see — how two roles differ.
 */
class PermissionController extends Controller
{
    /** The only guard the panel uses; see RoleController for why it is scoped. */
    private const GUARD = 'web';

    /** Rows per page on the grid. */
    private const PER_PAGE = 25;

    public function index(Request $request)
    {
        $query = Permission::query()
            ->where('guard_name', self::GUARD)
            ->withCount('roles')
            ->orderBy('name');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $permissions = $query->paginate(self::PER_PAGE)->withQueryString();

        $roles = Role::query()
            ->where('guard_name', self::GUARD)
            ->orderBy('name')
            ->get();

        /*
         * Which of the shown permissions each role already has, as a lookup the
         * view can read directly: $granted[roleId][permissionId].
         *
         * Loaded as one query over the pivot rather than lazily per row, which
         * at 25 permissions x however many roles would otherwise be a lot of
         * round trips to draw one table.
         */
        $granted = [];

        if ($roles->isNotEmpty() && $permissions->isNotEmpty()) {
            $pivot = DB::table(config('permission.table_names.role_has_permissions'))
                ->whereIn('role_id', $roles->pluck('id'))
                ->whereIn('permission_id', collect($permissions->items())->pluck('id'))
                ->get();

            foreach ($pivot as $row) {
                $granted[$row->role_id][$row->permission_id] = true;
            }
        }

        return view('admin.permissions.index', compact('permissions', 'roles', 'granted'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('permissions', 'name')->where('guard_name', self::GUARD),
            ],
        ], [
            'name.unique' => 'That permission already exists.',
        ]);

        $permission = Permission::create(['name' => $data['name'], 'guard_name' => self::GUARD]);

        ActivityLog::record('permission.created', null, 'Created permission "' . $permission->name . '"');

        return back()->with('success', 'Permission created.');
    }

    public function update(Request $request, Permission $permission)
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('permissions', 'name')
                    ->where('guard_name', self::GUARD)
                    ->ignore($permission->id),
            ],
        ], [
            'name.unique' => 'That permission already exists.',
        ]);

        $was = $permission->name;
        $permission->update(['name' => $data['name']]);

        // Same hazard as renaming a role: permissions are referred to by name
        // in code, so a rename breaks every can('…') that used the old one.
        $message = $was === $permission->name
            ? 'Permission updated.'
            : "Permission renamed to \"{$permission->name}\". Anywhere the old name was written down in code will need updating.";

        ActivityLog::record('permission.updated', null, 'Renamed permission "' . $was . '" to "' . $permission->name . '"');

        return back()->with('success', $message);
    }

    public function destroy(Permission $permission)
    {
        $name = $permission->name;

        // Pivot rows for roles and for any directly-assigned model go with it,
        // by foreign key — so this revokes the permission everywhere at once.
        $permission->delete();

        ActivityLog::record('permission.deleted', null, 'Deleted permission "' . $name . '"');

        return back()->with('success', 'Permission deleted.');
    }

    /**
     * Save the grid.
     *
     * Only the rows and columns that were on screen are touched. The form sends
     * back which permissions and roles it drew, and anything outside that set
     * is left exactly as it was — without that, saving page 2 of a paginated
     * grid would strip every permission that happened to be on page 1, because
     * an unticked box and a box that was never rendered look identical in a
     * form submission.
     */
    public function sync(Request $request)
    {
        $data = $request->validate([
            'page_permissions'   => 'required|array',
            'page_permissions.*' => 'integer|exists:permissions,id',
            'page_roles'         => 'required|array',
            'page_roles.*'       => 'integer|exists:roles,id',
            'grid'               => 'nullable|array',
            'grid.*'             => 'nullable|array',
            'grid.*.*'           => 'integer|exists:permissions,id',
        ]);

        $onPage = collect($data['page_permissions'])->map('intval');
        $grid = $data['grid'] ?? [];

        $roles = Role::with('permissions')
            ->where('guard_name', self::GUARD)
            ->whereIn('id', $data['page_roles'])
            ->get();

        $changed = 0;

        foreach ($roles as $role) {
            $before = $role->permissions->pluck('id')->map('intval');

            // Ticked in this submission, ignoring anything not actually drawn.
            $ticked = collect($grid[$role->id] ?? [])
                ->map('intval')
                ->filter(fn ($id) => $onPage->contains($id));

            // Held already but not on screen, so not up for decision here.
            $untouched = $before->reject(fn ($id) => $onPage->contains($id));

            $after = $untouched->merge($ticked)->unique()->sort()->values();

            if ($after->all() === $before->unique()->sort()->values()->all()) {
                continue;
            }

            $role->syncPermissions(Permission::whereIn('id', $after)->get());
            $changed++;
        }

        /*
         * The package caches the permission map, and a stale cache is the
         * classic reason a freshly granted permission appears to do nothing.
         * syncPermissions already flushes it, but this runs over several roles
         * and costs nothing to make certain of.
         */
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($changed === 0) {
            return back()->with('success', 'No changes to save.');
        }

        ActivityLog::record(
            'permission.synced',
            null,
            'Updated permissions for ' . $changed . ' ' . Str::plural('role', $changed)
        );

        return back()->with('success', 'Permissions saved for ' . $changed . ' ' . Str::plural('role', $changed) . '.');
    }
}
