<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Article;
use App\Models\Media;
use App\Models\User;
use App\Notifications\TeamInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Hash, Password as PasswordBroker};
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        abort_unless(Auth::user()->isAdmin(), 403);
        $users = User::withCount('articles')->latest()->get();
        return view('admin.users.index', compact('users'));
    }

    public function store(Request $request)
    {
        abort_unless(Auth::user()->isAdmin(), 403);
        $data = $request->validate([
            'name'  => 'required|string|max:100',
            'email' => 'required|email|unique:users',
            'role'  => 'required|in:admin,editor',
        ]);

        // Create with an unusable random password — the member sets their own via
        // the invite link, so the admin never knows or handles a password.
        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'role'     => $data['role'],
            'password' => Hash::make(Str::random(40)),
        ]);

        $token = PasswordBroker::broker()->createToken($user);

        try {
            $user->notify(new TeamInvitation($token, Auth::user()->name, $user->role));
            $msg = 'Invitation sent to ' . $user->email . '.';
        } catch (\Throwable $e) {
            report($e);
            $msg = 'Member added, but the invite email could not be sent — they can use "Forgot password?" on the login page to set their password.';
        }

        ActivityLog::record('user.invited', $user, 'Invited ' . $user->name . ' (' . $user->role . ')');

        return back()->with('success', $msg);
    }

    public function destroy(User $user)
    {
        abort_unless(Auth::user()->isAdmin(), 403);
        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        // Reassign the departing member's content to you so nothing is lost.
        // (The FK is now nullOnDelete as a safety net, but reassigning keeps a
        // real author on every article instead of orphaning it.)
        $reassigned = Article::withTrashed()->where('author_id', $user->id)->update(['author_id' => Auth::id()]);
        Media::where('uploaded_by', $user->id)->update(['uploaded_by' => Auth::id()]);

        $name = $user->name;
        $user->delete();

        ActivityLog::record('user.deleted', null,
            'Removed team member ' . $name . ($reassigned ? " ({$reassigned} article(s) reassigned to you)" : ''));

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
}
