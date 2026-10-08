@extends('layouts.admin')

@section('title', 'Account Settings')

@section('content')
    <div class="mx-auto max-w-3xl space-y-6">
        {{-- Profile information --}}
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <div class="mb-5">
                <h2 class="text-base font-semibold text-slate-800">Profile Information</h2>
                <p class="mt-1 text-sm text-slate-500">Update your account's name, position and email address.</p>
            </div>

            <form method="POST" action="{{ route('admin.profile.update') }}" class="space-y-4">
                @csrf
                @method('PATCH')

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Full Name</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    {{-- Asked for rather than offered: the position prints under
                         the name on every report this office signs. --}}
                    <label class="mb-1 block text-sm font-medium text-slate-700">Position</label>
                    <input type="text" name="position" value="{{ old('position', $user->position) }}" required
                           placeholder="e.g. Administrative Officer I (Supply Officer I)"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    <p class="mt-1 text-xs text-slate-400">Prints under your name on the reports this office signs.</p>
                    @error('position') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Email Address</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900"><x-icon name="check" class="h-4 w-4" /> Save Changes</button>
                </div>
            </form>
        </div>

        {{-- Change password --}}
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <div class="mb-5">
                <h2 class="text-base font-semibold text-slate-800">Change Password</h2>
                <p class="mt-1 text-sm text-slate-500">Use a strong password of at least 8 characters that you don't use elsewhere.</p>
            </div>

            <form method="POST" action="{{ route('admin.profile.password') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Current Password</label>
                    <input type="password" name="current_password" required autocomplete="current-password"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    @error('current_password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">New Password</label>
                        <input type="password" name="password" required autocomplete="new-password"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                        @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Confirm New Password</label>
                        <input type="password" name="password_confirmation" required autocomplete="new-password"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900"><x-icon name="check" class="h-4 w-4" /> Update Password</button>
                </div>
            </form>
        </div>
    </div>
@endsection
