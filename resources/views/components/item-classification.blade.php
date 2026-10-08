{{-- Which paper an item is recorded on. The header panel on the product forms
     and on the document form: it names the classification and why, from the
     single rule in App\Services\ItemClassifier, and its script keeps the answer
     live while the office is still filling the form in.

     `selectable` is the question itself. Equipment is always property, so its
     form asks nothing and the panel simply names the paper the recorded price
     will earn it; supply forms keep the Yes/No answer. --}}
@props(['consumable' => true, 'unitCost' => 0, 'heading' => 'Document Classification', 'scol' => false, 'selectable' => true])

@php
    $consumable = (bool) old('is_consumable', $consumable);
    $unitCost = (float) old('unit_cost', $unitCost);
    $types = \App\Services\ItemClassifier::labels();
    $current = \App\Services\ItemClassifier::for($consumable, $unitCost);
    $panel = $types[$current];
@endphp

<div {{ $attributes->merge(['class' => ($scol ? 'sm:col-span-2 ' : '') . 'rounded-lg border border-slate-200 bg-slate-50 p-4']) }}>
    @if ($selectable)
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-sm font-medium text-slate-700">Consumable?</p>
                <p class="mt-0.5 text-xs text-slate-400">
                    Consumables are used up, depleted or consumed in operations. Everything else is property.
                </p>
            </div>
            <div class="flex items-center gap-4">
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="radio" name="is_consumable" value="1" @checked($consumable) class="border-slate-300">
                    Yes
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="radio" name="is_consumable" value="0" @checked(! $consumable) class="border-slate-300">
                    No
                </label>
            </div>
        </div>
    @endif

    <div class="{{ $selectable ? 'mt-3 ' : '' }}rounded-lg bg-white px-3 py-2.5 ring-1 ring-slate-200"
         data-classification-panel
         data-consumable="{{ $consumable ? '1' : '0' }}"
         data-unit-cost="{{ $unitCost }}"
         data-labels="{{ json_encode($types) }}">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $heading }}</p>
        <div class="mt-1 flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $panel['tone'] }}"
                  data-classification-type>{{ $panel['type'] }}</span>
            <span class="text-sm font-medium text-slate-700" data-classification-label>{{ $panel['label'] }}</span>
            <span class="text-xs text-slate-400" data-classification-summary>{{ $panel['summary'] }}</span>
        </div>
        <p class="mt-1 text-xs text-slate-400" data-classification-reason>{{ $panel['reason'] }}</p>
        <p class="mt-1 text-xs text-slate-400" data-classification-cost>
            @if ($unitCost > 0)
                Based on a unit cost of ₱{{ number_format($unitCost, 2) }}.
            @else
                A new item starts without a unit cost: record its stock (Add Stock / Add Quantity) and the classification follows that price.
            @endif
        </p>
    </div>
</div>

{{-- The live answer only exists where the question is asked: with no radio on
     the page the panel already states the one paper its always-property item
     can be written on. --}}
@if ($selectable)
<script>
    // Live classification: the threshold and every label come from
    // ItemClassifier, so the panel can never disagree with the server.
    (function () {
        var THRESHOLD = {{ (float) \App\Services\ItemClassifier::PROPERTY_THRESHOLD }};

        function refresh(panel) {
            if (!panel) return;

            var labels = JSON.parse(panel.dataset.labels || '{}');
            // Without the question on the page, the panel's own answer stands.
            var checked = document.querySelector('input[name="is_consumable"]:checked');
            var consumable = checked ? checked.value : (panel.dataset.consumable || '0');
            var cost = parseFloat(panel.dataset.unitCost || '0') || 0;
            var type = consumable === '0' ? (cost > THRESHOLD ? 'PAR' : 'ICS') : 'RIS';
            var meta = labels[type] || {};

            var pill = panel.querySelector('[data-classification-type]');
            if (pill) {
                pill.textContent = meta.type || type;
                pill.className = 'inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ' + (meta.tone || '');
            }
            panel.querySelector('[data-classification-label]').textContent = meta.label || '';
            panel.querySelector('[data-classification-summary]').textContent = meta.summary || '';
            panel.querySelector('[data-classification-reason]').textContent = meta.reason || '';
        }

        document.querySelectorAll('[data-classification-panel]').forEach(function (panel) {
            document.querySelectorAll('input[name="is_consumable"]').forEach(function (radio) {
                radio.addEventListener('change', function () { refresh(panel); });
            });
            refresh(panel);
        });
    })();
</script>
@endif
