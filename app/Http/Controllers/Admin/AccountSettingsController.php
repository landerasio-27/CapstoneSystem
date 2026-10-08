<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Archive;
use App\Models\Role;
use App\Models\User;
use App\Support\ArchiveRegister;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountSettingsController extends Controller
{
    /**
     * One settings page, shown one section at a time: the sidebar rows link
     * back here with the section they want, so nothing else is rendered and no
     * other page is opened. Only the open section's data is read.
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $isAdmin = $user->isAdmin();

        $sections = [
            'profile' => ['label' => 'Profile', 'icon' => 'user'],
            'password' => ['label' => 'Change Password', 'icon' => 'lock'],
        ];

        // The back office is the administrator's. The audit trail belongs in
        // that set: it records what every account did — the supply staff
        // included — so it is not laid out in front of the people it keeps an
        // eye on. A supply staff account never sees the section, and asking for
        // it by name falls back to their own profile.
        if ($isAdmin) {
            $sections['users'] = ['label' => 'Users', 'icon' => 'users'];
            $sections['archive'] = ['label' => 'Archive', 'icon' => 'folder'];
            $sections['activity-logs'] = ['label' => 'Activity Logs', 'icon' => 'history'];
        }

        // An unknown — or not-allowed — section falls back to the first one
        // rather than rendering a blank page.
        $section = (string) $request->input('section', 'profile');
        if (! array_key_exists($section, $sections)) {
            $section = 'profile';
        }

        // The Archive panel is the register itself, not a summary of one: the
        // years, what is still waiting to be filed, and — when a year is opened
        // from the panel — what is filed under that year. Filing and restoring
        // post from this panel, so the whole job is done without leaving it.
        $archives = collect();
        $documents = $cards = null;
        $openYear = null;
        $yearDocuments = $yearCards = null;
        $cardType = null;

        if ($section === 'archive') {
            $archives = ArchiveRegister::years();
            $documents = ArchiveRegister::documents($request);
            $cards = ArchiveRegister::cards($request);
            $cardType = ArchiveRegister::cardType($request);

            // A year opened from the panel is shown in place of the register.
            // An id that names no year is left alone rather than failing the
            // page: the panel simply stays on the register.
            $openYear = $request->filled('archive')
                ? Archive::withCount(['documents', 'cards'])->find($request->integer('archive'))
                : null;

            if ($openYear) {
                $filed = ArchiveRegister::filed($openYear, $request);
                $yearDocuments = $filed['documents'];
                $yearCards = $filed['cards'];
            }
        }

        return view('admin.account-settings.index', [
            'user' => $user,
            'isAdmin' => $isAdmin,
            'sections' => $sections,
            'section' => $section,
            // Users: a compact reference strip — full management lives on its own page.
            'users' => $section === 'users'
                ? User::query()->with('role')->orderBy('name')->paginate(25)->withQueryString()
                : null,
            // The archive as it stands: the years, the register, and the year
            // that is open, if one is.
            'archives' => $archives,
            'documents' => $documents,
            'cards' => $cards,
            'cardType' => $cardType,
            'openYear' => $openYear,
            'yearDocuments' => $yearDocuments,
            'yearCards' => $yearCards,
            // Recent activity for the summary strip, narrowed by whatever the
            // panel's own filters ask for: the same five the full activity log
            // page offers, so the two read the same way.
            'recentActivity' => $section === 'activity-logs'
                ? ActivityLog::with('user')
                    ->when($request->filled('module'), fn ($q) => $q->where('module', $request->module))
                    ->when($request->filled('action'), fn ($q) => $q->where('action', $request->action))
                    ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->user_id))
                    ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
                    ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
                    ->latest()
                    ->limit(5)
                    ->get()
                : collect(),
            // The choices those filters are picked from, read only while the
            // panel is open.
            'activityModules' => $section === 'activity-logs'
                ? ActivityLog::query()->distinct()->orderBy('module')->pluck('module')
                : collect(),
            'activityActions' => $section === 'activity-logs'
                ? ActivityLog::query()->distinct()->orderBy('action')->pluck('action')
                : collect(),
            'activityUsers' => $section === 'activity-logs'
                ? User::query()->orderBy('name')->get()
                : collect(),
            // Roles for the inline add-user form.
            'roles' => Role::orderBy('display_name')->get(),
        ]);
    }

    /** Adding a user happens right inside the Users panel. */
    public function storeUser(Request $request)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'employee_id' => ['nullable', 'string', 'max:255', 'unique:users,employee_id'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role_id' => ['required', 'exists:roles,id'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $data['password'] = Hash::make($data['password']);
        $data['is_active'] = true;

        $user = User::create($data);

        ActivityLog::record('created', 'users', "Created user account for {$user->name}.");

        return redirect()
            ->route('admin.account-settings.index', ['section' => 'users'])
            ->with('success', "User \"{$user->name}\" created successfully.");
    }

    /** Adding an archive year happens right inside the Archive panel. */
    public function storeArchive(Request $request)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'year' => ['required', 'integer', 'min:1900', 'max:2100', Rule::unique('archives', 'year')],
        ], [], ['year' => 'archive year']);

        $archive = Archive::create($data + ['created_by' => auth()->id()]);

        ActivityLog::record('created', 'archives', "Added archive year {$archive->year}.");

        // The year just added is the one the register is filed under next, so
        // it opens selected in the "File under" pickers.
        return redirect()
            ->route('admin.account-settings.index', ['section' => 'archive', 'archive_id' => $archive->id])
            ->with('success', "Archive year {$archive->year} added.");
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }
}
