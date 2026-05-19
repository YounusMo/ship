{{--
    Brand mark for public HTML pages (e.g. /track/{code}).

    Inputs:
      $settings  — array from settingsController::get()
      $size      — px box size (default 38).

    Renders an <img> if a logo is uploaded, else a CSS monogram with the
    first letter of company_name.
--}}
@php
    $brandColor  = $brandColor  ?? '#0e2a47';
    $accentColor = $accentColor ?? '#c9a246';
    $size        = $size        ?? 38;
    $hasLogo     = (bool) \App\Http\Controllers\settingsController::brandLogoPath();
    $initial     = \App\Http\Controllers\settingsController::brandInitial($settings ?? []);
@endphp
@if ($hasLogo)
    <img src="{{ asset('images/logo.png') }}"
         alt="{{ $settings['company_name'] ?? '' }}"
         style="width: {{ $size }}px; height: {{ $size }}px; border-radius: 8px; object-fit: contain; background: #fff;">
@else
    <div style="display: inline-block;
                width: {{ $size }}px; height: {{ $size }}px;
                background: {{ $accentColor }};
                color: {{ $brandColor }};
                border-radius: 8px;
                line-height: {{ $size }}px;
                font-weight: 800;
                font-size: {{ round($size * 0.55) }}px;
                text-align: center;">{{ $initial }}</div>
@endif
