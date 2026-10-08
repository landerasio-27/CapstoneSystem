@extends('layouts.admin')

@section('title', $supplyRequest->request_no)

@section('content')
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-slate-800">{{ $supplyRequest->request_no }}</h2>
                    @php
                        $badges = ['pending' => 'bg-amber-50 text-amber-700', 'approved' => 'bg-sky-50 text-sky-700', 'rejected' => 'bg-red-50 text-red-700', 'issued' => 'bg-brand-50 text-brand-700'];
                    @endphp
                    <span class="rounded-full px-2 py-1 text-xs font-medium {{ $badges[$supplyRequest->status] }}">{{ ucfirst($supplyRequest->status) }}</span>
                </div>

                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-slate-500">Requested By</dt><dd class="font-medium text-slate-800">{{ $supplyRequest->requestedBy?->name }}</dd></div>
                    <div><dt class="text-slate-500">Date</dt><dd class="font-medium text-slate-800">{{ $supplyRequest->created_at->format('M d, Y') }}</dd></div>
                    <div class="col-span-2"><dt class="text-slate-500">Purpose</dt><dd class="font-medium text-slate-800">{{ $supplyRequest->purpose ?? '—' }}</dd></div>
                    @if ($supplyRequest->status === 'rejected')
                        <div class="col-span-2"><dt class="text-slate-500">Rejection Reason</dt><dd class="font-medium text-red-700">{{ $supplyRequest->rejection_reason ?? '—' }}</dd></div>
                    @endif
                </dl>

                <form method="POST" action="{{ $supplyRequest->status === 'pending' ? route('admin.supply-requests.approve', $supplyRequest) : '#' }}" class="mt-6">
                    @csrf
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead>
                            <tr>
                                <th class="px-2 py-2 text-left font-medium text-slate-500">Supply</th>
                                <th class="px-2 py-2 text-left font-medium text-slate-500">Requested</th>
                                @if ($supplyRequest->status === 'pending')
                                    <th class="px-2 py-2 text-left font-medium text-slate-500">Approve Qty</th>
                                @else
                                    <th class="px-2 py-2 text-left font-medium text-slate-500">Approved</th>
                                    <th class="px-2 py-2 text-left font-medium text-slate-500">Issued</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($supplyRequest->items as $item)
                                <tr>
                                    <td class="px-2 py-2 text-slate-800">{{ $item->supply->name }}</td>
                                    <td class="px-2 py-2 text-slate-500">{{ $item->quantity_requested }} {{ $item->supply->unit }}</td>
                                    @if ($supplyRequest->status === 'pending')
                                        <td class="px-2 py-2">
                                            <input type="number" min="0" max="{{ $item->quantity_requested }}"
                                                   name="items[{{ $item->id }}][quantity_approved]"
                                                   value="{{ $item->quantity_requested }}"
                                                   class="w-24 rounded-lg border border-slate-300 px-2 py-1 text-sm">
                                        </td>
                                    @else
                                        <td class="px-2 py-2 text-slate-500">{{ $item->quantity_approved }}</td>
                                        <td class="px-2 py-2 text-slate-500">{{ $item->quantity_issued }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    @if ($supplyRequest->status === 'pending')
                        <div class="mt-4 flex justify-end gap-3">
                            <button type="submit" formaction="{{ route('admin.supply-requests.reject', $supplyRequest) }}"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-red-300 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                <x-icon name="x-mark" class="h-4 w-4" /> Reject
                            </button>
                            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900">
                                <x-icon name="check" class="h-4 w-4" /> Approve
                            </button>
                        </div>
                    @endif
                </form>

                @if ($supplyRequest->status === 'approved')
                    <form method="POST" action="{{ route('admin.supply-requests.issue', $supplyRequest) }}" class="mt-4 flex justify-end">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900">
                            <x-icon name="send" class="h-4 w-4" /> Issue Supplies &amp; Generate RIS
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <div>
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h2 class="mb-4 text-base font-semibold text-slate-800">Generated Documents</h2>
                @forelse ($supplyRequest->documents as $doc)
                    <a href="{{ route('admin.documents.show', $doc) }}" class="block rounded-lg border border-slate-200 px-3 py-2 text-sm hover:bg-slate-50">
                        {{ $doc->type }} &mdash; {{ $doc->document_no }}
                    </a>
                @empty
                    <p class="text-sm text-slate-400">No documents generated yet.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
