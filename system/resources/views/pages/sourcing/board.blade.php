@php
    if (!in_array(auth()->user()->type, ['admin', 'branch_admin'])) {
        abort(403, 'Unauthorized');
    }
    $columnMeta = [
        'open'      => ['label' => 'Open',         'color' => '#6b7280', 'bg' => '#f3f4f6'],
        'searching' => ['label' => 'Searching',    'color' => '#0891b2', 'bg' => '#ecfeff'],
        'quoted'    => ['label' => 'Quoted',       'color' => '#2563eb', 'bg' => '#eff6ff'],
        'accepted'  => ['label' => 'Accepted',     'color' => '#ea580c', 'bg' => '#fff7ed'],
        'fulfilled' => ['label' => 'Fulfilled',    'color' => '#16a34a', 'bg' => '#f0fdf4'],
    ];
    $statusOrder = ['open', 'searching', 'quoted'];
@endphp
@extends('layout')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">
            <a href="{{ url('/sourcing') }}" class="text-muted text-decoration-none">
                {{ $lang->write('Sourcing requests') }} ›
            </a>
            {{ $lang->write('Pipeline board') }}
        </h1>
        <div class="page-subtitle">
            {{ $lang->write('Every active proforma, grouped by funnel stage') }}
        </div>
    </div>
</div>

<form method="GET" class="row g-2 mb-3">
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
        <div class="col-auto align-self-end">
            <button class="btn btn-primary btn-sm">{{ $lang->write('Apply') }}</button>
        </div>
    @endif
</form>

<div class="row g-2" style="overflow-x:auto;flex-wrap:nowrap;">
    @foreach ($columnMeta as $statusKey => $meta)
        <div class="col" style="min-width:280px;max-width:320px;">
            <div class="card mb-2" style="background:{{ $meta['bg'] }};border-color:{{ $meta['color'] }};">
                <div class="card-body p-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-weight:600;color:{{ $meta['color'] }};">
                            {{ $lang->write($meta['label']) }}
                        </span>
                        <span class="badge" style="background:{{ $meta['color'] }};">
                            {{ count($columns[$statusKey] ?? []) }}
                        </span>
                    </div>
                </div>
            </div>

            <div style="display:flex;flex-direction:column;gap:6px;">
                @foreach ($columns[$statusKey] ?? [] as $card)
                    @php
                        $ccy = strtoupper($card->display_currency ?: $card->currency ?: 'usd');
                        $nextDue = $nextDueByReq[$card->id] ?? null;
                        $isOverdue = $nextDue && $nextDue < $today;
                    @endphp
                    <div class="card" style="border-left:3px solid {{ $meta['color'] }};">
                        <div class="card-body p-2">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <a href="{{ url('/sourcing/' . $card->id) }}" class="text-decoration-none">
                                    <code class="small">{{ $card->request_number }}</code>
                                </a>
                                @if ($card->client_view_count > 0 && $statusKey === 'quoted')
                                    <span class="badge bg-info" title="Client viewed">👁 {{ $card->client_view_count }}</span>
                                @endif
                            </div>
                            <div class="fw-semibold small">{{ $card->title }}</div>
                            <div class="small text-muted text-truncate">
                                @if ($card->client_code)<span class="text-muted">{{ $card->client_code }}</span> · @endif
                                {{ $card->client_name }}
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-1">
                                <strong class="small">
                                    {{ number_format((float) $card->proforma_total, 2) }}
                                    <span class="text-muted">{{ $ccy }}</span>
                                </strong>
                                @if ($nextDue)
                                    <span class="small {{ $isOverdue ? 'text-danger' : 'text-muted' }}">
                                        {{ $isOverdue ? '⏰' : '📅' }} {{ $nextDue }}
                                    </span>
                                @endif
                            </div>
                            @if (in_array($statusKey, $statusOrder, true))
                                <div class="mt-1 d-flex gap-1">
                                    @if ($statusKey !== 'open')
                                        @php $back = $statusOrder[max(0, array_search($statusKey, $statusOrder) - 1)]; @endphp
                                        <button class="btn btn-sm btn-light flex-fill" onclick="boardMove({{ $card->id }}, '{{ $back }}')" title="Move to {{ $back }}">←</button>
                                    @endif
                                    @if ($statusKey !== 'quoted')
                                        @php $next = $statusOrder[min(count($statusOrder)-1, array_search($statusKey, $statusOrder) + 1)]; @endphp
                                        <button class="btn btn-sm btn-light flex-fill" onclick="boardMove({{ $card->id }}, '{{ $next }}')" title="Move to {{ $next }}">→</button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach

                @if (count($columns[$statusKey] ?? []) < 1)
                    <div class="small text-muted text-center py-3">—</div>
                @endif
            </div>
        </div>
    @endforeach
</div>

<script>
function boardMove(id, status) {
    const fd = new FormData();
    fd.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
    fd.append('status', status);
    fetch('/sourcing/' + id + '/status', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.type === 'success' || res.type === 'noop') {
                window.location.reload();
            } else {
                alert(res.message || 'Failed');
            }
        })
        .catch(() => alert('Network error'));
}
</script>

@endsection
