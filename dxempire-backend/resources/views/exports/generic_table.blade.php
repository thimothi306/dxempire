<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: sans-serif; font-size: 10px; color: #1a1a1a; }
    h1 { font-size: 16px; margin: 0 0 4px; }
    .meta { color: #666; font-size: 9px; margin-bottom: 14px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #ddd; padding: 5px 7px; text-align: left; }
    th { background: #f3f4f6; font-weight: 600; }
    tr:nth-child(even) { background: #fafafa; }
</style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">Exported {{ now()->format('d M Y, h:i A') }} — {{ count($rows) }} record{{ count($rows) === 1 ? '' : 's' }}</div>
    <table>
        <thead>
            <tr>
                @foreach ($headers as $h)
                    <th>{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
