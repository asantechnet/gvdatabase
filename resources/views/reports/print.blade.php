<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $report['meta']['company']['name'] ?: $report['meta']['company']['short_name'] }} — {{ __($report['title']) }}</title>
    <style>
        @page { size: A4 landscape; margin: 11mm; }
        * { box-sizing: border-box; }
        :root { --ink:#20243d; --muted:#6d7185; --line:#dfe2eb; --soft:#f6f6fa; --primary:#7367f0; --success:#28c76f; }
        body { margin:0; color:var(--ink); background:#eef0f6; font-family:Vazirmatn,Tahoma,"Segoe UI",Arial,sans-serif; font-size:10px; line-height:1.5; }
        .toolbar { position:sticky; z-index:5; inset-block-start:0; display:flex; justify-content:flex-end; padding:12px; background:#fff; border-block-end:1px solid var(--line); }
        button { padding:9px 15px; color:#fff; border:0; border-radius:7px; background:var(--primary); font:inherit; font-weight:700; cursor:pointer; }
        .document { max-width:1380px; margin:18px auto; padding:28px; background:#fff; box-shadow:0 8px 34px rgba(32,36,61,.09); }
        header { display:flex; align-items:flex-start; justify-content:space-between; gap:24px; padding-block-end:18px; border-block-end:3px solid var(--primary); }
        .brand { display:flex; align-items:center; gap:12px; }
        .brand img { width:48px; height:48px; object-fit:contain; }
        .brand-mark { display:grid; place-items:center; width:46px; height:46px; color:#fff; border-radius:10px; background:var(--primary); font-size:18px; font-weight:800; }
        h1,h2,p { margin:0; } h1 { font-size:19px; line-height:1.25; } .address { margin-block-start:4px; color:var(--muted); }
        .report-identity { text-align:end; } .report-identity .eyebrow { color:var(--primary); font-size:9px; font-weight:800; letter-spacing:.12em; text-transform:uppercase; }
        .report-identity h2 { margin-block:3px 7px; font-size:16px; }
        .meta-line { display:flex; justify-content:flex-end; gap:12px; color:var(--muted); }
        .summary { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-block:18px; }
        .summary-card { padding:12px 14px; border:1px solid var(--line); border-radius:9px; background:var(--soft); }
        .summary-card small { display:block; color:var(--muted); font-size:8px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
        .summary-card strong { display:block; margin-block-start:3px; font-size:14px; }
        section { margin-block-start:19px; break-inside:auto; }
        .section-heading { display:flex; align-items:end; justify-content:space-between; gap:12px; margin-block-end:8px; }
        .section-heading h2 { font-size:14px; } .record-count { color:var(--muted); }
        .table-wrap { overflow:hidden; border:1px solid var(--line); border-radius:8px; }
        table { width:100%; border-collapse:collapse; }
        th,td { padding:6px 7px; text-align:start; vertical-align:top; overflow-wrap:anywhere; }
        th { color:#54586d; background:#f0f1f7; border-block-end:1px solid var(--line); font-size:8px; font-weight:800; letter-spacing:.045em; text-transform:uppercase; white-space:nowrap; }
        td { border-block-end:1px solid #eceef3; } tbody tr:nth-child(even) td { background:#fbfbfd; } tbody tr:last-child td { border-block-end:0; } tr { break-inside:avoid; }
        .empty { padding:18px; color:var(--muted); text-align:center; }
        .totals { display:flex; flex-wrap:wrap; gap:7px; margin-block-start:7px; }
        .total { padding:5px 8px; border-radius:6px; background:rgba(115,103,240,.08); }
        .total span { color:var(--muted); } .total strong { margin-inline-start:4px; }
        footer { display:flex; justify-content:space-between; gap:18px; margin-block-start:24px; padding-block-start:10px; color:var(--muted); border-block-start:1px solid var(--line); font-size:8px; }
        @media print { body { background:#fff; } .toolbar { display:none; } .document { max-width:none; margin:0; padding:0; box-shadow:none; } }
    </style>
</head>
<body>
@php
    $company = $report['meta']['company'];
    $companyName = $company['name'] ?: $company['short_name'];
    $rowCount = collect($report['sections'])->sum(fn ($section) => count($section['rows']));
    $displayValue = function ($value) {
        if (is_null($value) || $value === '') return '—';
        if (is_bool($value)) return $value ? __('reports.print.yes') : __('reports.print.no');
        if (is_array($value)) return collect($value)->map(fn ($item, $key) => $key.': '.(is_numeric($item) ? number_format((float) $item, 2) : $item))->implode(' · ');
        if (is_float($value)) return number_format($value, 2);
        return $value;
    };
@endphp
<div class="toolbar"><button onclick="window.print()">{{ __('reports.print.print_pdf') }}</button></div>
<main class="document">
    <header>
        <div class="brand">
            @if (!empty($company['logo']))<img src="{{ asset('storage/'.$company['logo']) }}" alt="">@else<div class="brand-mark">{{ mb_substr($companyName ?: 'R', 0, 1) }}</div>@endif
            <div><h1>{{ $companyName }}</h1><p class="address">{{ $company['address'] }}</p></div>
        </div>
        <div class="report-identity">
            <p class="eyebrow">{{ __('reports.print.business_report') }}</p>
            <h2>{{ __($report['title']) }}</h2>
            <div class="meta-line"><span>{{ $report['filters']['start_date'] }} — {{ $report['filters']['end_date'] }}</span><span>{{ $report['meta']['timezone'] }}</span></div>
        </div>
    </header>

    <div class="summary">
        <div class="summary-card"><small>{{ __('reports.print.reporting_period') }}</small><strong>{{ $report['filters']['start_date'] }} — {{ $report['filters']['end_date'] }}</strong></div>
        <div class="summary-card"><small>{{ __('reports.print.coverage') }}</small><strong>{{ trans_choice('reports.print.section_count', count($report['sections']), ['count' => count($report['sections'])]) }}</strong></div>
        <div class="summary-card"><small>{{ __('reports.print.evidence') }}</small><strong>{{ trans_choice('reports.print.row_count', $rowCount, ['count' => $rowCount]) }}</strong></div>
    </div>

    @foreach ($report['sections'] as $section)
        <section>
            <div class="section-heading"><h2>{{ __($section['title']) }}</h2><span class="record-count">{{ trans_choice('reports.print.row_count', count($section['rows']), ['count' => count($section['rows'])]) }}</span></div>
            <div class="table-wrap"><table>
                <thead><tr>@foreach ($section['columns'] as $column)<th>{{ __("reports.columns.$column") }}</th>@endforeach</tr></thead>
                <tbody>
                @forelse ($section['rows'] as $row)
                    <tr>@foreach ($section['columns'] as $column)<td>{{ $displayValue($row[$column] ?? null) }}</td>@endforeach</tr>
                @empty
                    <tr><td colspan="{{ max(1, count($section['columns'])) }}" class="empty">{{ __('reports.print.no_records') }}</td></tr>
                @endforelse
                </tbody>
            </table></div>
            @if (count($section['totals']))
                <div class="totals">@foreach ($section['totals'] as $key => $value)<div class="total"><span>{{ __("reports.columns.$key") }}</span><strong>{{ $displayValue($value) }}</strong></div>@endforeach</div>
            @endif
        </section>
    @endforeach

    <footer><span>{{ __('reports.print.currency_basis') }}</span><span>{{ __('reports.print.generated_at', ['date' => $report['meta']['generated_at']]) }}</span></footer>
</main>
</body>
</html>
