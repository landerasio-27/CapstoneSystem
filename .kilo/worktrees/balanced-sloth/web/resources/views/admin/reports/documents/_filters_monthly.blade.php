{{--
    Monthly Report filters: the month the report covers, the end-user and stock
    number to narrow it by, and which documents it is cut from — the RIS
    supplies issued, or the ICS property handed out.

    They stand in one line, the way the office reads them off the form, and the
    note under them tells the reader what a sheet of the report will hold. On a
    narrow window the line wraps rather than squeezing the fields.

    The numbers written by hand on other months' sheets ride along with the
    form: each belongs to the month it was written in, so it is carried
    untouched and read again when that month is the one being reported.
--}}
@foreach (($carriedNumbers ?? []) as $name => $number)
    <input type="hidden" name="{{ $name }}" value="{{ $number }}">
@endforeach

<div class="flex flex-wrap items-end gap-3">
    <label class="flex min-w-[8rem] flex-1 flex-col gap-1">
        <span class="text-xs font-medium text-slate-500">Month</span>
        <select name="month" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm">
            @foreach ($months as $number => $name)
                <option value="{{ $number }}" @selected((int) $form['month'] === $number)>{{ $name }}</option>
            @endforeach
        </select>
    </label>

    <label class="flex min-w-[6rem] flex-1 flex-col gap-1">
        <span class="text-xs font-medium text-slate-500">Year</span>
        <select name="year" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm">
            @foreach ($years as $year)
                <option value="{{ $year }}" @selected((int) $form['year'] === (int) $year)>{{ $year }}</option>
            @endforeach
        </select>
    </label>

    <label class="flex min-w-[10rem] flex-1 flex-col gap-1">
        <span class="text-xs font-medium text-slate-500">End-User <span class="font-normal text-slate-400">(optional)</span></span>
        <select name="end_user" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm">
            <option value="">Every end-user</option>
            @foreach ($custodians as $custodian)
                <option value="{{ $custodian->id }}" @selected((string) $form['end_user'] === (string) $custodian->id)>{{ $custodian->full_name }}</option>
            @endforeach
        </select>
    </label>

    <label class="flex min-w-[10rem] flex-1 flex-col gap-1">
        <span class="text-xs font-medium text-slate-500">Stock No. <span class="font-normal text-slate-400">(optional)</span></span>
        <input type="text" name="stock_no" value="{{ $form['stock_no'] }}" placeholder="Item or property code"
               class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm">
    </label>

    <label class="flex min-w-[7rem] flex-1 flex-col gap-1">
        <span class="text-xs font-medium text-slate-500">Category</span>
        <select name="document_type" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm">
            <option value="all" @selected($form['document_type'] === 'all')>All</option>
            <option value="ics" @selected($form['document_type'] === 'ics')>ICS</option>
            <option value="ris" @selected($form['document_type'] === 'ris')>RIS</option>
        </select>
    </label>

    {{-- Everything each sheet's recapitulation prints, gathered behind the one
         control that names it: the filter line stays one line of filters, and
         the menu's checkbox fields are sent with the form whether it is open or
         shut. Its own width is kept, so the words never wrap. --}}
    <div class="relative flex-none self-end" data-menu>
        <button type="button" data-menu-trigger aria-haspopup="true" aria-expanded="false"
                class="flex items-center gap-2 whitespace-nowrap rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm text-slate-600 transition hover:bg-slate-50">
            Recapitulation
            <x-icon name="chevron-down" class="h-4 w-4 shrink-0 text-slate-400" />
        </button>

        {{-- The panel is as wide as the longest of the words it holds rather
             than a width of its own: they are the control here, and a fixed box
             would cut them off. It hangs from the control's right edge, since
             this is the last control on the line and a panel opening from its
             left would run off the window. --}}
        <div data-menu-panel
             class="absolute right-0 z-30 mt-1 hidden w-max rounded-xl border border-slate-200 bg-white p-1 shadow-lg">
            {{-- The money columns belong to the accounting unit, so they stand
                 blank on the printed form until this is ticked. --}}
            <label class="flex items-center gap-2 whitespace-nowrap rounded-lg px-2 py-1.5 text-sm text-slate-600 transition hover:bg-slate-50">
                <input type="checkbox" name="show_costs" value="1" @checked($form['show_costs'])
                       class="h-4 w-4 rounded border-slate-300 accent-brand-800">
                Show unit cost &amp; amount
            </label>

            {{-- The stock number and quantity are the report's own record, and
                 its columns stand blank until the box is ticked, as the money
                 ones do; the hidden field beside it carries the "no" when it
                 is unticked, since an unticked box sends nothing at all. --}}
            <label class="flex items-center gap-2 whitespace-nowrap rounded-lg px-2 py-1.5 text-sm text-slate-600 transition hover:bg-slate-50">
                <input type="hidden" name="show_stock" value="0">
                <input type="checkbox" name="show_stock" value="1" @checked($form['show_stock'])
                       class="h-4 w-4 rounded border-slate-300 accent-brand-800">
                Show stock no. &amp; quantity
            </label>

            {{-- The line that closes each block, unticked by default like the
                 figures above it, with the hidden field carrying the "no". --}}
            <label class="flex items-center gap-2 whitespace-nowrap rounded-lg px-2 py-1.5 text-sm text-slate-600 transition hover:bg-slate-50">
                <input type="hidden" name="show_total" value="0">
                <input type="checkbox" name="show_total" value="1" @checked($form['show_total'])
                       class="h-4 w-4 rounded border-slate-300 accent-brand-800">
                Show TOTAL row
            </label>
        </div>
    </div>

    <p class="w-full text-xs text-slate-400">
        One running report per paper, cut from the month's <strong class="font-medium">RIS</strong> (supplies issued) or
        <strong class="font-medium">ICS</strong> (property issued) documents — or <strong class="font-medium">All</strong>
        for both, each report behind a page naming it — a divider, which carries no number and takes none from the
        run. Every paper asked for is printed even when the month holds none of it, as the blank sheet it is on the
        form.        The end-users' lines follow one another down the form and carry on over onto another page when it
        fills, with the recapitulation and certification closing the whole report. Every page printed is numbered
        as the month's run is printed — one number a page, the first page number one — and nothing on the sheet is
        typed. What the recapitulation prints is set under <strong class="font-medium">Recapitulation</strong>: the
        money columns, the stock numbers and quantity, and the line that closes them are all left for the
        accounting unit unless they are asked for.
    </p>
</div>
