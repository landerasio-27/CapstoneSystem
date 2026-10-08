{{--
    Print/PDF styles for the official card sheet.

    Kept in step with the .sheet rules in resources/css/app.css: the same markup
    is rendered on screen (styled by the bundle) and here (dompdf only reads this
    stylesheet, and cannot be relied on for CSS custom properties or modern
    layout, so everything is plain tables and literals).
--}}
<style>
    @page { size: A4 portrait; margin: 12mm 10mm 14mm 10mm; }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        color: #000;
        background: #fff;
        font-family: "DejaVu Serif", "Times New Roman", serif;
        font-size: 11px;
        line-height: 1.3;
    }

    .sheet { color: #000; font-size: 11px; }

    .sheet-title {
        margin: 10px 0 0;
        text-align: center;
        font-size: 16px;
        font-weight: bold;
        letter-spacing: 2px;
        text-transform: uppercase;
    }

    .sheet-subtitle { display: none; }

    .sheet-meta {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
        font-size: 10.5px;
    }
    .sheet-meta th,
    .sheet-meta td {
        border: 1px solid #000;
        padding: 4px 5px;
        text-align: left;
        vertical-align: top;
        font-weight: normal;
        background: #fff;
    }
    .sheet-meta th { width: 17%; }
    .sheet-meta .sheet-value { font-weight: bold; }

    .sheet-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 0;
        font-size: 10px;
    }
    .sheet-table th,
    .sheet-table td {
        border: 1px solid #000;
        padding: 3px 4px;
        vertical-align: top;
    }
    .sheet-table thead th {
        background: #fff;
        text-align: center;
        font-weight: bold;
        text-transform: none;
    }
    .sheet-table .sheet-center { text-align: center; }
    .sheet-table .sheet-num { text-align: right; }
    .sheet-table .sheet-in,
    .sheet-table .sheet-out { color: #000; font-weight: bold; }
    .sheet-empty-row td { height: 20px; }

    /* Screen-only chrome around the printable sheet (ignored by dompdf). */
    .print-bar {
        max-width: 210mm;
        margin: 16px auto 0;
        padding: 0 12px;
        display: block;
        text-align: right;
    }
    .print-bar a,
    .print-bar button {
        display: inline-block;
        margin-left: 6px;
        padding: 8px 14px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        background: #fff;
        color: #0f172a;
        font-family: inherit;
        font-size: 12px;
        text-decoration: none;
        cursor: pointer;
    }
    .print-bar .primary { background: #166534; border-color: #166534; color: #fff; }

    .paper {
        width: 210mm;
        min-height: 297mm;
        margin: 12px auto 24px;
        padding: 14mm 12mm;
        background: #fff;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.18);
    }

    @media print {
        .print-bar { display: none; }
        .paper { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
    }
</style>
