<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <title>{{ $report['meta']['company']['name'] }} — {{ __($report['title']) }}</title>
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        * { box-sizing: border-box; }
        @font-face { font-family: "Report Persian"; src: local("Vazirmatn"), local("Vazir"), local("Tahoma"); font-display: swap; }
        body { color: #172033; font-family: "Report Persian", Tahoma, "Segoe UI", Arial, sans-serif; font-size: 11px; margin: 0; }
        header { align-items: center; border-bottom: 2px solid #172033; display: flex; justify-content: space-between; margin-bottom: 16px; padding-bottom: 10px; }
        h1 { font-size: 20px; margin: 0; } h2 { font-size: 15px; margin: 18px 0 8px; }
        .meta { color: #526077; line-height: 1.6; text-align: end; }
        table { border-collapse: collapse; margin-bottom: 8px; width: 100%; }
        th, td { border: 1px solid #d9deea; padding: 5px 6px; text-align: start; vertical-align: top; }
        th { background: #eef1f7; font-weight: 700; }
        tr { break-inside: avoid; } section { break-inside: auto; }
        .totals { background: #f7f8fb; font-weight: 700; margin-bottom: 14px; padding: 8px; }
        .actions { margin-bottom: 12px; } button { cursor: pointer; padding: 8px 14px; }
        footer { border-top: 1px solid #cfd5e2; color: #68758c; margin-top: 20px; padding-top: 8px; }
        @media print { .actions { display: none; } }
    </style>
</head>
<body>
<div class="actions"><button onclick="window.print()">{{ __('Print / Save PDF') }}</button></div>
<header>
    <div>
        <h1>{{ $report['meta']['company']['name'] ?: $report['meta']['company']['short_name'] }}</h1>
        <div>{{ $report['meta']['company']['address'] }}</div>
    </div>
    <div class="meta">
        <strong>{{ __($report['title']) }}</strong><br>
        {{ $report['filters']['start_date'] }} — {{ $report['filters']['end_date'] }}<br>
        {{ $report['meta']['generated_at'] }} ({{ $report['meta']['timezone'] }})
    </div>
</header>
@foreach ($report['sections'] as $section)
    <section>
        <h2>{{ __($section['title']) }}</h2>
        <table>
            <thead><tr>@foreach ($section['columns'] as $column)<th>{{ __("reports.columns.$column") }}</th>@endforeach</tr></thead>
            <tbody>
            @forelse ($section['rows'] as $row)
                <tr>@foreach ($section['columns'] as $column)<td>{{ is_bool($row[$column] ?? null) ? (($row[$column] ?? false) ? __('Yes') : __('No')) : ($row[$column] ?? '—') }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($section['columns']) }}">{{ __('No records found') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="totals">{{ __('Totals') }}: {{ json_encode($section['totals'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</div>
    </section>
@endforeach
<footer>{{ $report['meta']['currency_basis'] }}</footer>
</body>
</html>
