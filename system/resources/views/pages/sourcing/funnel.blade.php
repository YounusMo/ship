@php
    if (!in_array(auth()->user()->type, ['admin', 'branch_admin'])) {
        abort(403, 'Unauthorized');
    }
    $stages = [
        'open'      => ['label' => 'Created',   'color' => '#6b7280'],
        'searching' => ['label' => 'Searching', 'color' => '#0891b2'],
        'quoted'    => ['label' => 'Quoted',    'color' => '#2563eb'],
        'accepted'  => ['label' => 'Accepted',  'color' => '#ea580c'],
        'fulfilled' => ['label' => 'Fulfilled', 'color' => '#16a34a'],
    ];
    $maxCum = max(array_values($cumulative)) ?: 1;
@endphp
@extends('layout')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">
            <a href="{{ url('/sourcing') }}" class="text-muted text-decoration-none">
                {{ $lang->write('Sourcing requests') }} ›
            </a>
            {{ $lang->write('Funnel analytics') }}
        </h1>
        <div class="page-subtitle">
            {{ $lang->write('Stage-by-stage conversion, dwell time, and stuck deals') }}
        </div>
    </div>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-auto">
        <label class="form-label small text-muted">{{ $lang->write('From') }}</label>
        <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm">
    </div>
    <div class="col-auto">
        <label class="form-label small text-muted">{{ $lang->write('To') }}</label>
        <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm">
    </div>
    <div class="col-auto">
        <label class="form-label small text-muted">{{ $lang->write('Stuck after (days)') }}</label>
        <input type="number" name="stuck_days" value="{{ $stuckDays }}" min="1" max="365" class="form-control form-control-sm" style="width:100px;">
    </div>
    <div class="col-auto align-self-end">
        <button class="btn btn-primary btn-sm">{{ $lang->write('Apply') }}</button>
    </div>
</form>

{{-- Visual funnel --}}
<div class="card mb-3">
    <div class="card-body">
        <h5 class="card-title">{{ $lang->write('Funnel') }}</h5>
        @foreach ($stages as $key => $meta)
            @php
                $count = $cumulative[$key] ?? 0;
                $widthPct = $maxCum > 0 ? round(100 * $count / $maxCum) : 0;
            @endphp
            <div class="d-flex align-items-center mb-2">
                <div style="width:120px;" class="small text-muted">{{ $lang->write($meta['label']) }}</div>
                <div style="flex:1;background:#f3f4f6;border-radius:6px;height:32px;position:relative;">
                    <div style="background:{{ $meta['color'] }};height:100%;border-radius:6px;width:{{ max(2, $widthPct) }}%;display:flex;align-items:center;justify-content:flex-end;padding:0 10px;">
                        <span style="color:#fff;font-weight:600;font-size:12px;">{{ $count }}</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

{{-- Conversion rates + dwell time --}}
<div class="row g-3 mb-3">
    <div class="col-12 col-md-7">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title">{{ $lang->write('Stage-to-stage conversion') }}</h5>
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ $lang->write('From → To') }}</th>
                            <th class="text-end">{{ $lang->write('Rate') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ([['open','searching','Created → Searching'], ['searching','quoted','Searching → Quoted'], ['quoted','accepted','Quoted → Accepted'], ['accepted','fulfilled','Accepted → Fulfilled']] as $row)
                            @php
                                [$a, $b, $label] = $row;
                                $rate = $conv["{$a}_to_{$b}"] ?? 0;
                            @endphp
                            <tr>
                                <td>{{ $label }}</td>
                                <td class="text-end fw-semibold {{ $rate >= 50 ? 'text-success' : ($rate < 20 ? 'text-danger' : '') }}">
                                    {{ $rate }}%
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-5">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title">{{ $lang->write('Average dwell') }}</h5>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted small">{{ $lang->write('Created → Sent') }}</span>
                    <strong>{{ $dwell['create_to_send'] !== null ? $dwell['create_to_send'] . 'h' : '—' }}</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted small">{{ $lang->write('Sent → Approved') }}</span>
                    <strong>{{ $dwell['send_to_approve'] !== null ? $dwell['send_to_approve'] . 'h' : '—' }}</strong>
                </div>
                <div class="small text-muted mt-3">
                    {{ $lang->write('Calculated from sent_at and approved_at on each proforma in the window.') }}
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Stuck deals --}}
<div class="card">
    <div class="card-body">
        <h5 class="card-title">
            {{ $lang->write('Stuck deals') }}
            <span class="text-muted small">({{ $stuckDays }}+ {{ $lang->write('days without movement') }})</span>
        </h5>
        @if (count($stuck) < 1)
            <p class="text-muted mb-0">{{ $lang->write('Nothing stuck. Pipeline is moving.') }}</p>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>{{ $lang->write('Proforma') }}</th>
                            <th>{{ $lang->write('Client') }}</th>
                            <th>{{ $lang->write('Status') }}</th>
                            <th class="text-end">{{ $lang->write('Value') }}</th>
                            <th>{{ $lang->write('Last touch') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($stuck as $s)
                        <tr>
                            <td><code>{{ $s->request_number }}</code><div class="small text-muted">{{ $s->title }}</div></td>
                            <td>
                                @if ($s->client_code)<span class="text-muted small">{{ $s->client_code }}</span> · @endif
                                {{ $s->client_name }}
                            </td>
                            <td><span class="badge bg-secondary">{{ ucfirst($s->status) }}</span></td>
                            <td class="text-end">{{ number_format((float) $s->proforma_total, 2) }} <span class="text-muted small">{{ strtoupper($s->currency) }}</span></td>
                            <td class="small text-muted">{{ substr($s->updated_at, 0, 10) }}</td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ url('/sourcing/' . $s->id) }}">{{ $lang->write('Open') }}</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

@endsection
