@php
    if (!in_array(auth()->user()->type, ['admin', 'branch_admin'])) {
        abort(403, 'Unauthorized');
    }
@endphp
@extends('layout')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">
            <a href="{{ url('/sourcing') }}" class="text-muted text-decoration-none">
                {{ $lang->write('Sourcing requests') }} ›
            </a>
            {{ $lang->write('Supplier reliability') }}
        </h1>
        <div class="page-subtitle">
            {{ $lang->write('On-time rate, avg lead time, cancellation rate per supplier — derived from purchase_orders + linked proforma items') }}
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        @if (count($rows) < 1)
            <p class="text-muted mb-0">
                {{ $lang->write('No POs with supplier_name on record. Link POs to proformas to populate this report.') }}
            </p>
        @else
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>{{ $lang->write('Supplier') }}</th>
                            <th class="text-end">{{ $lang->write('Total POs') }}</th>
                            <th class="text-end">{{ $lang->write('Delivered') }}</th>
                            <th class="text-end">{{ $lang->write('Avg lead (days)') }}</th>
                            <th class="text-end">{{ $lang->write('On-time rate') }}</th>
                            <th class="text-end">{{ $lang->write('Cancel rate') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($rows as $r)
                        <tr>
                            <td>
                                <strong>{{ $r->supplier_name }}</strong>
                            </td>
                            <td class="text-end">{{ $r->total_pos }}</td>
                            <td class="text-end">{{ $r->delivered_pos }}</td>
                            <td class="text-end">
                                @if ($r->avg_lead_days !== null)
                                    {{ round($r->avg_lead_days, 1) }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($r->on_time_rate !== null)
                                    <span class="fw-semibold {{ $r->on_time_rate >= 80 ? 'text-success' : ($r->on_time_rate < 50 ? 'text-danger' : 'text-warning') }}">
                                        {{ $r->on_time_rate }}%
                                    </span>
                                    <div class="small text-muted">{{ $r->on_time_items }}/{{ $r->on_time_total }}</div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <span class="fw-semibold {{ $r->cancel_rate >= 20 ? 'text-danger' : ($r->cancel_rate > 5 ? 'text-warning' : 'text-success') }}">
                                    {{ $r->cancel_rate }}%
                                </span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="small text-muted mt-3">
                {{ $lang->write('On-time rate counts items where supplier_confirmed_date ≤ promised_delivery_date. Cancel rate = (CANCELLED + RETURNED + REFUNDED) / total POs.') }}
            </div>
        @endif
    </div>
</div>

@endsection
