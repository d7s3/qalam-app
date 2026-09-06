<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ $result->title }}</title>
    <style>
        body { direction: rtl; font-size: 11px; color: #1a1513; }
        h1 { font-size: 17px; margin: 0 0 4px; color: {{ config('brand.colors.primary') }}; }
        .subtitle { font-size: 10px; color: #666; margin: 0 0 2px; }
        .brand { font-size: 10px; color: #888; margin: 0 0 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 0.5px solid #d8d0cc; padding: 5px 6px; text-align: right; }
        thead th { background: #f2ece9; font-weight: bold; font-size: 10px; }
        tbody tr:nth-child(even) td { background: #fbf9f8; }
        tfoot td { background: #f2ece9; font-weight: bold; }
        .empty { text-align: center; padding: 30px; color: #888; }
    </style>
</head>
<body>
    <h1>{{ $result->title }}</h1>
    @if ($result->subtitle)
        <p class="subtitle">{{ $result->subtitle }}</p>
    @endif
    <p class="brand">{{ config('brand.name') }} — طُبع في {{ \App\Support\HijriDate::withGregorian(now()) }}</p>

    @if ($result->isEmpty())
        <p class="empty">لا بيانات في هذه المدة.</p>
    @else
        <table>
            <thead>
                <tr>
                    @foreach ($result->columns as $column)
                        <th>{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($result->rows as $row)
                    <tr>
                        @foreach ($result->cells($row) as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
            @if ($result->totals !== [])
                <tfoot>
                    <tr>
                        @foreach ($result->cells($result->totals) as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>
    @endif
</body>
</html>
