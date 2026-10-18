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
  <link rel="stylesheet" type="text/css" href="{{ asset('loader.css') }}" />
  @vite(['resources/ts/main.ts'])
</head>

<body>
  <div id="app">
    <div id="loading-bg">
      @if($setting?->website_logo)
        <div class="loading-logo"><img src="{{ \Illuminate\Support\Facades\Storage::url($setting->website_logo) }}" alt="" /></div>
      @endif
      <div class=" loading">
        <div class="effect-1 effects"></div>
        <div class="effect-2 effects"></div>
        <div class="effect-3 effects"></div>
      </div>
    </div>
  </div>
  
  <script>
    const loaderColor = localStorage.getItem('vuexy-initial-loader-bg') || '#FFFFFF'
    const primaryColor = localStorage.getItem('vuexy-initial-loader-color') || '#7367F0'

    if (loaderColor)
      document.documentElement.style.setProperty('--initial-loader-bg', loaderColor)
    if (loaderColor)
      document.documentElement.style.setProperty('--initial-loader-bg', loaderColor)

    if (primaryColor)
      document.documentElement.style.setProperty('--initial-loader-color', primaryColor)
  </script>
  </body>
</html>
