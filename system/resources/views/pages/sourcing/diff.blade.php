@php
    if (!in_array(auth()->user()->type, ['admin', 'branch_admin'])) {
        abort(403, 'Unauthorized');
    }

    $totalChanges = count($changes['proforma'])
                  + count($changes['items']['added']) + count($changes['items']['removed']) + count($changes['items']['modified'])
                  + count($changes['payments']['added']) + count($changes['payments']['removed']) + count($changes['payments']['modified']);
@endphp
@extends('layout')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">
            <a href="{{ url('/sourcing/' . $req->id) }}" class="text-muted text-decoration-none">
                <code>{{ $req->request_number }}</code> ›
            </a>
            {{ $lang->write('Compare versions') }}
        </h1>
        <div class="page-subtitle">
            {{ $totalChanges }} {{ $lang->write('change(s) detected') }}
        </div>
    </div>
</div>

{{-- Version pickers --}}
<form method="GET" class="row g-2 mb-3 align-items-end">
    <div class="col-auto">
        <label class="form-label small text-muted">{{ $lang->write('Compare') }}</label>
        <select name="a" class="form-select form-select-sm">
            @foreach ($versions as $v)
                <option value="{{ $v->version_no }}" {{ $sideA['key'] === (string) $v->version_no ? 'selected' : '' }}>
                    v{{ $v->version_no }} — {{ $v->trigger }} — {{ substr($v->created_at, 0, 16) }}
                </option>
            @endforeach
            <option value="live" {{ $sideA['key'] === 'live' ? 'selected' : '' }}>{{ $lang->write('Live (now)') }}</option>
        </select>
    </div>
    <div class="col-auto pb-1">↔</div>
    <div class="col-auto">
        <label class="form-label small text-muted">{{ $lang->write('With') }}</label>
        <select name="b" class="form-select form-select-sm">
            @foreach ($versions as $v)
                <option value="{{ $v->version_no }}" {{ $sideB['key'] === (string) $v->version_no ? 'selected' : '' }}>
                    v{{ $v->version_no }} — {{ $v->trigger }} — {{ substr($v->created_at, 0, 16) }}
                </option>
            @endforeach
            <option value="live" {{ $sideB['key'] === 'live' ? 'selected' : '' }}>{{ $lang->write('Live (now)') }}</option>
        </select>
    </div>
    <div class="col-auto">
        <button class="btn btn-primary btn-sm">{{ $lang->write('Apply') }}</button>
    </div>
</form>

