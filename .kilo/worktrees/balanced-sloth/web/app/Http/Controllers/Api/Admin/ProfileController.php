<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends AdminApiController
{
    public function show(Request $request): JsonResponse
    {
        return $this->ok($this->transform($request->user()->load('role')));
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'employee_id' => ['nullable', 'string', 'max:255', Rule::unique('users', 'employee_id')->ignore($user->id)],
            'contact_number' => ['nullable', 'string', 'max:50'],
        ]);

        $user->update($data);
        ActivityLog::record('updated', 'profile', 'Updated own account profile.');

        return $this->ok($this->transform($user->load('role')), 'Profile updated.');
    }

    public function password(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'min:8', 'different:current_password'],
        ]);

        $request->user()->update(['password' => Hash::make($data['password'])]);
        ActivityLog::record('updated', 'profile', 'Changed own account password.');

        return $this->ok(null, 'Password changed.');
    }

    private function transform($u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'employee_id' => $u->employee_id,
            'contact_number' => $u->contact_number,
            'role' => $u->role?->display_name,
        ];
    }
}
