{{--
    Modern print header for browser-printed views (printJS rendered).

    Inputs:
      $settings  — array from settingsController::get()
      $lang      — langController instance
      $title     — string shown in the navy title bar (e.g. "Sea container — XYZ")
      $subtitle  — small right-side label inside the title bar (e.g. "#NUM")
--}}
@php
    $hasLogo = \App\Http\Controllers\settingsController::brandLogoPath();
    $initial = \App\Http\Controllers\settingsController::brandInitial($settings ?? []);
    $isRtl   = (auth()->user()?->lang ?? 'en') === 'ar';
@endphp
<table style="width: 100%; border-collapse: collapse; margin-bottom: 18px; border-bottom: 2px solid #0e2a47; padding-bottom: 10px;">
    <tr>
        <td style="vertical-align: middle; width: 70%;">
            @if ($hasLogo)
                <img src="{{ asset('images/logo.png') }}?ver={{ env('VERSION') }}" alt="brand"
                     style="height: 44px; vertical-align: middle; margin-{{ $isRtl ? 'left' : 'right' }}: 12px; object-fit: contain;">
            @else
                <span style="display: inline-block; width: 44px; height: 44px;
                             background: #0e2a47; color: #c9a246; text-align: center;
                             font-weight: 800; font-size: 22px; line-height: 44px;
                             border-radius: 6px; margin-{{ $isRtl ? 'left' : 'right' }}: 12px; vertical-align: middle;">{{ $initial }}</span>
            @endif
            <span style="font-size: 20px; font-weight: 800; color: #0e2a47; vertical-align: middle; letter-spacing: 0.5px;">{{ $settings['company_name'] ?? '' }}</span>
            <div style="color: #5b667a; font-size: 10px; margin-top: 4px;">
                {{ $settings['address'] ?? '' }}
                @if (!empty($settings['phone'])) · {{ $settings['phone'] }} @endif
                @if (!empty($settings['email'])) · {{ $settings['email'] }} @endif
            </div>
        </td>
        <td style="vertical-align: top; text-align: {{ $isRtl ? 'left' : 'right' }}; color: #5b667a; font-size: 10px; line-height: 1.5;">
            @if (!empty($settings['commercial_registry']))
                <div>{{ $lang->write('Commercial registry') }}: <strong>{{ $settings['commercial_registry'] }}</strong></div>
            @endif
            @if (!empty($settings['tax_id']))
                <div>{{ $lang->write('Tax ID') }}: <strong>{{ $settings['tax_id'] }}</strong></div>
            @endif
            <div style="margin-top: 4px;">{{ $lang->write('Printed') }}: {{ date('Y-m-d H:i') }}</div>
        </td>
    </tr>
</table>

@if (!empty($title))
    <div style="background: #0e2a47; color: white; padding: 8px 12px; font-size: 13px; font-weight: 700;
                letter-spacing: 1.2px; text-transform: uppercase; margin-bottom: 14px; border-radius: 3px;">
        {{ $title }}
        @if (!empty($subtitle))
            <span style="float: {{ $isRtl ? 'left' : 'right' }}; background: #c9a246; color: #0e2a47;
                         padding: 2px 8px; border-radius: 3px; font-size: 11px;">{{ $subtitle }}</span>
        @endif
    </div>
@endif
