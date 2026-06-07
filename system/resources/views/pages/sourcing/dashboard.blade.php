@php
    if (!in_array(auth()->user()->type, ['admin', 'branch_admin'])) {
        abort(403, 'Unauthorized');
    }
    $actionLabels = [
        'sourcing_create'             => ['label' => 'Created',           'color' => 'secondary'],
        'sourcing_proforma_send'      => ['label' => 'Sent',              'color' => 'primary'],
        'sourcing_proforma_approve'   => ['label' => 'Approved',          'color' => 'success'],
        'sourcing_installment_paid'   => ['label' => 'Payment received',  'color' => 'success'],
        'sourcing_fulfill'            => ['label' => 'Fulfilled',         'color' => 'success'],
        'sourcing_freight_handoff'    => ['label' => 'Sent to freight',   'color' => 'info'],
        'sourcing_client_viewed'      => ['label' => 'Client viewed',     'color' => 'info'],
    ];
@endphp
@extends('layout')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">
            <a href="{{ url('/sourcing') }}" class="text-muted text-decoration-none">
                {{ $lang->write('Sourcing requests') }} ›
            </a>
            {{ $lang->write('Dashboard') }}
        </h1>
        <div class="page-subtitle">
            {{ $lang->write('Pipeline, conversion and recognised revenue at a glance') }}
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
    @if (count($allBranches ?? []) > 0)
        <div class="col-auto">
            <label class="form-label small text-muted">{{ $lang->write('Branch') }}</label>
            <select name="branch" class="form-select form-select-sm">
                <option value="">{{ $lang->write('All branches') }}</option>
                @foreach ($allBranches as $b)
                    <option value="{{ $b->id }}" {{ (int) $branchFilter === (int) $b->id ? 'selected' : '' }}>
                        {{ $lang->branch($b->id) }}
                    </option>
                @endforeach
            </select>
        </div>
    @endif
    <div class="col-auto align-self-end">
        <button class="btn btn-primary btn-sm">{{ $lang->write('Apply') }}</button>
    </div>
    <div class="col-auto align-self-end">
        <button type="button" class="btn btn-outline-warning btn-sm" onclick="runAutoReminders()">
            ✉ {{ $lang->write('Run auto-reminders now') }}
        </button>
    </div>
</form>

<script>
function runAutoReminders() {
    if (!confirm('Scan for quoted proformas that need a reminder and send them?')) return;
    const fd = new FormData();
    fd.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
    fetch('/sourcing/reminders/run', { method:'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            alert('Auto-reminders: scanned ' + res.scanned + ', sent ' + res.sent + ', skipped ' + res.skipped + ', failed ' + res.failed + '.');
        })
        .catch(e => alert('Network error: ' + e.message));
}
</script>

