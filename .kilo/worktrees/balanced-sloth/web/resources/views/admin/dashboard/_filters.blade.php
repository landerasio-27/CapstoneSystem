{{--
    One GET form drives every analytics section below it. There is nothing to
    apply and nothing to reset: every field submits the moment it changes, and
    each one opens on the analytics the dashboard shows by default, so choosing
    those again is the way back to them.
--}}
<form method="GET" action="{{ route('admin.dashboard') }}"
      class="mt-4 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
    <div class="flex flex-wrap items-end gap-3">
        <label class="block">
            <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-400">Analytics type</span>
            <select name="type" onchange="this.form.elements['item'].value = ''; this.form.submit();"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                <option value="supplies" @selected($filters['type'] === 'supplies')>Supplies</option>
                <option value="equipment" @selected($filters['type'] === 'equipment')>Equipment</option>
            </select>
        </label>

        <label class="block min-w-56 flex-1">
            <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                {{ $filters['type'] === 'equipment' ? 'Equipment item' : 'Supply item' }}
            </span>
            <select name="item" onchange="this.form.submit();"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                @forelse ($itemOptions as $option)
                    <option value="{{ $option['id'] }}" @selected((int) $filters['item'] === (int) $option['id'])>{{ $option['label'] }}</option>
                @empty
                    <option value="">No items recorded yet</option>
                @endforelse
            </select>
        </label>

        <label class="block">
            <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-400">Period</span>
            <select name="period" onchange="this.form.submit();"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                {{-- Compared as text on both sides: the period keys are written as
                     numbers, so PHP holds them as integers, while the chosen
                     period is a string — a strict comparison of the two would
                     never match, and the picker would always sit on its first
                     option whatever the analytics were showing. --}}
                @foreach (\App\Services\InventoryAnalyticsService::PERIODS as $key => $label)
                    <option value="{{ $key }}" @selected((string) $filters['period'] === (string) $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        @if ($filters['period'] === 'year')
            <label class="block">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-400">Year</span>
                <select name="year" onchange="this.form.submit();"
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    @foreach ($filters['years'] as $year)
                        <option value="{{ $year }}" @selected($filters['year'] === $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </label>
        @endif

        @if ($filters['period'] === 'custom')
            <label class="block">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-400">From month</span>
                <input type="month" name="from" value="{{ $filters['from_input'] }}"
                       class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            </label>
            <label class="block">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-400">To month</span>
                <input type="month" name="to" value="{{ $filters['to_input'] }}"
                       class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            </label>
        @endif

    </div>

    <p class="mt-2 text-xs text-slate-400">
        Showing <span class="font-medium text-slate-600">{{ $filters['type_label'] }}</span>
        for <span class="font-medium text-slate-600">{{ $filters['period_label'] }}</span>
        ({{ $filters['range_label'] }}). Changing a filter reloads the analytics from the database.
    </p>
</form>