{{-- Side labels --}}
<div class="row g-2 mb-3">
    <div class="col-6">
        <div class="card border-secondary">
            <div class="card-body p-2">
                <strong>A: {{ $sideA['label'] }}</strong>
                <div class="small text-muted">{{ $sideA['captured_at'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-6">
        <div class="card border-primary">
            <div class="card-body p-2">
                <strong>B: {{ $sideB['label'] }}</strong>
                <div class="small text-muted">{{ $sideB['captured_at'] }}</div>
            </div>
        </div>
    </div>
</div>

@if ($totalChanges === 0)
    <div class="alert alert-success">
        ✓ {{ $lang->write('No differences detected between these versions.') }}
    </div>
@endif

{{-- Proforma-level changes --}}
@if (count($changes['proforma']) > 0)
    <div class="card mb-3">
        <div class="card-body">
            <h5 class="card-title">{{ $lang->write('Proforma fields') }}</h5>
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ $lang->write('Field') }}</th>
                        <th>A: {{ $sideA['label'] }}</th>
                        <th>B: {{ $sideB['label'] }}</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($changes['proforma'] as $c)
                    <tr>
                        <td><code>{{ $c['field'] }}</code></td>
                        <td class="text-muted"><del>{{ $c['from'] ?? '—' }}</del></td>
                        <td><strong class="text-success">{{ $c['to'] ?? '—' }}</strong></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

{{-- Item changes --}}
@if (count($changes['items']['added']) + count($changes['items']['removed']) + count($changes['items']['modified']) > 0)
    <div class="card mb-3">
        <div class="card-body">
            <h5 class="card-title">{{ $lang->write('Items') }}</h5>

            @if (count($changes['items']['added']) > 0)
                <div class="mb-3">
                    <h6 class="text-success">+ {{ $lang->write('Added') }} ({{ count($changes['items']['added']) }})</h6>
                    <ul class="mb-0">
                        @foreach ($changes['items']['added'] as $it)
                            <li>
                                <strong>{{ $it['name'] }}</strong>
                                @if (!empty($it['code']))<span class="text-muted small"> · {{ $it['code'] }}</span>@endif
                                — {{ $it['quantity'] }} {{ $it['unit'] ?? '' }}
                                @ {{ $it['unit_price_to_client'] }} {{ strtoupper($it['unit_cost_currency'] ?? 'usd') }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (count($changes['items']['removed']) > 0)
                <div class="mb-3">
                    <h6 class="text-danger">− {{ $lang->write('Removed') }} ({{ count($changes['items']['removed']) }})</h6>
                    <ul class="mb-0">
                        @foreach ($changes['items']['removed'] as $it)
                            <li><del>{{ $it['name'] }} — {{ $it['quantity'] }} {{ $it['unit'] ?? '' }}</del></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (count($changes['items']['modified']) > 0)
                <h6>~ {{ $lang->write('Modified') }} ({{ count($changes['items']['modified']) }})</h6>
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>{{ $lang->write('Item') }}</th>
                            <th>{{ $lang->write('Field') }}</th>
                            <th>A</th>
                            <th>B</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($changes['items']['modified'] as $row)
                        @foreach ($row['changes'] as $i => $c)
                            <tr>
                                @if ($i === 0)
                                    <td rowspan="{{ count($row['changes']) }}" class="align-top">
                                        <strong>{{ $row['item']['name'] }}</strong>
                                        @if (!empty($row['item']['code']))<div class="small text-muted">{{ $row['item']['code'] }}</div>@endif
                                    </td>
                                @endif
                                <td><code>{{ $c['field'] }}</code></td>
                                <td class="text-muted"><del>{{ $c['from'] ?? '—' }}</del></td>
                                <td><strong class="text-success">{{ $c['to'] ?? '—' }}</strong></td>
                            </tr>
                        @endforeach
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
@endif

{{-- Payment changes --}}
@if (count($changes['payments']['added']) + count($changes['payments']['removed']) + count($changes['payments']['modified']) > 0)
    <div class="card mb-3">
        <div class="card-body">
            <h5 class="card-title">{{ $lang->write('Payment schedule') }}</h5>

            @if (count($changes['payments']['added']) > 0)
                <div class="mb-3">
                    <h6 class="text-success">+ {{ $lang->write('Added') }}</h6>
                    <ul class="mb-0">
                        @foreach ($changes['payments']['added'] as $p)
                            <li>{{ $p['label'] }} — {{ $p['amount'] }} {{ strtoupper($p['currency']) }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (count($changes['payments']['removed']) > 0)
                <div class="mb-3">
                    <h6 class="text-danger">− {{ $lang->write('Removed') }}</h6>
                    <ul class="mb-0">
                        @foreach ($changes['payments']['removed'] as $p)
                            <li><del>{{ $p['label'] }} — {{ $p['amount'] }} {{ strtoupper($p['currency']) }}</del></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (count($changes['payments']['modified']) > 0)
                <h6>~ {{ $lang->write('Modified') }}</h6>
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>{{ $lang->write('Installment') }}</th>
                            <th>{{ $lang->write('Field') }}</th>
                            <th>A</th>
                            <th>B</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($changes['payments']['modified'] as $row)
                        @foreach ($row['changes'] as $i => $c)
                            <tr>
                                @if ($i === 0)
                                    <td rowspan="{{ count($row['changes']) }}" class="align-top">
                                        {{ $row['payment']['label'] }}
                                    </td>
                                @endif
                                <td><code>{{ $c['field'] }}</code></td>
                                <td class="text-muted"><del>{{ $c['from'] ?? '—' }}</del></td>
                                <td><strong class="text-success">{{ $c['to'] ?? '—' }}</strong></td>
                            </tr>
                        @endforeach
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
@endif

@endsection
