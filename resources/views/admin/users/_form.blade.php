@csrf

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Full Name</label>
        <input type="text" name="name" value="{{ old('name', $user->name ?? '') }}" required
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Employee ID</label>
        <input type="text" name="employee_id" value="{{ old('employee_id', $user->employee_id ?? '') }}"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        @error('employee_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Email</label>
        <input type="email" name="email" value="{{ old('email', $user->email ?? '') }}" required
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Contact Number</label>
        <input type="text" name="contact_number" value="{{ old('contact_number', $user->contact_number ?? '') }}"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        @error('contact_number') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Role</label>
        <select name="role_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Select role</option>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" @selected(old('role_id', $user->role_id ?? '') == $role->id)>{{ $role->display_name }}</option>
            @endforeach
        </select>
        @error('role_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div></div>

    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">
            Password @if(isset($user)) <span class="text-xs text-slate-400">(leave blank to keep unchanged)</span> @endif
        </label>
        <input type="password" name="password" {{ isset($user) ? '' : 'required' }}
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Confirm Password</label>
        <input type="password" name="password_confirmation" {{ isset($user) ? '' : 'required' }}
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
    </div>
</div>

<div class="mt-6 flex justify-end gap-3">
    <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"><x-icon name="x-mark" class="h-4 w-4" /> Cancel</a>
    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900"><x-icon name="check" class="h-4 w-4" /> Save</button>
</div>
