@php
    if (!in_array(auth()->user()->type, ['admin'])) { abort(403); }
@endphp
@extends('layout')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">
            <a href="{{url('/profits')}}" class="text-muted text-decoration-none">
                {{ $lang->write('Profits') }} ›
            </a>
            {{ $kind === 'sky' ? $lang->write('Air container') : $lang->write('Sea container') }}
            <code>#{{ $container->id }}</code>
            @if (!empty($container->number))
                · <span class="text-muted">{{ $container->number }}</span>
            @endif
        </h1>
        <div class="page-subtitle">
            {{ $lang->write('Profit & loss derived from journal_lines (cost_object_type = ') }}<code>{{ $costObjectKey }}</code>)
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-3">
                <div class="text-muted small">{{ $lang->write('Container') }}</div>
                <div class="h6 mb-0">{{ $container->name ?? '—' }}</div>
            </div>
            <div class="col-6 col-md-2">
                <div class="text-muted small">{{ $lang->write('Type') }}</div>
                <div class="h6 mb-0">{{ $container->type ?? '—' }}</div>
            </div>
            <div class="col-6 col-md-2">
                <div class="text-muted small">{{ $lang->write('Size') }}</div>
                <div class="h6 mb-0">{{ $container->size ?? '—' }}</div>
            </div>
            <div class="col-6 col-md-2">
                <div class="text-muted small">{{ $lang->write('Status') }}</div>
                <div class="h6 mb-0">{{ $container->status ?? '—' }}</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted small">{{ $lang->write('Created') }}</div>
                <div class="h6 mb-0">{{ $container->created_date ?? '—' }}</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-2">
    @foreach ($currencies as $c)
        @php
            $net  = $totals['net'][$c];
            $cash = $cashOutflow[$c] ?? 0.0;
            // "Implied cash margin" = revenue collected minus cash that
            // actually left for this container. Useful when supplier/broker
            // payments are still sitting in prepayment asset accounts and
            // haven't been formally expensed yet.
            $impliedMargin = $totals['revenue'][$c] - $cash;
        @endphp
        @if (abs($totals['revenue'][$c]) > 0.0001 || abs($totals['expense'][$c]) > 0.0001 || abs($cash) > 0.0001)
            <div class="col-12 col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted small">{{ strtoupper($c) }} {{ $lang->write('net') }}</div>
                        <div class="h3 mb-0 {{ $net > 0 ? 'text-success' : ($net < 0 ? 'text-danger' : '') }}">
                            {{ $data->numberFormat($net) }}
                        </div>
                        <div class="small text-muted">
                            {{ $lang->write('Rev') }} {{ $data->numberFormat($totals['revenue'][$c]) }} ·
                            {{ $lang->write('Exp') }} {{ $data->numberFormat($totals['expense'][$c]) }}
                        </div>
                        <hr class="my-2">
                        <div class="small">
                            {{ $lang->write('Cash settled') }}:
                            <strong class="{{ $cash > 0 ? 'text-danger' : ($cash < 0 ? 'text-success' : '') }}">
                                {{ $cash > 0 ? '−' : ($cash < 0 ? '+' : '') }}{{ $data->numberFormat(abs($cash)) }}
                            </strong>
                        </div>
                        <div class="small">
                            {{ $lang->write('Implied cash margin') }}:
                            <strong class="{{ $impliedMargin > 0 ? 'text-success' : ($impliedMargin < 0 ? 'text-danger' : '') }}">
                                {{ $data->numberFormat($impliedMargin) }}
                            </strong>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
</div>

<div class="alert alert-info small mb-3">
    {{ $lang->write('In the current model, supplier and broker payments land in prepayment asset accounts (1200 / 1300) — they only become formal expenses when explicitly recognized. Until that recognition step is added, "Cash settled" is the most realistic per-container cost figure; "Net" reflects pure P&L accruals.') }}
</div>

<div class="card mb-3">
    <div class="card-body">
        <h5 class="card-title">{{ $lang->write('Revenue') }}</h5>
        @if (count($revenue) < 1)
            <p class="text-muted mb-0">{{ $lang->write('No revenue posted against this container yet.') }}</p>
        @else
            <table class="table table-sm align-middle">
                <thead><tr>
                    <th>{{ $lang->write('Code') }}</th>
                    <th>{{ $lang->write('Account') }}</th>
                    @foreach ($currencies as $c)<th class="text-end">{{ strtoupper($c) }}</th>@endforeach
                </tr></thead>
                <tbody>
                @foreach ($revenue as $r)
                    <tr>
                        <td class="text-muted">{{ $r['code'] }}</td>
                        <td>{{ $lang->write($r['name']) }}</td>
                        @foreach ($currencies as $c)
                            <td class="text-end">{{ abs($r['amounts'][$c]) > 0.0001 ? $data->numberFormat($r['amounts'][$c]) : '—' }}</td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr class="fw-semibold">
                        <td colspan="2">{{ $lang->write('Total revenue') }}</td>
                        @foreach ($currencies as $c)
                            <td class="text-end">{{ $data->numberFormat($totals['revenue'][$c]) }}</td>
                        @endforeach
                    </tr>
                </tfoot>
            </table>
        @endif
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <h5 class="card-title">{{ $lang->write('Expenses') }}</h5>
        @if (count($expense) < 1)
            <p class="text-muted mb-0">{{ $lang->write('No expenses posted against this container yet.') }}</p>
        @else
            <table class="table table-sm align-middle">
                <thead><tr>
                    <th>{{ $lang->write('Code') }}</th>
                    <th>{{ $lang->write('Account') }}</th>
                    @foreach ($currencies as $c)<th class="text-end">{{ strtoupper($c) }}</th>@endforeach
                </tr></thead>
                <tbody>
                @foreach ($expense as $r)
                    <tr>
                        <td class="text-muted">{{ $r['code'] }}</td>
                        <td>{{ $lang->write($r['name']) }}</td>
                        @foreach ($currencies as $c)
                            <td class="text-end">{{ abs($r['amounts'][$c]) > 0.0001 ? $data->numberFormat($r['amounts'][$c]) : '—' }}</td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr class="fw-semibold">
                        <td colspan="2">{{ $lang->write('Total expenses') }}</td>
                        @foreach ($currencies as $c)
                            <td class="text-end">{{ $data->numberFormat($totals['expense'][$c]) }}</td>
                        @endforeach
                    </tr>
                </tfoot>
            </table>
        @endif
    </div>
</div>

@if (count($other) > 0)
<div class="card mb-3">
    <div class="card-body">
        <h5 class="card-title">{{ $lang->write('Balance-sheet movements') }}</h5>
        <p class="text-muted small">
            {{ $lang->write('Asset / liability flows tagged to this container — usually AR / AP that will settle later.') }}
        </p>
        <table class="table table-sm align-middle">
            <thead><tr>
                <th>{{ $lang->write('Code') }}</th>
                <th>{{ $lang->write('Account') }}</th>
                <th>{{ $lang->write('Type') }}</th>
                @foreach ($currencies as $c)<th class="text-end">{{ strtoupper($c) }}</th>@endforeach
            </tr></thead>
            <tbody>
            @foreach ($other as $r)
                <tr>
                    <td class="text-muted">{{ $r['code'] }}</td>
                    <td>{{ $lang->write($r['name']) }}</td>
                    <td><span class="badge bg-secondary">{{ $r['acct_type'] }}</span></td>
                    @foreach ($currencies as $c)
                        <td class="text-end">{{ abs($r['amounts'][$c]) > 0.0001 ? $data->numberFormat($r['amounts'][$c]) : '—' }}</td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@endsection
