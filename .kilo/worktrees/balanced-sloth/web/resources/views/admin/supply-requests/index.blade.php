@extends('layouts.admin')

@section('title', 'Supply Requests')

@section('content')
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form method="GET" class="flex gap-2">
            <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" onchange="this.form.submit()">
                <option value="">All statuses</option>
                @foreach (['pending', 'approved', 'rejected', 'issued'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </form>

        <a href="{{ route('admin.supply-requests.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900">
            <x-icon name="plus" class="h-4 w-4" /> New Request
        </a>
    </div>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Request No.</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Requested By</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Items</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Status</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Date</th>
                    <th class="px-4 py-3 text-right font-medium text-slate-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($requests as $req)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $req->request_no }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $req->requestedBy?->name }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $req->items->count() }} item(s)</td>
                        <td class="px-4 py-3">
                            @php
                                $badges = ['pending' => 'bg-amber-50 text-amber-700', 'approved' => 'bg-sky-50 text-sky-700', 'rejected' => 'bg-red-50 text-red-700', 'issued' => 'bg-brand-50 text-brand-700'];
                            @endphp
                            <span class="rounded-full px-2 py-1 text-xs font-medium {{ $badges[$req->status] }}">{{ ucfirst($req->status) }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $req->created_at->format('M d, Y') }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.supply-requests.show', $req) }}" class="text-brand-600 hover:underline">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No supply requests found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $requests->links() }}</div>
@endsection
