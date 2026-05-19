{{--
    Brand mark for mPDF documents (PnL, BS, CF, receipts, statements, stickers).

    Inputs:
      $settings  — array from settingsController::get()
      $size      — px box size (default 30). Receipts use 40, stickers smaller.

    Behaviour:
      - If a logo is uploaded (public/images/logo.png), embed it via an
        absolute filesystem path (mPDF reads it directly).
      - Otherwise render a navy/gold monogram showing the first letter of
        company_name.
--}}
@php
    $brandColor  = $brandColor  ?? '#0e2a47';
    $accentColor = $accentColor ?? '#c9a246';
    $size        = $size        ?? 30;
    $logoPath    = \App\Http\Controllers\settingsController::brandLogoPath();
    $initial     = \App\Http\Controllers\settingsController::brandInitial($settings ?? []);
@endphp
@if ($logoPath)
    <img src="{{ $logoPath }}"
         style="width: {{ $size }}px; height: {{ $size }}px; vertical-align: middle; margin-right: 8px; border-radius: 4px; object-fit: contain;" />
@else
    <span style="display: inline-block;
                 width: {{ $size }}px; height: {{ $size }}px;
                 background: {{ $brandColor }};
                 color: {{ $accentColor }};
                 text-align: center;
                 font-size: {{ round($size * 0.6) }}px;
                 font-weight: 700;
                 line-height: {{ $size }}px;
                 border-radius: 4px;
                 margin-right: 8px;
                 vertical-align: middle;">{{ $initial }}</span>
@endif
