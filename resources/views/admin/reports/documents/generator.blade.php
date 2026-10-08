@extends('layouts.admin')

@section('title', $typeLabel.' — '.$documentTitle)

@section('content')
    @include('admin.reports.documents._styles')

    {{-- Only the reports opened from the PAR / ICS / RIS picker link back to
         it; the Monthly Report is reached from the sidebar like any other
         report and so carries no way back into that list. --}}
    @if ($backUrl)
        <div class="doc-nonprint mb-4">
            <a href="{{ $backUrl }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 transition hover:text-slate-700">
                <x-icon name="arrow-left" class="h-4 w-4" />
                {{ $backLabel }}
            </a>
        </div>
    @endif

    {{--
        The filters and the document are one form: every button sends the same
        fields, so the preview, the printed sheet and the PDF are always the
        report currently on screen.
    --}}
    <form id="report-form" method="GET"
          action="{{ $formAction }}"
          data-print-url="{{ $printUrl }}"
          class="doc-nonprint mb-5 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        {{-- The marker every button in this form sends: it is what says the
             report has been generated. The sheet's number is the report's own
             to give, so a page that has only been opened leaves the line blank
             and generating the report fills the number in. --}}
        <input type="hidden" name="generated" value="1">

        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="font-semibold text-slate-800">{{ $documentTitle }}</h2>
                <p class="text-xs text-slate-400">{{ $subtitle ?? $typeLabel.' · built from the records on file' }}</p>
            </div>
        </div>

        @include("admin.reports.documents._filters_{$type}")

        <div class="mt-5 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
            <button type="submit"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-900">
                <x-icon name="chart" class="h-4 w-4" />
                Generate Report
            </button>
            <button type="submit" formaction="{{ $printUrl }}" formtarget="_blank"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 transition hover:bg-slate-50">
                <x-icon name="file" class="h-4 w-4" />
                Preview
            </button>
            <button type="button" onclick="isuPrintDocument()"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 transition hover:bg-slate-50">
                <x-icon name="printer" class="h-4 w-4" />
                Print
            </button>
            <button type="submit" formaction="{{ $pdfUrl }}"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 transition hover:bg-slate-50">
                <x-icon name="download" class="h-4 w-4" />
                Export PDF
            </button>
        </div>
    </form>

    <p class="doc-nonprint mb-3 text-sm text-slate-500">{!! $summary !!}</p>

    {{-- The document itself, laid out exactly as it prints — and on the two
         reports the office numbers itself, the number sits in the header as a
         field written in place rather than set from the filter line: one on
         the RIS, which is a single sheet, and one per sheet on the Monthly
         Report, which is a run of them. --}}
    <div class="doc-inline">
        {{-- Only the RIS is written under a number the office types: the monthly
             report numbers its own pages, so it has nothing to write into. --}}
        @include('admin.reports.documents._document', ['editable' => $type === 'ris'])
    </div>

    <script>
        // Open the printed sheet in its own tab, asking it to bring up the
        // browser's print dialog straight away.
        function isuPrintDocument() {
            const form = document.getElementById('report-form');
            if (!form) return;

            const params = new URLSearchParams();
            new FormData(form).forEach(function (value, key) { params.append(key, value); });
            params.set('print', '1');

            window.open(form.dataset.printUrl + '?' + params.toString(), '_blank');
        }

        // The report number stands in the rule the sheet draws for it, so it is
        // written straight into that rule rather than a box of the browser's
        // own: the field is the width of the rule until what is written
        // outgrows it, and widens with it from there.
        document.querySelectorAll('[data-no-entry]').forEach(function (field) {
            field.addEventListener('input', function () {
                field.style.width = '';

                if (field.scrollWidth > field.clientWidth) {
                    field.style.width = field.scrollWidth + 'px';
                }
            });
        });

        // The filter menus: one control that names the choices it holds, opened
        // by a click and closed by a click away or Escape. The checkbox fields
        // inside are part of the form whether it is open or shut, so what they
        // say reaches the preview, the sheet and the PDF either way.
        document.querySelectorAll('[data-menu]').forEach(function (menu) {
            const trigger = menu.querySelector('[data-menu-trigger]');
            const panel = menu.querySelector('[data-menu-panel]');
            if (!trigger || !panel) return;

            function close() {
                panel.classList.add('hidden');
                trigger.setAttribute('aria-expanded', 'false');
            }

            trigger.addEventListener('click', function (e) {
                e.stopPropagation();
                const willOpen = panel.classList.contains('hidden');
                panel.classList.toggle('hidden', !willOpen);
                trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            });

            document.addEventListener('click', function (e) {
                if (!menu.contains(e.target)) close();
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') close();
            });
        });

        // The equipment list: a search box and select all / clear, so a long
        // inventory is still workable.
        document.querySelectorAll('[data-picker]').forEach(function (picker) {
            const rows = Array.from(picker.querySelectorAll('[data-picker-row]'));
            const search = picker.querySelector('[data-picker-search]');

            if (search) {
                search.addEventListener('input', function () {
                    const term = search.value.trim().toLowerCase();
                    rows.forEach(function (row) {
                        row.hidden = term !== '' && !row.dataset.pickerText.includes(term);
                    });
                });
            }

            const all = picker.querySelector('[data-picker-all]');
            if (all) {
                all.addEventListener('click', function () {
                    rows.filter(function (row) { return !row.hidden; }).forEach(function (row) {
                        const box = row.querySelector('[data-picker-checkbox]');
                        if (box) box.checked = true;
                    });
                });
            }

            const none = picker.querySelector('[data-picker-none]');
            if (none) {
                none.addEventListener('click', function () {
                    picker.querySelectorAll('[data-picker-checkbox]').forEach(function (box) { box.checked = false; });
                });
            }
        });
    </script>
@endsection
