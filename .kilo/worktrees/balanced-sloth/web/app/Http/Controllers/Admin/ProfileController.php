<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('admin.profile.edit', ['user' => auth()->user()]);
    }

    /**
     * Update the signed-in user's own name, position and email.
     *
     * The position is required: it is the office this account signs as, and it
     * is printed under the name on every RIS, monthly report and custodian slip
     * the office issues. An account that holds none can still read the register
     * — the dashboard asks it to set one — but it cannot be saved blank again
     * once it has been asked for.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update($data);

        ActivityLog::record('updated', 'profile', 'Updated own account profile.');

        return back()->with('success', 'Your profile has been updated.');
    }

    /**
     * Change the signed-in user's own password, after verifying the current one.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'min:8', 'different:current_password'],
        ]);

        $request->user()->update(['password' => Hash::make($data['password'])]);

        ActivityLog::record('updated', 'profile', 'Changed own account password.');

        return back()->with('success', 'Your password has been changed.');
    }
}
