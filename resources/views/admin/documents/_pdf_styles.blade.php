{{-- Shared print styles for single-document (pdf) and bundled (pdf_bundle) PDFs. --}}
<style>
    @page { margin: 1.3cm 1.2cm; }
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #000; }

    .form-head { text-align: center; line-height: 1.35; margin-bottom: 8px; }
    .form-head .title { font-size: 13px; font-weight: bold; text-transform: uppercase; }
    .form-head .org   { font-size: 11px; font-weight: bold; }
    .form-head .loc   { font-size: 10px; }

    table { border-collapse: collapse; width: 100%; }
    /* Borders live on cells (not the table) so stacked .bx tables merge into
       one continuous 1px box instead of doubling up at the seams. */
    .bx td, .bx th { border: 1px solid #000; padding: 3px 5px; }
    .no-top td, .no-top th { border-top: none !important; }
    th { font-weight: bold; text-align: center; vertical-align: middle; }

    .b { font-weight: bold; }
    .i { font-style: italic; }
    .tc { text-align: center; }
    .tr { text-align: right; }
    .tl { text-align: left; }
    .top td, .top th { vertical-align: top; }
    .nowrap { white-space: nowrap; }
    .small { font-size: 9px; }

    .fld { font-weight: normal; }
    .val { font-weight: normal; }
    .rowh { height: 20px; }

    .chk { display: inline-block; width: 12px; height: 11px; border: 1px solid #000; vertical-align: middle; margin-right: 6px; }
    .insp-box { width: 90px; height: 44px; border: 1px solid #000; }
    .sig-space { height: 34px; }
    .sig-space-lg { height: 46px; }
    .sig-line { border-top: 1px solid #000; padding-top: 3px; }

    /* The two signature blocks across the foot of a report are read as a pair,
       so they stand on rules of one width: the block is as wide as the longest
       of the captions under it, and the same width serves both columns. */
    .sig-block { display: inline-block; width: 260px; }
    .plain td { border: none !important; padding: 2px 3px; }

    /* Forces the next document in a bundle onto a fresh page. */
    .page-break { page-break-before: always; }
</style>
