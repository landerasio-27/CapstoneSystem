{{-- The paper an item is recorded on (PAR / ICS / RIS), as decided by the one
     classification rule in App\Services\ItemClassifier. Subtle tones keep the
     ISU green as the accent rather than three loud colours.

     The tooltip names the paper in full and says why the item landed on it —
     the caller's own reason when one is given (a record whose price decided
     between a PAR and an ICS), the paper's short summary otherwise. --}}
@props(['type', 'detail' => false, 'reason' => null])

@php
    $type = strtoupper((string) $type);
    $label = \App\Services\ItemClassifier::label($type);
    $summary = \App\Services\ItemClassifier::summaryLabel($type);
    $explanation = $reason ?: $summary;
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ' . \App\Services\ItemClassifier::tone($type)]) }}
      title="{{ $explanation ? $label . ' — ' . $explanation : $label }}">
    {{ $type }}
    @if ($detail && $summary)
        <span class="font-normal opacity-70">{{ $summary }}</span>
    @endif
</span>
