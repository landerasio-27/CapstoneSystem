<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 15px; margin-bottom: 2px; }
        p.subtitle { color: #666; margin-top: 0; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 5px 7px; text-align: left; }
        th { background: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Isabela State University &mdash; Cauayan Campus</h1>
    <p class="subtitle">{{ $title }} &middot; Generated {{ now()->format('F d, Y H:i') }}</p>

    <table>
        <thead>
            <tr>
                @foreach ($headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($headings) }}">No data found for the selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
