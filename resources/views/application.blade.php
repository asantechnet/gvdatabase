<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  @php($setting = app(\App\Support\CompanyProfile::class)->current())
  @php($appName = $setting?->short_company_name ?: ($setting?->website_title ?: 'Educational Agency Portal'))
  <link rel="icon" href="{{ $setting?->website_logo ? \Illuminate\Support\Facades\Storage::url($setting->website_logo) : asset('favicon.svg') }}" />
  <meta name="robots" content="noindex, nofollow" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{ $setting?->website_title ?: 'Educational Agency Portal' }}</title>
  <link rel="manifest" href="/manifest.webmanifest" />
  <meta name="theme-color" content="{{ $setting?->primary_color ?: '#7367F0' }}" />
  <meta name="mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-status-bar-style" content="default" />
  <meta name="apple-mobile-web-app-title" content="{{ $appName }}" />
  <link rel="apple-touch-icon" href="/apple-touch-icon.png" />
  @vite(['resources/ts/main.ts'])
</head>

<body>
  <div id="app"></div>
  </body>
</html>
