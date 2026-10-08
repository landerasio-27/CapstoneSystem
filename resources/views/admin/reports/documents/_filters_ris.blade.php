{{--
    RIS filters: the month the report covers, plus three optional narrowings.
    The month is a whole calendar month — the first to the last day — so the
    report can never reach outside the period that was chosen.

    The numbers written by hand on other months' sheets ride along with the
    form: each belongs to the month it was written in, so it is carried
    untouched and read again when that month is the one being reported.
--}}
@foreach (($carriedNumbers ?? []) as $name => $number)
    <input type="hidden" name="{{ $name }}" value="{{ $number }}">
@endforeach

<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
    <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-slate-500">Month</span>
        <select name="month" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm">
            @foreach ($months as $number => $name)
                <option value="{{ $number }}" @selected((int) $form['month'] === $number)>{{ $name }}</option>
            @endforeach
        </select>
    </label>

    <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-slate-500">Year</span>
        <select name="year" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm">
            @foreach ($years as $year)
                <option value="{{ $year }}" @selected((int) $form['year'] === (int) $year)>{{ $year }}</option>
            @endforeach
        </select>
    </label>

    <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-slate-500">End-User <span class="font-normal text-slate-400">(optional)</span></span>
        <select name="end_user" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm">
            <option value="">All end-users</option>
            @foreach ($custodians as $custodian)
                <option value="{{ $custodian->id }}" @selected((string) $form['end_user'] === (string) $custodian->id)>{{ $custodian->full_name }}</option>
            @endforeach
        </select>
    </label>

    <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-slate-500">Stock No. <span class="font-normal text-slate-400">(optional)</span></span>
        <input type="text" name="stock_no" value="{{ $form['stock_no'] }}" placeholder="Item or property code"
               class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm">
    </label>

    <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-slate-500">Category <span class="font-normal text-slate-400">(optional)</span></span>
        <select name="category" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm">
            <option value="">All categories</option>
            @foreach ($categories as $category)
                <option value="{{ $category }}" @selected((string) $form['category'] === (string) $category)>{{ $category }}</option>
            @endforeach
        </select>
    </label>

    {{-- Everything the recapitulation prints, gathered behind the one control
         that names it: they all decide how that block reads, so the filter line
         does not carry three boxes of its own. The menu holds checkbox fields
         like any other, so they are sent with the form whether it is open or
         shut; the one that opens it and closes it again is the script at the
         foot of the generator. --}}
    <div class="relative self-end sm:col-span-2 lg:col-span-1" data-menu>
        <button type="button" data-menu-trigger aria-haspopup="true" aria-expanded="false"
                class="flex w-full items-center justify-between gap-2 rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm text-slate-600 transition hover:bg-slate-50">
            Recapitulation
            <x-icon name="chevron-down" class="h-4 w-4 shrink-0 text-slate-400" />
        </button>

        {{-- The panel is as wide as the longest of the words it holds rather
             than a width of its own: they are the control here, and a fixed box
             would cut them off. --}}
        <div data-menu-panel
             class="absolute left-0 z-30 mt-1 hidden w-max rounded-xl border border-slate-200 bg-white p-1 shadow-lg">
            {{-- The money columns belong to the accounting unit, so they stand
                 blank on the printed form until this is ticked. --}}
            <label class="flex items-center gap-2 whitespace-nowrap rounded-lg px-2 py-1.5 text-sm text-slate-600 transition hover:bg-slate-50">
                <input type="checkbox" name="show_costs" value="1" @checked($form['show_costs'])
                       class="h-4 w-4 rounded border-slate-300 accent-brand-800">
                Show unit cost &amp; amount
            </label>

            {{-- The stock number and quantity are the report's own record, and
                 the columns stand blank until the box is ticked, as the money
                 ones do. The hidden field beside it carries the "no" when it
                 is unticked, so the filter always answers the question rather
                 than leaving it to silence. --}}
            <label class="flex items-center gap-2 whitespace-nowrap rounded-lg px-2 py-1.5 text-sm text-slate-600 transition hover:bg-slate-50">
                <input type="hidden" name="show_stock" value="0">
                <input type="checkbox" name="show_stock" value="1" @checked($form['show_stock'])
                       class="h-4 w-4 rounded border-slate-300 accent-brand-800">
                Show stock no. &amp; quantity
            </label>

            {{-- The line that closes the block. It stands only where it is asked
                 for, like the figures above it, so the box arrives unticked and
                 the hidden field beside it carries the "no". --}}
            <label class="flex items-center gap-2 whitespace-nowrap rounded-lg px-2 py-1.5 text-sm text-slate-600 transition hover:bg-slate-50">
                <input type="hidden" name="show_total" value="0">
                <input type="checkbox" name="show_total" value="1" @checked($form['show_total'])
                       class="h-4 w-4 rounded border-slate-300 accent-brand-800">
                Show TOTAL row
            </label>
        </div>
    </div>
</div>

<p class="mt-3 text-xs text-slate-400">
    Covers the RIS documents dated inside the chosen month that the supply office has already approved or issued.
    What the recapitulation prints is set under <strong class="font-medium">Recapitulation</strong>: the UNIT COST and AMOUNT
    columns, the stock numbers and quantity the report holds, and the line that closes them are all left for the
    accounting unit unless they are asked for. The columns and their headings stand either way, ruled as the form
    rules them, and only the figures wait to be asked for.
    The number at the head of the sheet is written straight into the line the form draws for it: clear it and the report
    numbers itself again.
</p>
