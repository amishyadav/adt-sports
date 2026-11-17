<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Article;
use App\Models\Media;
use App\Models\User;
use App\Support\ImageUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Hash};
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

/**
 * Users: the full account record, created and edited on their own pages.
 *
 * The role offered here comes from the Roles module. Saving does two things
 * with it — assigns the role through spatie, and mirrors its name into the
 * users.role column, which is what AdminMiddleware and User::isAdmin() read.
 * Both are written together so the gate and the assignment cannot drift apart.
 */
class UserController extends Controller
{
    /** Guard the Roles module works in; see RoleController. */
    private const GUARD = 'web';

    /** Where uploaded user photos live, mirroring TeamController's logos. */
    private const PHOTO_DIR = 'uploads/users';

    public function index(Request $request)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $query = User::withCount('articles')->latest();

        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where(fn ($q) => $q
                ->where('name', 'like', $term)
                ->orWhere('first_name', 'like', $term)
                ->orWhere('last_name', 'like', $term)
                ->orWhere('email', 'like', $term)
                ->orWhere('phone', 'like', $term));
        }

        if ($request->filled('role')) {
            // LOWER() on both sides because SQLite compares text case
            // sensitively, unlike MySQL's default collation.
            $query->whereRaw('LOWER(role) = ?', [mb_strtolower($request->role)]);
        }

        if ($request->filled('status')) {
            $query->whereRaw('LOWER(status) = ?', [mb_strtolower($request->status)]);
        }

        $users = $query->paginate(20)->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => $this->roles(),
        ]);
    }

    public function create()
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        return view('admin.users.create', ['roles' => $this->roles()]);
    }

    public function store(Request $request)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $data = $request->validate(
            $this->rules() + [
                // An administrator sets the password here, so it is required and
                // has to be typed twice. 'confirmed' pairs it with the
                // password_confirmation field.
                'password' => ['required', 'confirmed', Password::min(8)],
            ],
            $this->messages()
        );

        $role = $this->role($data['role_id']);

        $user = new User([
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'email'      => $data['email'],
            'phone'      => $this->blankToNull($data['phone'] ?? null),
            'gender'     => $this->blankToNull($data['gender'] ?? null),
            'status'     => $data['status'],
            'role'       => $role->name,
            'password'   => $data['password'], // hashed by the model's cast
        ]);

        if ($request->hasFile('photo')) {
            $user->avatar = ImageUpload::store($request->file('photo'), self::PHOTO_DIR);
        }

        $user->save();
        $user->syncRoles([$role]);

        ActivityLog::record('user.created', $user,
            'Added user ' . $user->name . ' (' . $role->name . ')');

        return redirect()->route('admin.users.index')
            ->with('success', $user->name . ' added.' . $this->accessNote($role->name));
    }

    public function edit(User $user)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $roles = $this->roles();

        return view('admin.users.edit', [
            'user'  => $user,
            'roles' => $roles,
            // Prefer the assigned role; fall back to matching the column by
            // name, for an account that predates the Roles module.
            'currentRoleId' => $user->roles->first()?->id
                ?? $roles->first(fn ($r) => strcasecmp($r->name, (string) $user->role) === 0)?->id,
        ]);
    }

    public function update(Request $request, User $user)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $data = $request->validate(
            $this->rules($user->id) + [
                // Blank means "leave the password alone", so it is only checked
                // when something was actually typed.
                'password' => ['nullable', 'confirmed', Password::min(8)],
            ],
            $this->messages()
        );

        $role = $this->role($data['role_id']);

        if ($refusal = $this->lockoutRefusal($user, $role, $data['status'])) {
            return back()->with('error', $refusal)->withInput();
        }

        // Captured before the row changes so the old file is only binned once
        // the record pointing at it has been saved — same order as TeamController.
        $oldPhoto = $user->avatar;

        $user->fill([
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'email'      => $data['email'],
            'phone'      => $this->blankToNull($data['phone'] ?? null),
            'gender'     => $this->blankToNull($data['gender'] ?? null),
            'status'     => $data['status'],
            'role'       => $role->name,
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password']; // hashed by the model's cast
        }

        if ($request->hasFile('photo')) {
            $user->avatar = ImageUpload::store($request->file('photo'), self::PHOTO_DIR);
        } elseif ($request->boolean('remove_photo')) {
            $user->avatar = null;
        }

        $user->save();
        $user->syncRoles([$role]);

        if ($oldPhoto && $oldPhoto !== $user->avatar) {
            ImageUpload::delete($oldPhoto);
        }

        ActivityLog::record('user.updated', $user,
            'Updated user ' . $user->name . ' (' . $role->name . ', ' . $user->status . ')');

        return redirect()->route('admin.users.index')
            ->with('success', $user->name . ' updated.' . $this->accessNote($role->name));
    }

    public function destroy(User $user)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->isAdmin() && $user->isActive() && ! $this->otherActiveAdminsExist($user->id)) {
            return back()->with('error',
                'This is the only active administrator left — deleting it would lock everyone out of the panel.');
        }

        // Reassign the departing member's content to you so nothing is lost.
        // (The FK is now nullOnDelete as a safety net, but reassigning keeps a
        // real author on every article instead of orphaning it.)
        $reassigned = Article::withTrashed()->where('author_id', $user->id)->update(['author_id' => Auth::id()]);
        Media::where('uploaded_by', $user->id)->update(['uploaded_by' => Auth::id()]);

        $name  = $user->name;
        $photo = $user->avatar;

        $user->delete();
        ImageUpload::delete($photo);

        ActivityLog::record('user.deleted', null,
            'Removed user ' . $name . ($reassigned ? " ({$reassigned} article(s) reassigned to you)" : ''));

        return back()->with('success',
            'User removed.' . ($reassigned ? " {$reassigned} article(s) reassigned to you." : ''));
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|unique:users,email,'.$user->id,
            'password' => ['nullable', Password::min(8)],
        ]);
        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }
        $user->update($data);
        return back()->with('success', 'Profile updated.');
    }

    /* ─── helpers ──────────────────────────────────────────── */

    /** Validation shared by store and update; $ignoreId exempts the row being edited. */
    private function rules(?int $ignoreId = null): array
    {
        return [
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'email'      => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ignoreId)],
            // Deliberately loose: numbers arrive with country codes, spaces,
            // dashes and brackets, and none of those are worth rejecting.
            'phone'      => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s().]+$/'],
            'gender'     => ['nullable', Rule::in(array_keys(User::GENDERS))],
            'status'     => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE])],
            'role_id'    => ['required', 'integer', Rule::exists('roles', 'id')->where('guard_name', self::GUARD)],
            'photo'      => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
        ];
    }

    private function messages(): array
    {
        return [
            'email.unique'      => 'Another account already uses that email address.',
            'phone.regex'       => 'The phone number can only contain digits, spaces and + - ( ) .',
            'role_id.required'  => 'Choose a role for this user.',
            'role_id.exists'    => 'That role no longer exists — pick another.',
            'password.confirmed' => 'The two passwords do not match.',
        ];
    }

    /** Roles offered by the picker, from the Roles module. */
    private function roles()
    {
        return Role::where('guard_name', self::GUARD)->orderBy('name')->get();
    }

    private function role(int $id): Role
    {
        return Role::where('guard_name', self::GUARD)->findOrFail($id);
    }

    /**
     * An emptied optional field should read as "no value", not as an empty
     * string, so that a cleared phone or gender is NULL in the database and
     * whereNull() finds it.
     *
     * The framework's ConvertEmptyStringsToNull middleware usually does this
     * already; doing it here as well means the column shape does not depend on
     * which middleware happens to be in the stack.
     */
    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Refuse a change that would leave nobody able to administer the panel.
     *
     * Returns the reason, or null when the change is safe. The two self checks
     * matter most: an administrator editing their own row is one careless
     * dropdown away from locking themselves out of every admin-only screen,
     * and there is no way back in through the interface.
     */
    private function lockoutRefusal(User $user, Role $role, string $newStatus): ?string
    {
        $becomesAdmin  = strcasecmp($role->name, 'admin') === 0;
        $becomesActive = $newStatus === User::STATUS_ACTIVE;
        $isSelf        = $user->id === Auth::id();

        if ($isSelf && ! $becomesActive) {
            return 'You cannot deactivate your own account.';
        }

        if ($isSelf && $user->isAdmin() && ! $becomesAdmin) {
            return 'You cannot move your own account off the administrator role — '
                . 'you would immediately lose access to this screen. Ask another administrator to do it.';
        }

        if ($user->isAdmin() && $user->isActive()
            && (! $becomesAdmin || ! $becomesActive)
            && ! $this->otherActiveAdminsExist($user->id)) {
            return 'This is the only active administrator left, so its role and status cannot be changed.';
        }

        return null;
    }

    private function otherActiveAdminsExist(int $exceptUserId): bool
    {
        return User::where('id', '!=', $exceptUserId)
            ->whereRaw('LOWER(role) = ?', ['admin'])
            ->whereRaw('LOWER(status) = ?', [User::STATUS_ACTIVE])
            ->exists();
    }

    /**
     * Warn when the saved role is not one the panel opens for.
     *
     * The account is perfectly valid, it simply cannot sign in to the admin
     * side yet, and saying nothing here would look like a bug.
     */
    private function accessNote(string $roleName): string
    {
        if (User::roleGrantsPanelAccess($roleName)) {
            return '';
        }

        return ' Note: the "' . $roleName . '" role does not grant admin panel access yet,'
            . ' so this account cannot sign in to the panel.';
    }
}
