{{--
    One signature column: a blank space to sign in, then the printed name, the
    position and the date, each on its own rule. Shared by the PAR and the ICS,
    which print the same "Received from / Received by" pair side by side.

    Expects: $label, $name, $position, $dateLabel (all optional except $label).
--}}
@php
    $name = $name ?? '';
    $position = $position ?? '';
    $dateLabel = $dateLabel ?? null;
@endphp

<div class="doc-b">{{ $label }}</div>
<div class="doc-sig-space"></div>
<div class="doc-c"><span class="doc-sign-line doc-sig-name">{{ $name }}</span></div>
<div class="doc-c doc-sig-label">Signature Over Printed Name</div>
<div class="doc-sig-space" style="height:16px;"></div>
<div class="doc-c"><span class="doc-sign-line">{{ $position }}</span></div>
<div class="doc-c doc-sig-label">Position</div>
@if ($dateLabel)
    <div class="doc-sig-label" style="margin-top:10px;">
        Date: <span class="doc-sign-line" style="min-width:50%; padding-left:6px;">{{ $dateLabel }}</span>
    </div>
@endif
