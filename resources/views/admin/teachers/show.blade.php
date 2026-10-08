@extends('layouts.admin')

@section('title', $teacher->full_name.' — Custodian Folder')

@section('content')
    @php
        // Breadcrumb "Custodian Folders / <name>" comes from the layout, which
        // builds it from the route (see App\Support\Navigation::breadcrumbs).
        $documentTypes = \App\Http\Controllers\Admin\TeacherController::DOCUMENT_TYPES;
        $typeTones = [
            'PAR' => 'bg-brand-50 text-brand-700',
            'ICS' => 'bg-sky-50 text-sky-700',
            'RIS' => 'bg-indigo-50 text-indigo-600',
            'IAR' => 'bg-slate-100 text-slate-600',
        ];
        $statusTones = [
            'draft' => 'bg-slate-100 text-slate-600',
            'approved' => 'bg-sky-50 text-sky-700',
            'issued' => 'bg-brand-50 text-brand-700',
        ];
        $equipmentTones = [
            'available' => 'bg-sky-50 text-sky-700',
            'issued' => 'bg-brand-50 text-brand-700',
            'damaged' => 'bg-red-50 text-red-600',
            'missing' => 'bg-red-50 text-red-600',
            'misplaced' => 'bg-amber-50 text-amber-700',
        ];
        $isEmpty = $summary['documents'] === 0 && $summary['equipment'] === 0;

        // No type is asked for: the items on the new paper decide whether this
        // custodian is handed property (PAR / ICS) or supplies (RIS), and every
        // paper the transaction produces is filed into this folder. Narrowing the
        // form to PAR here would hide the ICS and RIS items from the picker.
        $addDocumentUrl = route('admin.documents.create', ['teacher_id' => $teacher->id]);

        // A document filter keeps the search, and the search keeps the filter.
        $documentFilterUrl = fn (string $value) => route('admin.teachers.show', array_filter([
            'teacher' => $teacher->id,
            'documents' => $value === 'all' ? null : $value,
            'doc_q' => $documentSearch ?: null,
        ]));

        // The two tabs of the folder's contents. Both addresses carry the
        // document filter, search and "show all" the office is working with, so
        // stepping over to the property and back returns the same list.
        $panelUrl = fn (string $value) => route('admin.teachers.show', array_filter([
            'teacher' => $teacher->id,
            'panel' => $value === 'equipment' ? 'equipment' : null,
            'documents' => $documentType ?: null,
            'doc_q' => $documentSearch ?: null,
            'doc_all' => $showAllDocuments ? 1 : null,
        ]));

        // The same address, with (or without) the whole list asked for.
        $documentListUrl = fn (bool $all) => route('admin.teachers.show', array_filter([
            'teacher' => $teacher->id,
            'documents' => $documentType ?: null,
            'doc_q' => $documentSearch ?: null,
            'doc_all' => $all ? 1 : null,
        ]));

        $documentNoun = $documentType !== ''
            ? $documentType.' documents'
            : ($documentSearch !== '' ? 'matching documents' : 'documents');

        $tiles = [
            ['label' => 'Documents', 'value' => $summary['documents'], 'icon' => 'file', 'tone' => 'brand'],
            ['label' => 'Equipment', 'value' => $summary['equipment'], 'icon' => 'box', 'tone' => 'sky'],
            ['label' => 'PAR', 'value' => $summary['PAR'], 'icon' => 'clipboard', 'tone' => 'brand'],
            ['label' => 'ICS', 'value' => $summary['ICS'], 'icon' => 'clipboard', 'tone' => 'brand'],
            ['label' => 'RIS', 'value' => $summary['RIS'], 'icon' => 'clipboard', 'tone' => 'brand'],
        ];
        $tileTones = [
            'brand' => 'bg-brand-50 text-brand-700',
            'sky' => 'bg-sky-50 text-sky-700',
        ];
    @endphp

    {{-- Folder header: whose folder this is. What is added to the folder is
         offered on the tab that holds it, so the header keeps only the action
         that belongs to the record itself. --}}
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-start gap-4">
                {{-- The folder this page is --}}
                <x-folder-icon class="h-14 w-16 shrink-0" />
                <div class="min-w-0">
                    <h2 class="truncate text-xl font-semibold text-slate-800">{{ $teacher->full_name }}</h2>
                    <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-500">
                        <span>{{ $teacher->department ?? 'No department recorded' }}</span>
                        @unless ($teacher->is_active)
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-500">Inactive</span>
                        @endunless
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="button" onclick="ISUModal.open('edit-information-modal')"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    <x-icon name="pencil" class="h-4 w-4" /> Edit Information
                </button>
            </div>
        </div>
    </div>

    {{-- What is in the folder, counted from the records that name this custodian. --}}
    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5">
        @foreach ($tiles as $tile)
            <div class="flex items-center gap-3 rounded-xl border border-slate-100 bg-white px-3 py-2.5 shadow-sm">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $tileTones[$tile['tone']] }}">
                    <x-icon :name="$tile['icon']" class="h-4 w-4" />
                </span>
                <span class="min-w-0">
                    <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $tile['label'] }}</span>
                    <span class="block text-lg font-semibold leading-tight text-slate-900">{{ $tile['value'] }}</span>
                </span>
            </div>
        @endforeach
    </div>

    <div class="mt-6">
            @if ($isEmpty)
                {{-- Empty folder: the way to fill it, instead of two empty tables. --}}
                <section class="rounded-xl bg-white p-10 text-center shadow-sm ring-1 ring-slate-200">
                    <x-folder-icon class="mx-auto h-16 w-20" />
                    <p class="mt-4 font-medium text-slate-700">This folder is empty</p>
                    <p class="mx-auto mt-1 max-w-md text-sm text-slate-500">
                        No documents or equipment have been assigned to this custodian yet.
                        Anything filed under {{ $teacher->full_name }} anywhere in the system appears here on its own.
                    </p>
                    <div class="mt-5 flex flex-wrap items-center justify-center gap-3">
                        <a href="{{ $addDocumentUrl }}"
                           class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-900">
                            <x-icon name="plus" class="h-4 w-4" /> Add Document
                        </a>
                        <button type="button" onclick="ISUModal.open('assign-equipment-modal')"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                            <x-icon name="box" class="h-4 w-4" /> Assign Equipment
                        </button>
                    </div>
                </section>
            @else
                {{-- What the folder holds, read one part at a time: the papers it
                     filed, and the property it answers for. The tab carries its
                     own count, so neither side is out of sight. --}}
                <section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
                    <div class="flex flex-col gap-3 border-b border-slate-200 px-5 pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <nav class="-mb-px flex gap-6" aria-label="Folder contents">
                            <a href="{{ $panelUrl('documents') }}" @if ($panel === 'documents') aria-current="page" @endif
                               class="inline-flex items-center gap-2 border-b-2 px-1 pb-3 text-sm font-medium transition {{ $panel === 'documents' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' }}">
                                <x-icon name="file" class="h-4 w-4" /> Documents
                                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $panel === 'documents' ? 'bg-brand-50 text-brand-700' : 'bg-slate-100 text-slate-500' }}">{{ $summary['documents'] }}</span>
                            </a>
                            <a href="{{ $panelUrl('equipment') }}" @if ($panel === 'equipment') aria-current="page" @endif
                               class="inline-flex items-center gap-2 border-b-2 px-1 pb-3 text-sm font-medium transition {{ $panel === 'equipment' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' }}">
                                <x-icon name="box" class="h-4 w-4" /> Assigned Equipment
                                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $panel === 'equipment' ? 'bg-brand-50 text-brand-700' : 'bg-slate-100 text-slate-500' }}">{{ $summary['equipment'] }}</span>
                            </a>
                        </nav>

                        <div class="pb-3">
                            @if ($panel === 'documents')
                                <a href="{{ $addDocumentUrl }}"
                                   class="inline-flex w-fit items-center gap-1.5 rounded-lg bg-brand-800 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-brand-900">
                                    <x-icon name="plus" class="h-3.5 w-3.5" /> Add Document
                                </a>
                            @else
                                <button type="button" onclick="ISUModal.open('assign-equipment-modal')"
                                        class="inline-flex w-fit items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                                    <x-icon name="box" class="h-3.5 w-3.5" /> Assign Equipment
                                </button>
                            @endif
                        </div>
                    </div>

                    @if ($panel === 'documents')
                    <p class="border-b border-slate-100 px-5 py-2 text-xs text-slate-500">
                        PAR, ICS and RIS papers filed under this custodian.
                    </p>

                    <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-3 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ $documentFilterUrl('all') }}"
                               class="rounded-full px-3 py-1 text-xs font-medium transition {{ $documentType === '' ? 'bg-brand-800 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100' }}">All</a>
                            @foreach (['PAR', 'ICS', 'RIS'] as $type)
                                <a href="{{ $documentFilterUrl($type) }}"
                                   class="rounded-full px-3 py-1 text-xs font-medium transition {{ $documentType === $type ? 'bg-brand-800 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100' }}">{{ $type }}</a>
                            @endforeach
                        </div>

                        <form method="GET" data-auto-filter class="flex gap-2">
                            <input type="hidden" name="documents" value="{{ $documentType }}">
                            <input type="text" name="doc_q" value="{{ $documentSearch }}" placeholder="Search documents..."
                                   class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 sm:w-56">
                            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-600 transition hover:bg-slate-50">
                                <x-icon name="search" class="h-4 w-4" />
                            </button>
                        </form>
                    </div>

                    <ul class="divide-y divide-slate-100">
                        @forelse ($documents as $document)
                            <li class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex items-start gap-3">
                                    <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $typeTones[$document->type] ?? 'bg-slate-100 text-slate-600' }}">
                                        <x-icon name="file" class="h-4 w-4" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-slate-800">
                                            {{ $document->type }}
                                            <span class="font-normal text-slate-500">{{ $documentTypes[$document->type] ?? '' }}</span>
                                        </p>
                                        <p class="mt-0.5 font-mono text-xs text-slate-600">{{ $document->document_no }}</p>
                                        <p class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-400">
                                            <span>{{ $document->document_date?->format('F j, Y') ?? '—' }}</span>
                                            <span class="rounded-full px-2 py-0.5 font-medium {{ $statusTones[$document->status] ?? 'bg-slate-100 text-slate-600' }}">
                                                {{ ucfirst($document->status) }}
                                            </span>
                                        </p>
                                    </div>
                                </div>

                                <div class="flex shrink-0 items-center gap-2">
                                    <button type="button" data-document-preview
                                            data-preview-url="{{ in_array($document->type, ['PAR', 'ICS', 'RIS'], true) ? route('admin.documents.bundle-pdf', $document) : route('admin.documents.pdf', $document) }}"
                                            data-document-title="{{ $document->type }} — {{ $document->document_no }}"
                                            data-details-url="{{ route('admin.documents.show', $document) }}"
                                            aria-haspopup="dialog" aria-controls="document-preview-modal"
                                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-50">View</button>
                                    {{-- One click saves the paper as a file, together with the
                                         acceptance report it is filed with: a PAR/ICS/RIS
                                         downloads as a two-page bundle (page 1 = the paper,
                                         page 2 = its IAR). An IAR is already the second half
                                         of that pair, so it downloads on its own. The
                                         `download=1` is what makes the browser save it
                                         instead of opening a reader tab. --}}
                                    @php
                                        $pairsWithIar = in_array($document->type, ['PAR', 'ICS', 'RIS'], true);
                                        $downloadUrl = $pairsWithIar
                                            ? route('admin.documents.bundle-pdf', ['document' => $document, 'download' => 1])
                                            : route('admin.documents.pdf', ['document' => $document, 'download' => 1]);
                                    @endphp
                                    <a href="{{ $downloadUrl }}"
                                       title="{{ $pairsWithIar ? "Download this {$document->type} together with its IAR" : "Download this IAR" }}"
                                       class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-50">
                                        <x-icon name="download" class="h-3.5 w-3.5" /> Download
                                    </a>
                                </div>
                            </li>
                        @empty
                            <li class="px-5 py-10 text-center text-sm text-slate-400">
                                @if ($documentType !== '' || $documentSearch !== '')
                                    No documents match this filter.
                                @else
                                    No documents filed under this custodian yet.
                                @endif
                            </li>
                        @endforelse
                    </ul>

                    {{-- The folder lists the newest papers; a custodian can hold
                         dozens, so the rest are one link away rather than all of
                         them always drawn. --}}
                    @if ($documentsMatching > $documents->count() || ($showAllDocuments && $documentsMatching > $documentPreview))
                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 bg-slate-50 px-5 py-3 text-xs text-slate-500">
                            <span>
                                @if ($documentsMatching > $documents->count())
                                    Showing the first {{ $documents->count() }} of {{ $documentsMatching }} {{ $documentNoun }}.
                                @else
                                    Showing all {{ $documentsMatching }} {{ $documentNoun }}.
                                @endif
                            </span>

                            @if ($documentsMatching > $documents->count())
                                <a href="{{ $documentListUrl(true) }}" class="font-medium text-brand-700 hover:underline">
                                    Show all {{ $documentsMatching }}
                                </a>
                            @else
                                <a href="{{ $documentListUrl(false) }}" class="font-medium text-brand-700 hover:underline">
                                    Show first {{ $documentPreview }}
                                </a>
                            @endif
                        </div>
                    @endif
                    @else
                    {{-- Assigned Equipment: read off the equipment's own custodian. --}}
                    <p class="border-b border-slate-100 px-5 py-2 text-xs text-slate-500">
                        Property assigned to this custodian, straight from the equipment register.
                    </p>

                    {{-- The panel sits in the folder's left column, so the columns keep
                         their own widths and the table scrolls sideways rather than
                         wrapping an item's name over three lines. --}}
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr class="whitespace-nowrap">
                                    <th class="px-3 py-2.5 text-left font-medium text-slate-500">Equipment</th>
                                    <th class="px-3 py-2.5 text-left font-medium text-slate-500">Code</th>
                                    <th class="px-3 py-2.5 text-left font-medium text-slate-500">Serial</th>
                                    <th class="px-3 py-2.5 text-left font-medium text-slate-500">Room</th>
                                    <th class="px-3 py-2.5 text-left font-medium text-slate-500">Assigned</th>
                                    <th class="px-3 py-2.5 text-left font-medium text-slate-500">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($equipment as $item)
                                    {{-- The row itself opens the equipment record, the way it
                                         does in the register: the name reads as the plain
                                         text it is instead of being the one link in the
                                         row. --}}
                                    <tr data-href="{{ route('admin.equipment.show', $item) }}"
                                        class="cursor-pointer whitespace-nowrap transition hover:bg-slate-50">
                                        {{-- Category rides under the name, so the status of the item
                                             stays on the panel instead of past its right edge. --}}
                                        <td class="px-3 py-3">
                                            <span class="flex items-center gap-2">
                                                <span class="font-medium text-slate-800">{{ $item->name }}</span>
                                                {{-- How the item is accountable: property above the
                                                     threshold on a PAR, at or below it on an ICS. --}}
                                                <x-classification-badge :type="$item->classification()" />
                                            </span>
                                            <span class="mt-0.5 block text-xs text-slate-400">{{ $item->category ?? 'No category' }}</span>
                                        </td>
                                        <td class="px-3 py-3 font-mono text-xs text-slate-500">{{ $item->item_code }}</td>
                                        <td class="px-3 py-3 font-mono text-xs text-slate-500">{{ $item->serial_number ?: '—' }}</td>
                                        {{-- Where the item is, beside who holds it: the same
                                             record read from the room's folder too. --}}
                                        <td class="px-3 py-3 text-slate-500">
                                            @if ($item->room)
                                                <a href="{{ route('admin.rooms.show', $item->room) }}" class="hover:text-brand-800">{{ $item->room->code }}</a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 text-slate-500">{{ $item->assignedAt()?->format('M j, Y') ?? '—' }}</td>
                                        <td class="px-3 py-3">
                                            <span class="rounded-full px-2 py-1 text-xs font-medium {{ $equipmentTones[$item->status] ?? 'bg-slate-100 text-slate-600' }}">
                                                {{ ucfirst($item->status) }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-3 py-10 text-center text-sm text-slate-400">
                                            No equipment has been assigned to this custodian yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @endif
                </section>
            @endif
    </div>

    <div id="document-preview-modal" data-modal role="dialog" aria-modal="true" aria-labelledby="document-preview-title"
         class="fixed inset-0 z-50 hidden items-center justify-center p-2 sm:p-4">
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" data-close-document-preview></div>
        <div class="relative flex h-[90vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-xl">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-4">
                <h3 id="document-preview-title" class="min-w-0 break-words text-base font-semibold text-slate-800">Document Preview</h3>
                <div class="flex items-center gap-2">
                    <a id="document-preview-details" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">Full Details</a>
                    <a id="document-preview-download" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">Download</a>
                    <button type="button" data-close-document-preview data-modal-focus aria-label="Close document preview"
                            class="rounded-lg p-2 text-slate-500 hover:bg-slate-100">
                        <x-icon name="x-mark" class="h-5 w-5" />
                    </button>
                </div>
            </div>
            <p class="border-b border-slate-100 px-4 py-2 text-xs text-slate-500">PAR, ICS and RIS previews include their IAR. If the preview does not display, use Download or Full Details.</p>
            <iframe id="document-preview-frame" title="Document preview" class="min-h-0 flex-1 border-0 bg-slate-100"></iframe>
        </div>
    </div>

    @include('admin.teachers._assign_equipment_modal')
    @include('admin.teachers._edit_information_modal')

    {{-- A click anywhere on an assigned item opens its equipment record. A click
         that lands on a link or a control inside the row is left alone: those
         carry their own destination, and a reader dragging across a row to copy
         a code gets the text rather than a page change. --}}
    <script>
        (() => {
            const modal = document.getElementById('document-preview-modal');
            const frame = document.getElementById('document-preview-frame');
            let trigger = null;

            function closePreview() {
                ISUModal.close(modal.id);
                frame.removeAttribute('src');
                trigger?.focus();
            }

            document.querySelectorAll('[data-document-preview]').forEach(button => {
                button.addEventListener('click', () => {
                    trigger = button;
                    document.getElementById('document-preview-title').textContent = button.dataset.documentTitle;
                    document.getElementById('document-preview-details').href = button.dataset.detailsUrl;
                    const download = new URL(button.dataset.previewUrl, window.location.origin);
                    download.searchParams.set('download', '1');
                    document.getElementById('document-preview-download').href = download.href;
                    frame.title = button.dataset.documentTitle + ' document';
                    frame.src = button.dataset.previewUrl + '#toolbar=0';
                    ISUModal.open(modal.id);
                });
            });
            modal.querySelectorAll('[data-close-document-preview]').forEach(button => {
                button.addEventListener('click', closePreview);
            });
            document.addEventListener('keydown', event => {
                if (modal.classList.contains('hidden')) return;
                if (event.key === 'Escape') {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    closePreview();
                } else if (event.key === 'Tab') {
                    const controls = [...modal.querySelectorAll('a[href], button, iframe')];
                    const first = controls[0];
                    const last = controls[controls.length - 1];
                    if (event.shiftKey && document.activeElement === first) {
                        event.preventDefault();
                        last.focus();
                    } else if (!event.shiftKey && document.activeElement === last) {
                        event.preventDefault();
                        first.focus();
                    }
                }
            }, true);
        })();

        document.querySelectorAll('[data-href]').forEach(function (row) {
            row.addEventListener('click', function (e) {
                if (e.target.closest('a, button, input, select, label')) return;
                if (String(window.getSelection()).trim() !== '') return;

                window.location.href = row.dataset.href;
            });
        });
    </script>
@endsection
