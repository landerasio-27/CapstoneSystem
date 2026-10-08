<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::with('role')
            ->when($request->filled('role'), fn ($q) => $q->whereHas('role', fn ($r) => $r->where('name', $request->role)))
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where(function ($sub) use ($request) {
                    $sub->where('name', 'like', "%{$request->search}%")
                        ->orWhere('email', 'like', "%{$request->search}%")
                        ->orWhere('employee_id', 'like', "%{$request->search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $roles = Role::orderBy('display_name')->get();

        return view('admin.users.index', compact('users', 'roles'));
    }

    public function create(): View
    {
        $roles = Role::orderBy('display_name')->get();

        return view('admin.users.create', compact('roles'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'employee_id' => ['nullable', 'string', 'max:255', 'unique:users,employee_id'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'role_id' => ['required', 'exists:roles,id'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $data['password'] = Hash::make($data['password']);
        $data['is_active'] = true;

        $user = User::create($data);

        ActivityLog::record('created', 'users', "Created user account for {$user->name}.");

        return redirect()->route('admin.users.index')->with('success', "User \"{$user->name}\" created successfully.");
    }

    public function edit(User $user): View
    {
        $roles = Role::orderBy('display_name')->get();

        return view('admin.users.edit', compact('user', 'roles'));
    }

    /** Editing an account in place: see resetPassword() for the password side. */
    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'employee_id' => ['nullable', 'string', 'max:255', Rule::unique('users', 'employee_id')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'role_id' => ['required', 'exists:roles,id'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        ActivityLog::record('updated', 'users', "Updated user account for {$user->name}.");

        // Back to the register the account was opened from: the editor is a
        // sheet over it, and saving must not move the reader off their place in
        // it — the panel that carries the register, or the register page.
        return back()->with('success', "User \"{$user->name}\" updated successfully.");
    }

    /**
     * Writing a new password for an account: the office's own reset, for the
     * account that has forgotten its own. The old password is never asked for —
     * an administrator cannot know it, and the person asking for the reset is
     * the one who has lost it — so the new one is typed twice instead, and the
     * account is written under it. Nothing else about the account is touched.
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [], ['password' => 'new password']);

        $user->update(['password' => Hash::make($data['password'])]);

        ActivityLog::record('updated', 'users', "Reset the password for {$user->name}.");

        return back()->with('success', "Password for \"{$user->name}\" has been reset.");
    }

    /**
     * Deleting an account. Two accounts are never deleted: the one the officer
     * is signed in as, whose session would be left hanging on a user that is
     * gone, and the last active administrator, without whom nobody could open
     * this register again. An account that holds records of its own is held back
     * too — a supply request it filed is deleted along with it rather than left
     * behind — and is asked to be deactivated instead, which keeps the record
     * and takes away the way in.
     */
    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($this->isLastActiveAdministrator($user)) {
            return back()->with('error', 'This is the office\'s only active administrator. Give another account the Admin role before deleting it.');
        }

        if ($held = $this->recordsHeldBy($user)) {
            return back()->with('error', "This account holds {$held} on file, which would be deleted along with it. Deactivate the account instead.");
        }

        $name = $user->name;
        $user->delete();

        ActivityLog::record('deleted', 'users', "Deleted user account for {$name}.");

        return back()->with('success', "User \"{$name}\" deleted successfully.");
    }

    /**
     * Taking an account's way in away, or giving it back. Deactivating is what
     * the office uses on an account that must keep its records and lose its
     * door, so the last active administrator cannot be deactivated any more than
     * it can be deleted.
     */
    public function toggleActive(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        if ($user->is_active && $this->isLastActiveAdministrator($user)) {
            return back()->with('error', 'This is the office\'s only active administrator. Give another account the Admin role before deactivating it.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        $state = $user->is_active ? 'activated' : 'deactivated';
        ActivityLog::record('updated', 'users', "{$state} user account for {$user->name}.");

        return back()->with('success', "User \"{$user->name}\" {$state}.");
    }

    /**
     * Whether this account is the one active administrator the office has left.
     * Taking it out of the office — by deleting it or by deactivating it — would
     * leave nobody able to open the register accounts are managed from.
     */
    private function isLastActiveAdministrator(User $user): bool
    {
        if (! $user->is_active || ! $user->isAdmin()) {
            return false;
        }

        return User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('name', Role::ADMIN))
            ->count() === 1;
    }

    /**
     * What the account holds on file that would be deleted along with it, read
     * as the words the refusal names them by — or null when there is nothing to
     * hold it back for.
     */
    private function recordsHeldBy(User $user): ?string
    {
        $held = [];

        if ($user->supplyRequests()->exists()) {
            $held[] = 'supply requests';
        }

        if (Report::query()->where('generated_by', $user->id)->exists()) {
            $held[] = 'generated reports';
        }

        return $held === [] ? null : implode(' and ', $held);
    }
}
