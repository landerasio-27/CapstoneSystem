<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends AdminApiController
{
    public function index(Request $request): JsonResponse
    {
        $users = User::with('role')
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($s) => $s
                ->where('name', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%")
                ->orWhere('employee_id', 'like', "%{$request->search}%")))
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => $this->transform($u));

        return $this->ok($users);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'employee_id' => ['nullable', 'string', 'max:255', 'unique:users,employee_id'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'role_id' => ['required', 'exists:roles,id'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $data['password'] = Hash::make($data['password']);
        $data['is_active'] = true;

        $user = User::create($data);
        ActivityLog::record('created', 'users', "Created user account for {$user->name}.");

        return $this->ok($this->transform($user->load('role')), 'User created.', 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'employee_id' => ['nullable', 'string', 'max:255', Rule::unique('users', 'employee_id')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'role_id' => ['required', 'exists:roles,id'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);
        ActivityLog::record('updated', 'users', "Updated user account for {$user->name}.");

        return $this->ok($this->transform($user->load('role')), 'User updated.');
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return $this->fail('You cannot delete your own account.');
        }

        $name = $user->name;
        $user->delete();
        ActivityLog::record('deleted', 'users', "Deleted user account for {$name}.");

        return $this->ok(null, 'User deleted.');
    }

    private function transform(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'employee_id' => $u->employee_id,
            'email' => $u->email,
            'contact_number' => $u->contact_number,
            'role_id' => $u->role_id,
            'role_name' => $u->role?->name,
            'role_display' => $u->role?->display_name,
            'is_active' => (bool) $u->is_active,
        ];
    }
}
