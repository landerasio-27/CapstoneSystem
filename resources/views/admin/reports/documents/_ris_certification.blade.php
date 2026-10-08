{{--
    The block that closes every sheet: the certification, where each office
    posts its copy, and the lines the two offices sign on.

    Laid out the way the official form draws it. The wording sits on the first
    row over an open area; the supply officer's name is written just above a
    rule that runs out to the same width as the signing lines; that rule and
    the one the titles sit under are the only lines drawn inside the block,
    apart from the frame and the divider between the statement and the posting
    block. Those two rules stop short of the divider, and the accounting
    clerk's rule stops short of the left, exactly as the form has them.

    Expects: $meta, whose posted_by is the supply officer who issued the
    month's papers (blank on a blank form, which is signed by hand) and whose
    posted_position is the office the supply office signs under — held on the
    account's own profile, so the title under the name is the office's own
    wording only until the account says which office it is.
--}}
@php
    $meta = $meta ?? [];
    // The office's own wording, for a sheet handed no position to sign under.
    $meta['posted_position'] = $meta['posted_position'] ?? 'Administrative Officer IV';
@endphp

{{-- The signing lines stop short of the divider, so the statement column is
     split: the first cell carries the rules the names and titles hang from,
     the second is the open strip up to the divider. The divider sits a little
     past the halfway mark so the certification above it stays on one line. --}}
<table class="doc-grid doc-cert no-top">
    <tbody>
        <tr>
            <td colspan="2" class="doc-cert-statement">I HEREBY CERTIFY TO THE CORRECTNESS OF THE ABOVE INFORMATION</td>
            <td class="doc-cert-post">Posted By/ Date:</td>
            <td class="doc-r doc-cert-post">Posted By/ Date:</td>
        </tr>
        {{-- The name sits on the rule: it is the line the officer signs above,
             not a border around the row. It is the officer the record names,
             so it is written in at the sheet's own weight like the entries it
             closes, not in the form's printing. --}}
        <tr>
            <td class="doc-cert-name doc-c" style="width:44.4%;">{{ $meta['posted_by'] ?? '' }}</td>
            <td class="doc-cert-divider" style="width:11.6%;"></td>
            <td style="width:21%;"></td>
            <td style="width:23%;"></td>
        </tr>
        {{-- Open space between the officer's rule and the titles' rules: the
             two offices sign here. --}}
        <tr class="doc-cert-gap">
            <td></td>
            <td class="doc-cert-divider"></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td class="doc-c doc-cert-rule">{{ $meta['posted_position'] ?? 'Administrative Officer IV' }}</td>
            <td class="doc-cert-divider"></td>
            <td></td>
            <td class="doc-c doc-cert-rule">Accounting Clerk</td>
        </tr>
    </tbody>
</table>