{{-- KPI cards --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="text-muted small">{{ $lang->write('Created') }}</div>
                <div class="h3 mb-0">{{ $createdCount }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="text-muted small">{{ $lang->write('Sent to clients') }}</div>
                <div class="h3 mb-0">{{ $sentCount }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-success">
            <div class="card-body">
                <div class="text-muted small">{{ $lang->write('Approved') }}</div>
                <div class="h3 mb-0 text-success">{{ $approvedCount }}</div>
                <div class="small text-muted">
                    {{ $sentCount > 0 ? $conversion . '% ' . $lang->write('conversion') : '—' }}
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-info">
            <div class="card-body">
                <div class="text-muted small">{{ $lang->write('Fulfilled') }}</div>
                <div class="h3 mb-0 text-info">{{ $fulfilledCount }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Pipeline + revenue --}}
<div class="row g-3 mb-3">
    <div class="col-12 col-md-6">
        <div class="card border-warning">
            <div class="card-body">
                <div class="text-muted small">{{ $lang->write('Pipeline value (USD eq.)') }}</div>
                <div class="h2 mb-0 text-warning">{{ $data->numberFormat($pipelineUsd) }}</div>
                <div class="small text-muted">{{ $lang->write('Quoted + accepted proformas waiting to settle') }}</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="card border-success">
            <div class="card-body">
                <div class="text-muted small">{{ $lang->write('Revenue recognised (per currency)') }}</div>
                @forelse ($revenueByCcy as $ccy => $v)
                    <div class="d-flex justify-content-between">
                        <span>{{ $ccy }}</span>
                        <strong class="text-success">{{ number_format($v, 2) }}</strong>
                    </div>
                @empty
                    <div class="text-muted small">{{ $lang->write('Nothing recognised in this period.') }}</div>
                @endforelse
                <div class="small text-muted">{{ $lang->write('From CoA 4020 cost-object tagged sourcing_request') }}</div>
            </div>
        </div>
    </div>
</div>

{{-- At-risk delivery widget (Phase 12) --}}
@if ($atRiskCount > 0)
    <div class="card mb-3 border-danger">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="card-title mb-0 text-danger">
                    ⚠ {{ $lang->write('At-risk deliveries') }}
                    <span class="badge bg-danger">{{ $atRiskCount }}</span>
                </h5>
            </div>
            <div class="small text-muted mb-3">
                {{ $lang->write('Items where promised date has passed without delivery, or supplier confirmed for a later date than promised.') }}
            </div>
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ $lang->write('Proforma') }}</th>
                        <th>{{ $lang->write('Item') }}</th>
                        <th>{{ $lang->write('Promised') }}</th>
                        <th>{{ $lang->write('Supplier confirmed') }}</th>
                        <th>{{ $lang->write('Slip') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($atRiskTop as $it)
                    @php
                        $promised  = $it->promised_delivery_date;
                        $confirmed = $it->supplier_confirmed_date;
                        $slipDays = null;
                        if ($promised && $confirmed) {
                            $slipDays = round((strtotime($confirmed) - strtotime($promised)) / 86400);
                        } elseif ($promised && !$confirmed) {
                            $slipDays = round((time() - strtotime($promised)) / 86400);
                        }
                    @endphp
                    <tr>
                        <td>
                            <a href="{{ url('/sourcing/' . $it->id) }}" class="text-decoration-none"><code>{{ $it->request_number }}</code></a>
                            <div class="small text-muted">{{ $it->client_name }}</div>
                        </td>
                        <td>
                            {{ $it->item_name }}
                            <div class="small text-muted">{{ $lang->write('item.status.' . ($it->delivery_status ?: 'pending')) }}</div>
                        </td>
                        <td class="small">{{ $promised ?: '—' }}</td>
                        <td class="small">
                            @if ($confirmed)
                                {{ $confirmed }}
                            @else
                                <span class="text-muted">{{ $lang->write('not confirmed') }}</span>
                            @endif
                        </td>
                        <td class="fw-semibold text-danger">+{{ $slipDays }}d</td>
                        <td class="text-end">
                            <a href="{{ url('/sourcing/' . $it->id) }}" class="btn btn-sm btn-outline-primary">{{ $lang->write('Open') }}</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            @if ($atRiskCount > count($atRiskTop))
                <div class="small text-muted mt-2">
                    {{ $lang->write('Showing top') }} {{ count($atRiskTop) }} {{ $lang->write('of') }} {{ $atRiskCount }}.
                </div>
            @endif
        </div>
    </div>
@endif

{{-- Health watch — Phase 15: lowest-score active proformas --}}
@if (!empty($healthWatch['rows']))
    <div class="card mb-3 border-warning">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="card-title mb-0 text-warning">
                    ❤︎ {{ $lang->write('Health watch') }}
                    @if ($healthWatch['count'] > 0)
                        <span class="badge bg-warning text-dark">{{ $healthWatch['count'] }}</span>
                    @endif
                </h5>
                <div class="small text-muted">
                    {{ $lang->write('Lowest-scoring active proformas — act before they go silent.') }}
                </div>
            </div>
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ $lang->write('Proforma') }}</th>
                        <th>{{ $lang->write('Status') }}</th>
                        <th class="text-end" style="width:90px">{{ $lang->write('Score') }}</th>
                        <th>{{ $lang->write('Why') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($healthWatch['rows'] as $h)
                    @php
                        $cls = $h->score < 40 ? 'text-danger' : ($h->score < 60 ? 'text-warning' : 'text-success');
                        $weak = array_slice(array_filter($h->factors, fn($f) => ($f['points'] ?? 0) < 5), 0, 2);
                    @endphp
                    <tr>
                        <td>
                            <a href="{{ url('/sourcing/' . $h->id) }}" class="text-decoration-none"><code>{{ $h->request_number }}</code></a>
                            <div class="small text-muted">{{ $h->client_name }}</div>
                        </td>
                        <td><span class="badge bg-light text-dark">{{ $h->status }}</span></td>
                        <td class="text-end fw-semibold {{ $cls }}">{{ $h->score }}/100</td>
                        <td class="small">
                            @foreach ($weak as $w)
                                <span class="badge bg-light text-muted me-1">{{ $w['label'] }}@if(isset($w['note'])): {{ $w['note'] }}@endif</span>
                            @endforeach
                        </td>
                        <td class="text-end">
                            <a href="{{ url('/sourcing/' . $h->id) }}" class="btn btn-sm btn-outline-primary">{{ $lang->write('Open') }}</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            @if ($healthWatch['count'] > count($healthWatch['rows']))
                <div class="small text-muted mt-2">
                    {{ $lang->write('Showing top') }} {{ count($healthWatch['rows']) }} {{ $lang->write('of') }} {{ $healthWatch['count'] }} {{ $lang->write('needs-attention') }}.
                </div>
            @endif
        </div>
    </div>
@endif

<div class="row g-3">
    {{-- Left col: top clients + branch breakdown --}}
    <div class="col-12 col-lg-7">
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">{{ $lang->write('Top clients') }}</h5>
                @if (count($topClients) < 1)
                    <p class="text-muted mb-0">{{ $lang->write('No client activity in this period.') }}</p>
                @else
                    <table class="table table-sm align-middle">
                        <thead><tr>
                            <th>{{ $lang->write('Client') }}</th>
                            <th class="text-end">{{ $lang->write('Proformas') }}</th>
                            <th class="text-end">{{ $lang->write('Total value') }}</th>
                        </tr></thead>
                        <tbody>
                        @foreach ($topClients as $c)
                            <tr>
                                <td>
                                    @if ($c->code) <span class="text-muted small">{{ $c->code }}</span> · @endif
                                    {{ $c->name }}
                                </td>
                                <td class="text-end">{{ $c->proforma_count }}</td>
                                <td class="text-end">{{ number_format((float) $c->total_value, 2) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        @if (count($branchBreakdown) > 0)
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">{{ $lang->write('Branch breakdown') }}</h5>
                    <table class="table table-sm align-middle">
                        <thead><tr>
                            <th>{{ $lang->write('Branch') }}</th>
                            <th class="text-end">{{ $lang->write('Quoted') }}</th>
                            <th class="text-end">{{ $lang->write('Accepted') }}</th>
                            <th class="text-end">{{ $lang->write('Fulfilled') }}</th>
                            <th class="text-end">{{ $lang->write('Total value') }}</th>
                        </tr></thead>
                        <tbody>
                        @foreach ($branchBreakdown as $b)
                            <tr>
                                <td>{{ $b->name ?? '—' }}</td>
                                <td class="text-end">{{ (int) $b->quoted_n }}</td>
                                <td class="text-end">{{ (int) $b->accepted_n }}</td>
                                <td class="text-end">{{ (int) $b->fulfilled_n }}</td>
                                <td class="text-end">{{ number_format((float) $b->total_value, 2) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    {{-- Right col: activity feed --}}
    <div class="col-12 col-lg-5">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-3">{{ $lang->write('Recent activity') }}</h5>
                @if (count($recent) < 1)
                    <p class="text-muted mb-0">{{ $lang->write('No recent activity.') }}</p>
                @else
                    <div style="position:relative;padding-inline-start:1.5rem;border-inline-start:2px solid var(--color-border);">
                        @foreach ($recent as $ev)
                            @php
                                $info = $actionLabels[$ev->action] ?? ['label' => $ev->action, 'color' => 'secondary'];
                                $reqRow = $reqLookup[$ev->target_id] ?? null;
                            @endphp
                            <div class="mb-3" style="position:relative;">
                                <div style="position:absolute;left:-1.85rem;top:.25rem;width:.6rem;height:.6rem;border-radius:50%;background:var(--color-{{ $info['color'] }}-500, #6b7280);"></div>
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <span class="badge bg-{{ $info['color'] }}">{{ $lang->write($info['label']) }}</span>
                                        @if ($reqRow)
                                            <a href="{{ url('/sourcing/' . $reqRow->id) }}" class="ms-1 text-decoration-none">
                                                <code>{{ $reqRow->request_number }}</code>
                                            </a>
                                        @endif
                                    </div>
                                    <div class="small text-muted text-end">
                                        {{ substr($ev->created_at, 0, 16) }}
                                        @if ($ev->user_name)
                                            <div>· {{ $ev->user_name }}</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@endsection
