<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    public function create(): \Illuminate\View\View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'The provided credentials do not match our records.',
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'Your account has been deactivated. Contact the supply office administrator.',
            ]);
        }

        // The portal is shared by the office: an administrator runs it and the
        // supply staff work in it too. What each role may open is settled by the
        // role-gated navigation and by the pages themselves, so signing in only
        // asks that the account be a supply office account.
        if (! $user->isAdmin() && ! $user->isInspectionStaff()) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'This portal is for supply office accounts only.',
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        ActivityLog::record('login', 'auth', "{$user->name} logged in to the admin portal.");

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if ($user) {
            ActivityLog::record('logout', 'auth', "{$user->name} logged out of the admin portal.");
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
