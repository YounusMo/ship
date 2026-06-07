@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\langController;
    $lang = new langController();
    $dataController = new dataController();
    $currencies = $dataController->currencies;

    $badgeClass = [
        'open'      => 'bg-secondary',
        'searching' => 'bg-info',
        'quoted'    => 'bg-primary',
        'accepted'  => 'bg-warning',
        'fulfilled' => 'bg-success',
        'canceled'  => 'bg-dark',
    ];

    $suppliers = DB::table('suppliers')->where('deleted', 'false')->orderBy('name')->get();

    $isFinal = in_array($req->status, ['fulfilled', 'canceled'], true);
    $hasCommission = !empty($req->commission_journal_entry_id);
@endphp
@extends('layout')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">
            <a href="{{url('/sourcing')}}" class="text-muted text-decoration-none">
                {{ $lang->write('Sourcing requests') }} ›
            </a>
            <code>{{$req->request_number}}</code>
        </h1>
        <div class="page-subtitle">
            <span class="badge {{$badgeClass[$req->status] ?? 'bg-secondary'}} me-2">
                {{$lang->write('sourcing.status.' . $req->status)}}
            </span>
            {{$req->title}}
        </div>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary btn-sm" href="{{ url('/sourcing/' . $req->id . '/profit') }}">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;"><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/></svg>
            {{ $lang->write('Profit') }}
        </a>
        <a class="btn btn-outline-primary btn-sm" href="{{ url('/sourcing/' . $req->id . '/pdf') }}" target="_blank">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            {{ $lang->write('Download PDF') }}
        </a>
        @if (in_array($req->status, ['open','searching','quoted'], true))
            <button class="btn btn-primary btn-sm" onclick="sendProforma({{$req->id}})">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                {{ $req->sent_at ? $lang->write('Resend / refresh link') : $lang->write('Send to client') }}
            </button>
            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#emailProforma">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                {{ $lang->write('Email to client') }}
            </button>
        @endif
        @if (in_array($req->status, ['open','searching','quoted'], true))
            <button class="btn btn-success btn-sm" onclick="approveOnBehalf({{$req->id}})">
                {{ $lang->write('Approve on behalf') }}
            </button>
        @endif
        @if ($req->status === 'quoted' && $req->sent_at && $req->share_token)
            <button class="btn btn-outline-warning btn-sm" onclick="sendReminder({{$req->id}})">
                {{ $lang->write('Send reminder') }}
            </button>
        @endif
        <button class="btn btn-outline-secondary btn-sm" onclick="cloneProforma({{$req->id}})">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
            {{ $lang->write('Clone') }}
        </button>
        <button class="btn btn-outline-secondary btn-sm" onclick="mintPortalToken({{$req->client_id}})" title="{{ $lang->write('Get a long-lived link that shows ALL of this client\'s proformas.') }}">
            {{ $lang->write('Client portal link') }}
        </button>
        @if ($req->status !== 'canceled')
            <button class="btn btn-outline-danger btn-sm" onclick="cancelRequest({{$req->id}})">{{ $lang->write('Cancel request') }}</button>
        @endif
        @if ($req->status === 'accepted')
            <button class="btn btn-outline-success btn-sm" onclick="markFulfilled({{$req->id}})">{{ $lang->write('Mark fulfilled') }}</button>
        @endif
    </div>
</div>

{{-- Share link (if proforma has been sent) --}}
@if ($req->share_token)
    <div class="alert alert-info d-flex justify-content-between align-items-center mt-2">
        <div>
            <strong>{{ $lang->write('Client share link') }}:</strong>
            <code id="share_url_display">{{ url('/proforma/' . $req->share_token) }}</code>
            @if ($req->share_token_expires_at)
                <span class="text-muted small">· {{ $lang->write('expires') }} {{ substr($req->share_token_expires_at, 0, 10) }}</span>
            @endif
        </div>
        <div>
            <button class="btn btn-sm btn-outline-secondary" onclick="copyShareUrl()">{{ $lang->write('Copy') }}</button>
            <a class="btn btn-sm btn-outline-secondary" href="{{ url('/proforma/' . $req->share_token) }}" target="_blank">{{ $lang->write('Open') }}</a>
            @if (!in_array($req->status, ['canceled', 'fulfilled'], true))
                <button class="btn btn-sm btn-outline-danger" onclick="rotateToken({{ $req->id }})" title="{{ $lang->write('Old link will stop working — use when the link has been forwarded too widely.') }}">
                    {{ $lang->write('Rotate') }}
                </button>
            @endif
        </div>
    </div>
@endif

@if ($req->status === 'accepted' && $req->approved_via)
    <div class="alert alert-success mt-2">
        {{ $lang->write('Approved') }}
        @if ($req->approved_via === 'client_portal')
            {{ $lang->write('by client via share link') }}
        @elseif ($req->approved_via === 'on_behalf')
            {{ $lang->write('on behalf — internal user') }}
        @else
            {{ $lang->write('directly by admin') }}
        @endif
        @if ($req->approved_at)
            · {{ substr($req->approved_at, 0, 19) }}
        @endif
    </div>
@endif

<input type="hidden" class="sourcing_id" value="{{$req->id}}">

{{-- Workflow hints (Phase 6) — surface the next useful action based on
     the current state. Banners stack only when multiple apply. --}}
@php
    $hints = [];

    // 1) Sent but no client view yet (>=2 days)
    if ($req->status === 'quoted' && $req->sent_at && !$req->client_viewed_at
        && strtotime($req->sent_at) < strtotime('-2 days')) {
        $hints[] = [
            'class' => 'alert-warning',
            'icon'  => '⏰',
            'text'  => $lang->write('Sent') . ' ' . substr($req->sent_at, 0, 10)
                       . ' — ' . $lang->write('client has not opened the link yet. Consider sending a reminder.'),
            'action'=> ['label' => $lang->write('Send reminder'), 'onclick' => "sendReminder({$req->id})"],
        ];
    }

    // 2) Client viewed but no approval in >=3 days
    if ($req->status === 'quoted' && $req->client_viewed_at
        && strtotime($req->client_viewed_at) < strtotime('-3 days')) {
        $hints[] = [
            'class' => 'alert-info',
            'icon'  => '👀',
            'text'  => $lang->write('Client opened the link') . ' ' . substr($req->client_viewed_at, 0, 10)
                       . ' — ' . $lang->write('but has not approved yet. Send a follow-up nudge?'),
            'action'=> ['label' => $lang->write('Send reminder'), 'onclick' => "sendReminder({$req->id})"],
        ];
    }

    // 3) Accepted but no freight handoff
    if ($req->status === 'accepted' && !$req->freight_container_id) {
        $hints[] = [
            'class' => 'alert-primary',
            'icon'  => '🚢',
            'text'  => $lang->write('Approved — ready for freight handoff. Pick air or sea to create the container.'),
            'action'=> ['label' => $lang->write('Send to freight'), 'href' => url('/sourcing/' . $req->id . '/handoff/sky')],
        ];
    }

    // 4) Items all delivered but proforma not fulfilled
    if ($req->status === 'accepted' && count($items) > 0) {
        $allDelivered = true;
        foreach ($items as $it) {
            if (($it->delivery_status ?: 'pending') !== 'delivered') { $allDelivered = false; break; }
        }
        if ($allDelivered) {
            $hints[] = [
                'class' => 'alert-success',
                'icon'  => '✅',
                'text'  => $lang->write('All items delivered — ready to mark this proforma fulfilled. Revenue will be recognised on confirm.'),
                'action'=> ['label' => $lang->write('Mark fulfilled'), 'onclick' => "markFulfilled({$req->id})"],
            ];
        }
    }

    // 5) Overdue installments
    $overdueInstallments = collect($payments)->filter(function ($p) {
        return in_array($p->status, ['scheduled','partial'], true)
            && $p->due_date && $p->due_date < date('Y-m-d');
    });
    if ($overdueInstallments->count() > 0) {
        $sum = $overdueInstallments->sum(fn($p) => (float) $p->amount - (float) $p->paid_amount);
        $ccy = strtoupper($overdueInstallments->first()->currency);
        $hints[] = [
            'class' => 'alert-danger',
            'icon'  => '🔴',
            'text'  => $overdueInstallments->count() . ' ' . $lang->write('installment(s) overdue') . ' — '
                       . number_format($sum, 2) . ' ' . $ccy . ' ' . $lang->write('outstanding'),
            'action'=> null,
        ];
    }
@endphp
@foreach ($hints as $h)
    <div class="alert {{ $h['class'] }} d-flex justify-content-between align-items-center mt-2 mb-0">
        <div>
            <span style="font-size:1.1rem;">{{ $h['icon'] }}</span>
            {{ $h['text'] }}
        </div>
        @if (!empty($h['action']))
            @if (!empty($h['action']['href']))
                <a href="{{ $h['action']['href'] }}" class="btn btn-sm btn-light">{{ $h['action']['label'] }}</a>
            @else
                <button class="btn btn-sm btn-light" onclick="{{ $h['action']['onclick'] }}">{{ $h['action']['label'] }}</button>
            @endif
        @endif
    </div>
@endforeach

{{-- Pending change request banner --}}
@php
    $pendingChanges = collect($changeRequests ?? [])->where('status', 'pending');
@endphp
@if ($pendingChanges->count() > 0)
    <div class="alert alert-warning d-flex justify-content-between align-items-center mt-2 mb-0">
        <div>
            <strong>📝 {{ $lang->write('Client requested changes') }}</strong>
            ·
            {{ substr($pendingChanges->first()->created_at, 0, 16) }}
        </div>
        <button class="btn btn-sm btn-light" onclick="document.getElementById('changeRequestsCard').scrollIntoView({behavior:'smooth'})">
            {{ $lang->write('Review below') }}
        </button>
    </div>
@endif

{{-- Smart insights (Phase 14) — composite deal health + client patterns --}}
@if (isset($dealHealth))
    @php
        $score = $dealHealth['score'];
        $healthColor = $score >= 70 ? 'success' : ($score >= 40 ? 'warning' : 'danger');
        $healthLabel = $score >= 70 ? 'Healthy' : ($score >= 40 ? 'Watch' : 'At risk');
    @endphp
    <div class="row g-3 mb-3">
        <div class="col-12 col-md-5">
            <div class="card border-{{ $healthColor }}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">{{ $lang->write('Deal health') }}</div>
                            <div class="h2 mb-0 text-{{ $healthColor }}">{{ $score }}/100</div>
                            <span class="badge bg-{{ $healthColor }}">{{ $lang->write($healthLabel) }}</span>
                        </div>
                        <button class="btn btn-sm btn-outline-secondary" type="button"
                                data-bs-toggle="collapse" data-bs-target="#healthFactors">
                            {{ $lang->write('Breakdown') }}
                        </button>
                    </div>
                    <div class="collapse mt-3" id="healthFactors">
                        <table class="table table-sm mb-0">
                            <tbody>
                                @foreach ($dealHealth['factors'] as $f)
                                    <tr>
                                        <td class="small">{{ $f['label'] }}</td>
                                        <td class="small text-muted">{{ $f['note'] }}</td>
                                        <td class="text-end small fw-semibold">+{{ $f['points'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    {{-- Phase 15 — last-14d health trend mini-chart. Loads
                         async so the show page stays fast. --}}
                    <div class="mt-3" id="healthTrendWrap" data-proforma-id="{{ $req->id }}" style="display:none">
                        <div class="text-muted small mb-1">{{ $lang->write('14-day trend') }}</div>
                        <svg id="healthTrendSvg" viewBox="0 0 280 60" width="100%" height="60" preserveAspectRatio="none"
                             style="display:block;background:#f8f9fa;border-radius:4px"></svg>
                        <div class="small text-muted mt-1" id="healthTrendCaption"></div>
                    </div>
                </div>
            </div>
        </div>
        @if (!empty($clientPatterns) && $clientPatterns['total_proformas'] > 1)
            <div class="col-12 col-md-7">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="text-muted small">{{ $lang->write('Client pattern') }}</div>
                                <div class="h6 mb-0">
                                    {{ $client->name ?? '' }} —
                                    {{ $clientPatterns['total_proformas'] }} {{ $lang->write('proformas to date') }}
                                </div>
                            </div>
                            @if ($clientPatterns['most_common_action'])
                                <span class="badge bg-info">
                                    {{ $lang->write('Often negotiates') }}: {{ $clientPatterns['most_common_action'] }}
                                </span>
                            @endif
                        </div>
                        <div class="row g-2 small">
                            <div class="col-4">
                                <div class="text-muted">{{ $lang->write('Change requests') }}</div>
                                <div class="fw-semibold">{{ $clientPatterns['total_change_requests'] }}</div>
                            </div>
                            <div class="col-4">
                                <div class="text-muted">{{ $lang->write('Avg response') }}</div>
                                <div class="fw-semibold">
                                    @if ($clientPatterns['avg_response_hours'] !== null)
                                        {{ $clientPatterns['avg_response_hours'] }}h
                                    @else
                                        —
                                    @endif
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="text-muted">{{ $lang->write('Negotiated on') }}</div>
                                <div class="small">
                                    @if ($clientPatterns['qty_changes_pct'] > 0)
                                        {{ $lang->write('Qty') }} {{ $clientPatterns['qty_changes_pct'] }}%
                                    @endif
                                    @if ($clientPatterns['price_changes_pct'] > 0)
                                        · {{ $lang->write('Price') }} {{ $clientPatterns['price_changes_pct'] }}%
                                    @endif
                                    @if ($clientPatterns['terms_changes_pct'] > 0)
                                        · {{ $lang->write('Terms') }} {{ $clientPatterns['terms_changes_pct'] }}%
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endif

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-3">{{$lang->write('Details')}}</h5>
                <dl class="row mb-0">
                    <dt class="col-sm-4">{{$lang->write('Client')}}</dt>
                    <dd class="col-sm-8">{{$client->code ?? ''}} — {{$client->name ?? ''}}</dd>

                    <dt class="col-sm-4">{{$lang->write('Branch')}}</dt>
                    <dd class="col-sm-8">{{$branch->name ?? '—'}}</dd>

                    <dt class="col-sm-4">{{$lang->write('Description')}}</dt>
                    <dd class="col-sm-8">{!! $req->description ? nl2br(e($req->description)) : '<span class="text-muted">—</span>' !!}</dd>

                    <dt class="col-sm-4">{{$lang->write('Target')}}</dt>
                    <dd class="col-sm-8">
                        @if ($req->target_quantity || $req->target_unit_price)
                            {{ $req->target_quantity ?? '—' }} {{ $req->target_unit ?? '' }}
                            @if ($req->target_unit_price)
                                · {{ number_format((float) $req->target_unit_price, 2) }} {{ strtoupper($req->currency) }} / {{ $lang->write('unit') }}
                            @endif
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </dd>

                    <dt class="col-sm-4">{{$lang->write('Created at')}}</dt>
                    <dd class="col-sm-8">{{substr($req->created_at, 0, 19)}}</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-3">{{$lang->write('Commission')}}</h5>
                @if ($hasCommission)
                    <div class="alert alert-success mb-2">
                        <div class="fw-semibold">
                            {{ number_format((float) $req->commission_amount, 2) }}
                            {{ strtoupper($req->commission_currency) }}
                        </div>
                        <small class="text-muted">
                            {{$lang->write('Posted')}} {{ substr($req->commission_posted_at, 0, 19) }}
                            · {{$lang->write('Journal entry')}} #{{ $req->commission_journal_entry_id }}
                        </small>
                    </div>
                    @if ($journalEntry)
                        <div class="small text-muted">
                            <div>Dr 1100 AR clients · Cr 4020 {{$lang->write('Sourcing commission revenue')}}</div>
                        </div>
                    @endif
                @else
                    <p class="text-muted small mb-0">
                        {{ $lang->write('Commission posts to CoA 4020 when you accept a quote below.') }}
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ========================================================
     PROFORMA — items, payment plan, settings.
     Becomes the centrepiece of the page once Phase 2 ships
     (PDF + share link + on-behalf approval).
     ======================================================== --}}
@php
    $editable        = !in_array($req->status, ['accepted','fulfilled','canceled'], true);
    $displayCurrency = strtoupper($req->display_currency ?: $req->currency ?: 'usd');
@endphp

{{-- Proforma settings --}}
<div class="card mt-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="card-title mb-0">{{$lang->write('Proforma settings')}}</h5>
            @if ($editable)
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#proformaSettings">
                    {{ $lang->write('Edit settings') }}
                </button>
            @endif
        </div>
        <div class="row g-3">
            <div class="col-6 col-md-3">
                <div class="text-muted small">{{$lang->write('Display currency')}}</div>
                <div class="h5 mb-0">{{ $displayCurrency }}</div>
                @if ($req->fx_frozen_on)
                    <div class="small text-muted">{{$lang->write('FX frozen on')}} {{ $req->fx_frozen_on }}</div>
                @endif
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted small">{{$lang->write('Commission mode')}}</div>
                <div class="h6 mb-0">
                    @if ($req->commission_mode === 'visible_separate')
                        <span class="badge bg-info">{{$lang->write('Visible to client')}}</span>
                    @else
                        <span class="badge bg-secondary">{{$lang->write('Hidden in prices')}}</span>
                    @endif
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted small">{{$lang->write('Items subtotal')}}</div>
                <div class="h5 mb-0">{{ number_format((float) $req->items_subtotal, 2) }} <span class="text-muted">{{ $displayCurrency }}</span></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted small">{{$lang->write('Proforma total')}}</div>
                <div class="h4 mb-0 text-success">{{ number_format((float) $req->proforma_total, 2) }} <span class="text-muted">{{ $displayCurrency }}</span></div>
            </div>
        </div>
        @if (!empty($req->terms_text))
            <hr>
            <div class="small text-muted">{{ $lang->write('Terms & notes') }}</div>
            <div style="white-space:pre-wrap;">{{ $req->terms_text }}</div>
        @endif
    </div>
</div>

{{-- Line items --}}
<div class="card mt-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="card-title mb-0">
                {{$lang->write('Items')}}
                <span class="text-muted small">({{ count($items) }})</span>
            </h5>
            @if ($editable)
                <div class="d-flex gap-2">
                    @if (count($items) > 0)
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#markupModal">
                            % {{ $lang->write('Apply markup') }}
                        </button>
                    @endif
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addItem">
                        {{ $lang->write('Add item') }}
                    </button>
                </div>
            @endif
        </div>

        @if (count($items) < 1)
            <p class="text-muted mb-0">{{$lang->write('No items yet. Add the first product to build the proforma.')}}</p>
        @else
            @php
                // Aggregate item statuses for the proforma-level rollup.
                $statusBuckets = ['pending'=>0,'sourced'=>0,'in_production'=>0,'shipped'=>0,'delivered'=>0];
                foreach ($items as $it) {
                    $key = $it->delivery_status ?: 'pending';
                    if (isset($statusBuckets[$key])) $statusBuckets[$key]++;
                }
                $totalItems = count($items);
                $deliveredPct = $totalItems > 0 ? round(100 * $statusBuckets['delivered'] / $totalItems) : 0;
            @endphp
            @if ($totalItems > 0)
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small text-muted">{{ $lang->write('Delivery progress') }}</span>
                        <span class="small">
                            {{ $statusBuckets['delivered'] }} / {{ $totalItems }} {{ $lang->write('delivered') }}
                            ({{ $deliveredPct }}%)
                        </span>
                    </div>
                    <div class="progress" style="height:6px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: {{ $deliveredPct }}%"></div>
                    </div>
                    <div class="small text-muted mt-1">
                        @foreach ($statusBuckets as $st => $cnt)
                            @if ($cnt > 0)
                                <span class="badge bg-light text-dark border me-1">{{ $cnt }} {{ $lang->write('item.status.' . $st) }}</span>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th style="width:64px;"></th>
                            <th>{{$lang->write('Item')}}</th>
                            <th>{{$lang->write('Delivery')}}</th>
                            <th class="text-end">{{$lang->write('Quantity')}}</th>
                            <th class="text-end">{{$lang->write('Unit cost')}}</th>
                            <th class="text-end">{{$lang->write('Unit price')}}</th>
                            <th class="text-end">{{$lang->write('Weight (KG)')}}</th>
                            <th class="text-end">{{$lang->write('Line total')}}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($items as $it)
                        @php
                            $primary = ($photos[$it->id] ?? null) ? $photos[$it->id][0] : null;
                            $lineTotal = (float) $it->quantity * (float) $it->unit_price_to_client;
                            $margin    = ((float) $it->unit_price_to_client - (float) $it->unit_cost) * (float) $it->quantity;
                        @endphp
                        <tr>
                            <td>
                                @if ($primary)
                                    <a href="{{ asset('storage/' . $primary->path) }}" target="_blank">
                                        <img src="{{ asset('storage/' . $primary->path) }}"
                                             style="width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid var(--color-border);">
                                    </a>
                                @else
                                    <div style="width:48px;height:48px;border-radius:6px;background:var(--color-surface);border:1px dashed var(--color-border);display:flex;align-items:center;justify-content:center;color:var(--color-text-muted);">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-4-4-10 10"/></svg>
                                    </div>
                                @endif
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $it->name }}</div>
                                @if ($it->code)
                                    <div class="small text-muted">{{ $it->code }}</div>
                                @endif
                                @if ($it->description)
                                    <div class="small text-muted text-truncate" style="max-width:300px;">{{ $it->description }}</div>
                                @endif
                                @php $photoCount = isset($photos[$it->id]) ? count($photos[$it->id]) : 0; @endphp
                                @if ($photoCount > 0)
                                    <div class="small text-muted">
                                        <a href="javascript:void(0)" onclick="openGallery({{ $it->id }})">
                                            {{ $photoCount }} {{ $lang->write('photo(s)') }}
                                        </a>
                                    </div>
                                @endif
                            </td>
                            <td>
                                @php
                                    $itemStatusBadge = [
                                        'pending'       => 'bg-secondary',
                                        'sourced'       => 'bg-info',
                                        'in_production' => 'bg-primary',
                                        'shipped'       => 'bg-warning',
                                        'delivered'     => 'bg-success',
                                    ][$it->delivery_status ?: 'pending'];
                                    // Slip = supplier_confirmed - promised. Positive = supplier
                                    // is slipping. Negative = early. Null when either date missing.
                                    $promised  = $it->promised_delivery_date;
                                    $confirmed = $it->supplier_confirmed_date;
                                    $slipDays = null;
                                    if ($promised && $confirmed) {
                                        $slipDays = (strtotime($confirmed) - strtotime($promised)) / 86400;
                                    } elseif ($promised && !$confirmed && in_array($it->delivery_status, ['pending','sourced','in_production'], true)) {
                                        // No supplier confirmation yet AND past promised date.
                                        $diff = (time() - strtotime($promised)) / 86400;
                                        if ($diff > 0) $slipDays = $diff;
                                    }
                                @endphp
                                <select class="form-select form-select-sm item-status-picker" style="width:auto;display:inline-block;" data-item-id="{{ $it->id }}">
                                    @foreach (['pending','sourced','in_production','shipped','delivered'] as $st)
                                        <option value="{{ $st }}" {{ ($it->delivery_status ?: 'pending') === $st ? 'selected' : '' }}>{{ $lang->write('item.status.' . $st) }}</option>
                                    @endforeach
                                </select>
                                <div class="mt-1 small">
                                    <div class="d-flex gap-1 align-items-center">
                                        <span class="text-muted" style="font-size:11px;">{{ $lang->write('Promised') }}</span>
                                        <input type="date" class="form-control form-control-sm item-date-picker"
                                               data-item-id="{{ $it->id }}" data-field="promised_delivery_date"
                                               value="{{ $promised ?? '' }}"
                                               style="width:130px;font-size:11px;padding:1px 4px;">
                                    </div>
                                    <div class="d-flex gap-1 align-items-center mt-1">
                                        <span class="text-muted" style="font-size:11px;">{{ $lang->write('Supplier') }}</span>
                                        <input type="date" class="form-control form-control-sm item-date-picker"
                                               data-item-id="{{ $it->id }}" data-field="supplier_confirmed_date"
                                               value="{{ $confirmed ?? '' }}"
                                               style="width:130px;font-size:11px;padding:1px 4px;">
                                    </div>
                                    @if ($slipDays !== null && abs($slipDays) >= 0.5)
                                        <div class="small {{ $slipDays > 0 ? 'text-danger fw-semibold' : 'text-success' }}">
                                            {{ $slipDays > 0 ? '⚠ ' : '✓ ' }}{{ $slipDays > 0 ? '+' : '' }}{{ round($slipDays) }}{{ $lang->write('d') }}
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td class="text-end">{{ rtrim(rtrim(number_format((float) $it->quantity, 4, '.', ''), '0'), '.') }} <span class="text-muted small">{{ $it->unit }}</span></td>
                            <td class="text-end">
                                {{ number_format((float) $it->unit_cost, 2) }}
                                <span class="text-muted small">{{ strtoupper($it->unit_cost_currency) }}</span>
                            </td>
                            <td class="text-end">{{ number_format((float) $it->unit_price_to_client, 2) }}</td>
                            <td class="text-end">{{ $it->weight_kg !== null ? number_format((float) $it->weight_kg, 2) : '—' }}</td>
                            <td class="text-end">
                                {{ number_format($lineTotal, 2) }}
                                <div class="small text-muted">{{$lang->write('Margin')}} {{ number_format($margin, 2) }}</div>
                            </td>
                            <td class="text-end">
                                @if ($editable)
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-secondary"
                                                onclick='editItem(@json($it))'>{{$lang->write('Edit')}}</button>
                                        <button class="btn btn-outline-secondary" onclick="openPhotoUpload({{ $it->id }})">{{$lang->write('Photos')}}</button>
                                        <button class="btn btn-outline-danger" onclick="deleteItem({{ $it->id }})">{{$lang->write('Delete')}}</button>
                                    </div>
                                @else
                                    <button class="btn btn-sm btn-outline-secondary" onclick="openGallery({{ $it->id }})">{{$lang->write('Photos')}}</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

{{-- Payment schedule --}}
<div class="card mt-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="card-title mb-0">
                {{$lang->write('Payment plan')}}
                @if ($req->payment_plan)
                    <span class="badge bg-secondary ms-2">{{ strtoupper(str_replace('_','/',$req->payment_plan)) }}</span>
                @endif
            </h5>
            @if ($editable)
                <div class="d-flex gap-2">
                    <select class="form-select form-select-sm" id="paymentPlanPicker" style="width:auto;">
                        <option value="100"      {{ $req->payment_plan === '100'      ? 'selected' : '' }}>100% upfront</option>
                        <option value="50_50"   {{ $req->payment_plan === '50_50'   ? 'selected' : '' }}>50 / 50</option>
                        <option value="30_50_20"{{ $req->payment_plan === '30_50_20'? 'selected' : '' }}>30 / 50 / 20</option>
                        <option value="30_30_40"{{ $req->payment_plan === '30_30_40'? 'selected' : '' }}>30 / 30 / 40</option>
                        <option value="custom"   {{ $req->payment_plan === 'custom'   ? 'selected' : '' }}>{{$lang->write('Custom')}}</option>
                    </select>
                    <button class="btn btn-sm btn-primary" onclick="generatePaymentPlan()">{{$lang->write('Regenerate plan')}}</button>
                </div>
            @endif
        </div>

        @if (count($payments) < 1)
            <p class="text-muted mb-0">{{$lang->write('No installments yet. Pick a plan above to generate the schedule.')}}</p>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th style="width:48px;">#</th>
                            <th>{{$lang->write('Installment')}}</th>
                            <th class="text-end">%</th>
                            <th class="text-end">{{$lang->write('Amount')}}</th>
                            <th>{{$lang->write('Due date')}}</th>
                            <th>{{$lang->write('Status')}}</th>
                            <th class="text-end">{{$lang->write('Paid')}}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($payments as $p)
                        @php
                            $payBadge = [
                                'scheduled' => 'bg-secondary',
                                'partial'   => 'bg-warning',
                                'paid'      => 'bg-success',
                                'canceled'  => 'bg-dark',
                            ][$p->status] ?? 'bg-secondary';
                        @endphp
                        <tr>
                            <td class="text-muted">{{ $p->sequence }}</td>
                            <td>{{ $p->label }}</td>
                            <td class="text-end">{{ rtrim(rtrim(number_format((float) $p->percentage, 4, '.', ''), '0'), '.') }}</td>
                            <td class="text-end">
                                {{ number_format((float) $p->amount, 2) }}
                                <span class="text-muted small">{{ strtoupper($p->currency) }}</span>
                            </td>
                            <td>{{ $p->due_date ?? '—' }}</td>
                            <td><span class="badge {{ $payBadge }}">{{$lang->write('payment.status.' . $p->status)}}</span></td>
                            <td class="text-end">
                                {{ number_format((float) $p->paid_amount, 2) }}
                            </td>
                            <td class="text-end">
                                @if (in_array($p->status, ['scheduled','partial'], true))
                                    <button class="btn btn-sm btn-success" onclick='markPaid(@json($p))'>{{$lang->write('Mark paid')}}</button>
                                @endif
                                @if ($editable && $p->status === 'scheduled')
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-secondary" onclick='editPayment(@json($p))'>{{$lang->write('Edit')}}</button>
                                        <button class="btn btn-outline-danger"    onclick="deletePayment({{ $p->id }})">{{$lang->write('Delete')}}</button>
                                    </div>
                                @endif
                                @if ($p->settled_by_transaction_id)
                                    <div class="small text-muted">{{ $lang->write('Txn') }} #{{ $p->settled_by_transaction_id }}</div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if ($editable)
                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addPayment">
                    + {{$lang->write('Add installment')}}
                </button>
            @endif
        @endif
    </div>
</div>

{{-- Change requests (Phase 7) --}}
@if (isset($changeRequests) && count($changeRequests) > 0)
    @php
        $crStatusBadge = [
            'pending'    => 'bg-warning',
            'responded'  => 'bg-success',
            'dismissed'  => 'bg-secondary',
            'superseded' => 'bg-dark',
        ];
    @endphp
    <div class="card mt-4" id="changeRequestsCard">
        <div class="card-body">
            <h5 class="card-title mb-3">
                {{ $lang->write('Change requests from client') }}
                <span class="text-muted small">({{ count($changeRequests) }})</span>
            </h5>
            @foreach ($changeRequests as $cr)
                <div class="border rounded p-3 mb-2 {{ $cr->status === 'pending' ? 'border-warning bg-light' : '' }}">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <span class="badge {{ $crStatusBadge[$cr->status] ?? 'bg-secondary' }}">
                                {{ $lang->write('change.status.' . $cr->status) }}
                            </span>
                            <span class="small text-muted">
                                {{ $lang->write('Submitted') }} {{ substr($cr->created_at, 0, 19) }}
                                @if ($cr->client_ip)
                                    · IP {{ $cr->client_ip }}
                                @endif
                            </span>
                        </div>
                        @if ($cr->status === 'pending')
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-success"
                                        onclick="respondChangeRequest({{ $cr->id }}, 'responded')">
                                    {{ $lang->write('Mark responded') }}
                                </button>
                                <button class="btn btn-outline-secondary"
                                        onclick="respondChangeRequest({{ $cr->id }}, 'dismissed')">
                                    {{ $lang->write('Dismiss') }}
                                </button>
                            </div>
                        @endif
                    </div>
                    <div class="small text-muted mb-1">{{ $lang->write('Client said:') }}</div>
                    <div style="white-space:pre-wrap;">{{ $cr->comment }}</div>

                    @php
                        $suggested = $cr->suggested_changes ? (json_decode($cr->suggested_changes, true) ?: []) : [];
                        $suggestedById = [];
                        foreach ($suggested as $sg) {
                            if (isset($sg['item_id'])) $suggestedById[(int) $sg['item_id']] = $sg;
                        }
                    @endphp
                    @if (count($suggestedById) > 0)
                        <div class="small text-muted mt-3 mb-1">{{ $lang->write('Suggested item changes:') }}</div>
                        <table class="table table-sm align-middle mb-0" style="background:#fff;">
                            <thead>
                                <tr>
                                    <th>{{ $lang->write('Item') }}</th>
                                    <th class="text-end">{{ $lang->write('Current qty') }}</th>
                                    <th class="text-end">{{ $lang->write('Suggested qty') }}</th>
                                    <th class="text-end">{{ $lang->write('Current unit price') }}</th>
                                    <th class="text-end">{{ $lang->write('Suggested unit price') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach ($items as $it)
                                @php $sg = $suggestedById[$it->id] ?? null; @endphp
                                @if (!$sg) @continue @endif
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $it->name }}</div>
                                        @if ($it->code)<div class="small text-muted">{{ $it->code }}</div>@endif
                                    </td>
                                    <td class="text-end">
                                        {{ rtrim(rtrim(number_format((float) $it->quantity, 4, '.', ''), '0'), '.') }}
                                    </td>
                                    <td class="text-end">
                                        @if (isset($sg['qty']))
                                            <strong class="{{ (float) $sg['qty'] < (float) $it->quantity ? 'text-warning' : 'text-info' }}">
                                                {{ rtrim(rtrim(number_format((float) $sg['qty'], 4, '.', ''), '0'), '.') }}
                                            </strong>
                                        @else — @endif
                                    </td>
                                    <td class="text-end">{{ number_format((float) $it->unit_price_to_client, 2) }}</td>
                                    <td class="text-end">
                                        @if (isset($sg['unit_price_to_client']))
                                            <strong class="{{ (float) $sg['unit_price_to_client'] < (float) $it->unit_price_to_client ? 'text-warning' : 'text-info' }}">
                                                {{ number_format((float) $sg['unit_price_to_client'], 2) }}
                                            </strong>
                                        @else — @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                    @if ($cr->reply_to_email)
                        <div class="small text-muted mt-2">
                            {{ $lang->write('Reply to') }}: <code>{{ $cr->reply_to_email }}</code>
                        </div>
                    @endif
                    @if ($cr->status !== 'pending' && ($cr->response || $cr->responded_at))
                        <hr>
                        <div class="small text-muted mb-1">
                            {{ $lang->write('Our response') }}
                            @if ($cr->responded_at)
                                · {{ substr($cr->responded_at, 0, 19) }}
                            @endif
                            @if ($cr->responded_by_name)
                                · {{ $cr->responded_by_name }}
                            @endif
                        </div>
                        @if ($cr->response)
                            <div style="white-space:pre-wrap;">{{ $cr->response }}</div>
                        @endif
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- Respond modal (single, reused for any change request) --}}
    <div class="modal fade" id="respondCRModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $lang->write('Respond to change request') }}</h5>
                    <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.6" /></svg>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="cr_response_id">
                    <input type="hidden" id="cr_response_status">
                    <div class="alert alert-light" id="cr_response_summary"></div>
                    <div class="mb-3">
                        <label class="form-label">{{ $lang->write('Your response (optional)') }}</label>
                        <textarea class="form-control" id="cr_response_text" rows="4" maxlength="5000" placeholder="{{ $lang->write('What changed, or why we declined') }}"></textarea>
                        <small class="text-muted">
                            {{ $lang->write('This text is stored for your records. Email the client separately if you want them to see it.') }}
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ $lang->write('Close') }}</button>
                    <button type="button" class="btn btn-primary" onclick="submitCRResponse()">{{ $lang->write('Save') }}</button>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- Version history (Phase 13) --}}
@php
    $triggerBadge = [
        'manual'           => 'bg-secondary',
        'sent'             => 'bg-primary',
        'approved'         => 'bg-success',
        'fulfilled'        => 'bg-info',
        'markup_applied'   => 'bg-warning',
        'plan_regenerated' => 'bg-warning',
        'cloned_from'      => 'bg-secondary',
    ];
@endphp
<div class="card mt-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="card-title mb-0">
                {{ $lang->write('Version history') }}
                <span class="text-muted small">({{ count($versions ?? []) }})</span>
            </h5>
            <div class="d-flex gap-2">
                @if (count($versions ?? []) >= 1)
                    <a href="{{ url('/sourcing/' . $req->id . '/diff') }}" class="btn btn-sm btn-outline-secondary">
                        ⇄ {{ $lang->write('Compare versions') }}
                    </a>
                @endif
                <button class="btn btn-sm btn-primary" onclick="snapshotNow({{ $req->id }})">
                    📷 {{ $lang->write('Snapshot now') }}
                </button>
            </div>
        </div>
        @if (count($versions ?? []) < 1)
            <p class="text-muted mb-0">
                {{ $lang->write('No versions yet. A snapshot is automatically captured when the proforma is sent, approved, or fulfilled.') }}
            </p>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th style="width:60px;">v#</th>
                            <th>{{ $lang->write('Trigger') }}</th>
                            <th>{{ $lang->write('Captured') }}</th>
                            <th>{{ $lang->write('By') }}</th>
                            <th class="text-end">{{ $lang->write('Items') }}</th>
                            <th class="text-end">{{ $lang->write('Total') }}</th>
                            <th>{{ $lang->write('Status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($versions as $v)
                        <tr>
                            <td><strong>v{{ $v->version_no }}</strong></td>
                            <td>
                                <span class="badge {{ $triggerBadge[$v->trigger] ?? 'bg-secondary' }}">
                                    {{ str_replace('_', ' ', $v->trigger) }}
                                </span>
                                @if ($v->label)
                                    <div class="small text-muted">{{ $v->label }}</div>
                                @endif
                            </td>
                            <td class="small text-muted">{{ substr($v->created_at, 0, 19) }}</td>
                            <td class="small text-muted">{{ $v->created_by_name ?? '—' }}</td>
                            <td class="text-end">{{ $v->item_count_at_snapshot }}</td>
                            <td class="text-end">
                                {{ number_format((float) $v->total_at_snapshot, 2) }}
                                <span class="text-muted small">{{ strtoupper($v->currency_at_snapshot ?? '') }}</span>
                            </td>
                            <td><span class="badge bg-light text-dark border">{{ $v->status_at_snapshot }}</span></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-secondary" href="{{ url('/sourcing/' . $req->id . '/versions/' . $v->version_no) }}" target="_blank">
                                    {{ $lang->write('PDF') }}
                                </a>
                                <a class="btn btn-sm btn-outline-primary" href="{{ url('/sourcing/' . $req->id . '/diff?a=' . $v->version_no . '&b=live') }}">
                                    {{ $lang->write('Diff vs now') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

{{-- Linked Purchase Orders (Phase 12) --}}
@php
    // Friendly badges for the rich PO status enum.
    $poStatusBadge = [
        'PENDING_CONFIRMATION' => 'bg-secondary',
        'CONFIRMED'            => 'bg-info',
        'PURCHASING'           => 'bg-primary',
        'PURCHASED'            => 'bg-primary',
        'RECEIVED_WAREHOUSE'   => 'bg-warning',
        'IN_SHIPMENT'          => 'bg-warning',
        'DELIVERED'            => 'bg-success',
        'CANCELLED'            => 'bg-dark',
        'RETURNED'             => 'bg-dark',
        'REFUNDED'             => 'bg-dark',
        'ON_HOLD'              => 'bg-secondary',
    ];
@endphp
<div class="card mt-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="card-title mb-0">
                {{ $lang->write('Linked purchase orders') }}
                <span class="text-muted small">({{ count($linkedPOs ?? []) }})</span>
            </h5>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#poLinkModal">
                + {{ $lang->write('Link PO') }}
            </button>
        </div>
        @if (count($linkedPOs ?? []) < 1)
            <p class="text-muted mb-0">
                {{ $lang->write('No POs linked yet. Link a Purchase Order so supplier-side status flows through to this proforma.') }}
            </p>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>{{ $lang->write('PO number') }}</th>
                            <th>{{ $lang->write('Supplier') }}</th>
                            <th>{{ $lang->write('Status') }}</th>
                            <th class="text-end">{{ $lang->write('Value (USD)') }}</th>
                            <th>{{ $lang->write('Note') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($linkedPOs as $po)
                        <tr>
                            <td>
                                <code>{{ $po->order_number ?? '—' }}</code>
                                @if ($po->purchasing_started_at)
                                    <div class="small text-muted">{{ $lang->write('Started') }} {{ substr($po->purchasing_started_at, 0, 10) }}</div>
                                @endif
                            </td>
                            <td>{{ $po->supplier_name ?: '—' }}</td>
                            <td>
                                <span class="badge {{ $poStatusBadge[$po->status] ?? 'bg-secondary' }}">
                                    {{ str_replace('_', ' ', $po->status ?? 'unknown') }}
                                </span>
                                @if ($po->delivered_at)
                                    <div class="small text-success">✓ {{ substr($po->delivered_at, 0, 10) }}</div>
                                @elseif ($po->shipped_at)
                                    <div class="small text-warning">📦 {{ substr($po->shipped_at, 0, 10) }}</div>
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($po->actual_total_usd)
                                    {{ number_format((float) $po->actual_total_usd, 2) }}
                                @elseif ($po->estimated_total_usd)
                                    <span class="text-muted">~ {{ number_format((float) $po->estimated_total_usd, 2) }}</span>
                                @else — @endif
                            </td>
                            <td class="small text-muted">{{ $po->note ?: '—' }}</td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-danger" onclick="unlinkPO({{ $po->link_id }})">
                                    {{ $lang->write('Unlink') }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

{{-- PO link modal --}}
<div class="modal fade" id="poLinkModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ $lang->write('Link a purchase order') }}</h5>
        <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.6" /></svg>
        </button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="po_link_client_id" value="{{ $req->client_id }}">
        <input type="hidden" id="po_link_selected_id" value="">
        <div class="mb-2">
          <input type="text" class="form-control form-control-sm" id="po_search_input" placeholder="{{ $lang->write('Search POs by number or supplier…') }}">
        </div>
        <div id="po_search_results" style="max-height:260px;overflow-y:auto;border:1px solid var(--color-border, #e5e7eb);border-radius:6px;"></div>
        <div class="mt-3">
          <label class="form-label small">{{ $lang->write('Note (optional)') }}</label>
          <input type="text" class="form-control form-control-sm" id="po_link_note" maxlength="500" placeholder="{{ $lang->write('e.g. PO covers items 1–3 only') }}">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ $lang->write('Close') }}</button>
        <button type="button" class="btn btn-primary" onclick="submitPOLink()">{{ $lang->write('Link selected') }}</button>
      </div>
    </div>
  </div>
</div>

{{-- Documents (Phase 6) --}}
<div class="card mt-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="card-title mb-0">
                {{$lang->write('Documents')}}
                <span class="text-muted small">({{ count($documents) }})</span>
            </h5>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#docUpload">
                {{$lang->write('Upload document')}}
            </button>
        </div>
        @if (count($documents) < 1)
            <p class="text-muted mb-0">
                {{$lang->write('No documents yet. Attach contracts, certificates, packing lists, customs paperwork — anything that should travel with this proforma.')}}
            </p>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>{{$lang->write('File')}}</th>
                            <th>{{$lang->write('Label')}}</th>
                            <th>{{$lang->write('Visibility')}}</th>
                            <th class="text-end">{{$lang->write('Size')}}</th>
                            <th>{{$lang->write('Uploaded')}}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($documents as $d)
                        <tr>
                            <td>
                                <a href="{{ asset('storage/' . $d->path) }}" target="_blank" class="text-decoration-none">
                                    📄 {{ $d->original_name ?: basename($d->path) }}
                                </a>
                                <div class="small text-muted">{{ $d->mime }}</div>
                            </td>
                            <td>{{ $d->label ?? '—' }}</td>
                            <td>
                                <select class="form-select form-select-sm doc-visibility-picker" data-doc-id="{{ $d->id }}" style="width:auto;">
                                    <option value="internal"       {{ $d->visibility === 'internal'       ? 'selected' : '' }}>{{$lang->write('Internal only')}}</option>
                                    <option value="client_visible" {{ $d->visibility === 'client_visible' ? 'selected' : '' }}>{{$lang->write('Visible to client')}}</option>
                                </select>
                            </td>
                            <td class="text-end small text-muted">
                                @if ($d->size_bytes)
                                    {{ number_format($d->size_bytes / 1024, 1) }} KB
                                @else — @endif
                            </td>
                            <td class="small text-muted">{{ substr($d->created_at, 0, 16) }}</td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteDocument({{ $d->id }})">{{$lang->write('Delete')}}</button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

{{-- Upload document modal --}}
<div class="modal fade" id="docUpload" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{$lang->write('Upload document')}}</h5>
        <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.6" /></svg>
        </button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">{{$lang->write('Files')}} <small class="text-muted">({{$lang->write('PDF / image / Office, up to 20 MB each, max 10 at once')}})</small></label>
          <input type="file" class="form-control" id="doc_files" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx" multiple>
        </div>
        <div class="mb-3">
          <label class="form-label">{{$lang->write('Label')}} ({{$lang->write('optional')}})</label>
          <input type="text" class="form-control" id="doc_label" maxlength="191" placeholder="{{$lang->write('e.g. Signed contract / Certificate of origin')}}">
        </div>
        <div class="mb-3">
          <label class="form-label">{{$lang->write('Visibility')}}</label>
          <select class="form-select" id="doc_visibility">
            <option value="internal">{{$lang->write('Internal only')}}</option>
            <option value="client_visible">{{$lang->write('Visible to client on share link / PDF')}}</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$lang->write('Close')}}</button>
        <button type="button" class="btn btn-primary" onclick="uploadDocuments()">{{$lang->write('Upload')}}</button>
      </div>
    </div>
  </div>
</div>

{{-- Freight handoff (visible after the proforma is approved) --}}
@if (in_array($req->status, ['accepted', 'fulfilled'], true) && count($items) > 0)
    @php
        $totalWeight = 0.0; $totalCbm = 0.0; $totalPieces = 0;
        foreach ($items as $it) {
            $totalWeight += (float) ($it->weight_kg ?? 0);
            $totalCbm    += (float) ($it->cbm ?? 0);
            $totalPieces += (float) $it->quantity;
        }
    @endphp
    <div class="card mt-4 border-success">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h5 class="card-title mb-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;color:var(--color-green-600);"><path d="M16 16l-4-4-4 4M12 12V2"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7"/></svg>
                        {{ $lang->write('Send to freight') }}
                    </h5>
                    <div class="text-muted small">
                        {{ $lang->write('Skip re-entering — the items, weights and client are already here. Pick a container to load them into.') }}
                    </div>
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3">
                    <div class="text-muted small">{{ $lang->write('Total items') }}</div>
                    <div class="h5 mb-0">{{ count($items) }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted small">{{ $lang->write('Total pieces') }}</div>
                    <div class="h5 mb-0">{{ rtrim(rtrim(number_format($totalPieces, 4, '.', ''), '0'), '.') }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted small">{{ $lang->write('Total weight (kg)') }}</div>
                    <div class="h5 mb-0">{{ number_format($totalWeight, 2) }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted small">{{ $lang->write('Total CBM') }}</div>
                    <div class="h5 mb-0">{{ number_format($totalCbm, 3) }}</div>
                </div>
            </div>
            @if ($req->freight_container_id)
                <div class="alert alert-success mb-3 d-flex justify-content-between align-items-center">
                    <div>
                        <strong>{{ $lang->write('Shipped on') }}</strong>
                        {{ $req->freight_kind === 'sky' ? $lang->write('Air container') : $lang->write('Sea container') }}
                        #{{ $req->freight_container_id }} ·
                        <a href="{{ url('/' . $req->freight_kind) }}" class="alert-link">{{ $lang->write('Open in') }} /{{ $req->freight_kind }}</a>
                    </div>
                    <button class="btn btn-sm btn-light" onclick="syncFromContainer({{ $req->id }})">
                        ⟲ {{ $lang->write('Sync item status from container') }}
                    </button>
                </div>
            @else
                <div class="d-flex gap-2">
                    <a href="{{ url('/sourcing/' . $req->id . '/handoff/sky') }}" class="btn btn-outline-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;"><path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/></svg>
                        {{ $lang->write('Send to air freight') }}
                    </a>
                    <a href="{{ url('/sourcing/' . $req->id . '/handoff/sea') }}" class="btn btn-outline-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;"><path d="M3 18l2-2 2 2 2-2 2 2 2-2 2 2 2-2 2 2"/><path d="M3 13l9-4 9 4"/><path d="M12 9V3"/></svg>
                        {{ $lang->write('Send to sea freight') }}
                    </a>
                    <button class="btn btn-outline-secondary" onclick="copyFreightPayload()">
                        {{ $lang->write('Copy payload JSON') }}
                    </button>
                </div>
            @endif
            <script id="freight_payload_json" type="application/json">{!! json_encode([
                'sourcing_request_id' => $req->id,
                'request_number'      => $req->request_number,
                'client_id'           => $req->client_id,
                'client_name'         => $client->name ?? null,
                'totals' => [
                    'pieces'   => $totalPieces,
                    'weight_kg'=> $totalWeight,
                    'cbm'      => $totalCbm,
                ],
                'items' => $items->map(fn($it) => [
                    'name'        => $it->name,
                    'code'        => $it->code,
                    'description' => $it->description,
                    'quantity'    => (float) $it->quantity,
                    'unit'        => $it->unit,
                    'weight_kg'   => (float) ($it->weight_kg ?? 0),
                    'cbm'         => (float) ($it->cbm ?? 0),
                    'unit_price_to_client' => (float) $it->unit_price_to_client,
                ])->values(),
            ], JSON_UNESCAPED_UNICODE) !!}</script>
        </div>
    </div>
@endif

{{-- ===== Modals (proforma) ===== --}}

{{-- Add / edit item --}}
<div class="modal fade" id="addItem" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header" style="background: linear-gradient(135deg, var(--color-blue-100, #dbeafe), transparent);">
        <div class="d-flex align-items-center gap-2">
          <div style="width:36px;height:36px;border-radius:8px;background:var(--color-blue-600, #2563eb);color:white;display:flex;align-items:center;justify-content:center;">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
          </div>
          <h5 class="modal-title">{{$lang->write('Add product to proforma')}}</h5>
        </div>
        <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.6" /></svg>
        </button>
      </div>
      <div class="modal-body">
        <input type="hidden" class="inp" data-name="id" value="">
        <input type="hidden" class="inp" data-name="catalog_id" value="">

        {{-- Catalog picker (Phase 11) --}}
        <div class="mb-3 p-2 rounded" style="background: var(--color-surface, #f9fafb); border: 1px solid var(--color-border, #e5e7eb);">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <strong class="small">{{$lang->write('Pick from catalog')}}</strong>
            <a href="{{ url('/sourcing/catalog/manage') }}" target="_blank" class="small text-decoration-none">{{$lang->write('Manage catalog')}} →</a>
          </div>
          <input type="text" class="form-control form-control-sm mb-2" id="catalog_search" placeholder="{{$lang->write('Search the catalog…')}}">
          <div id="catalog_results" style="max-height:180px;overflow-y:auto;"></div>
        </div>

        <div class="row g-3">
          <div class="col-12 col-md-8">
            <label class="form-label">{{$lang->write('Product name')}}</label>
            <input type="text" class="form-control inp req" data-name="name" maxlength="191" placeholder="{{$lang->write('e.g. Stainless steel water bottle 500ml')}}">
          </div>
          <div class="col-6 col-md-4">
            <label class="form-label">{{$lang->write('SKU / Code')}}</label>
            <input type="text" class="form-control inp" data-name="code" maxlength="64">
          </div>
          <div class="col-12">
            <label class="form-label">{{$lang->write('Description')}}</label>
            <textarea class="form-control inp" data-name="description" rows="2" placeholder="{{$lang->write('Specs, brand, packaging notes')}}"></textarea>
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label">{{$lang->write('Quantity')}}</label>
            <input type="number" step="any" class="form-control inp req" data-name="quantity">
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label">{{$lang->write('Unit')}}</label>
            <input type="text" class="form-control inp" data-name="unit" value="pcs" maxlength="32">
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label">{{$lang->write('Unit cost')}}</label>
            <input type="number" step="any" class="form-control inp req" data-name="unit_cost" placeholder="{{$lang->write('what supplier charges us')}}">
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label">{{$lang->write('Cost currency')}}</label>
            <select class="form-select inp req" data-name="unit_cost_currency">
              @foreach ($currencies as $c)
                <option value="{{ $c['code'] }}">{{ $c['text'] }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-6 col-md-4">
            <label class="form-label">{{$lang->write('Unit price to client')}}</label>
            <input type="number" step="any" class="form-control inp req" data-name="unit_price_to_client" placeholder="{{$lang->write('what client pays per unit')}}">
            <small class="text-muted">{{$lang->write('In commission_mode=hidden_in_prices, include the markup here. In visible mode, leave equal to unit cost and add commission below.')}}</small>
          </div>
          <div class="col-6 col-md-4">
            <label class="form-label">{{$lang->write('Weight (KG)')}}</label>
            <input type="number" step="any" class="form-control inp" data-name="weight_kg" placeholder="{{$lang->write('Used for freight estimates')}}">
          </div>
          <div class="col-6 col-md-4">
            <label class="form-label">CBM</label>
            <input type="number" step="any" class="form-control inp" data-name="cbm" placeholder="{{$lang->write('Volume for sea freight')}}">
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input inp" type="checkbox" id="save_to_catalog" data-name="save_to_catalog" value="1">
              <label class="form-check-label small" for="save_to_catalog">
                {{$lang->write('Also save to product catalog (reuse next time)')}}
              </label>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$lang->write('Close')}}</button>
        <button type="button" class="btn btn-primary" onclick="submitItem()">{{$lang->write('Save item')}}</button>
      </div>
    </div>
  </div>
</div>

{{-- Photo upload + gallery --}}
<div class="modal fade" id="photoModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{$lang->write('Photos')}}</h5>
        <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.6" /></svg>
        </button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="photo_item_id" value="">
        @if ($editable)
            <div class="mb-3">
              <label class="form-label">{{$lang->write('Upload photos')}} <span class="text-muted small">({{$lang->write('JPG / PNG / WEBP, up to 5 MB each, max 10 at once')}})</span></label>
              <input type="file" class="form-control" id="photo_files" accept="image/jpeg,image/png,image/webp" multiple>
              <button class="btn btn-primary btn-sm mt-2" onclick="uploadPhotos()">{{$lang->write('Upload')}}</button>
            </div>
        @endif
        <div id="photo_gallery" class="row g-2">
          {{-- populated by JS from data-photos JSON below --}}
        </div>
      </div>
    </div>
  </div>
</div>
<script id="photos_json" type="application/json">{!! json_encode($photos, JSON_UNESCAPED_UNICODE) !!}</script>

{{-- Edit proforma settings --}}
<div class="modal fade" id="proformaSettings" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <div class="modal-header" style="background: linear-gradient(135deg, var(--color-purple-100, #ede9fe), transparent);">
        <div class="d-flex align-items-center gap-2">
          <div style="width:36px;height:36px;border-radius:8px;background:var(--color-purple-600, #7c3aed);color:white;display:flex;align-items:center;justify-content:center;">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          </div>
          <h5 class="modal-title">{{$lang->write('Proforma settings')}}</h5>
        </div>
        <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.6" /></svg>
        </button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-6">
            <label class="form-label">{{$lang->write('Display currency')}}</label>
            <select class="form-select" id="settings_display_currency">
              @foreach ($currencies as $c)
                <option value="{{ $c['code'] }}" {{ ($req->display_currency ?: $req->currency) === $c['code'] ? 'selected' : '' }}>{{ $c['text'] }}</option>
              @endforeach
            </select>
            <small class="text-muted">{{$lang->write('FX rates lock the moment you save.')}}</small>
          </div>
          <div class="col-6">
            <label class="form-label">{{$lang->write('Commission mode')}}</label>
            <select class="form-select" id="settings_commission_mode">
              <option value="hidden_in_prices" {{ $req->commission_mode === 'hidden_in_prices' ? 'selected' : '' }}>{{$lang->write('Hidden in prices')}}</option>
              <option value="visible_separate" {{ $req->commission_mode === 'visible_separate' ? 'selected' : '' }}>{{$lang->write('Visible to client')}}</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">{{$lang->write('Commission amount')}}</label>
            <input type="number" step="any" class="form-control" id="settings_commission_amount" value="{{ $req->commission_amount ?: '' }}" placeholder="0">
            <small class="text-muted">{{$lang->write('Used only when mode is visible.')}}</small>
          </div>
          <div class="col-6">
            <label class="form-label">{{$lang->write('Commission currency')}}</label>
            <select class="form-select" id="settings_commission_currency">
              @foreach ($currencies as $c)
                <option value="{{ $c['code'] }}" {{ ($req->commission_currency ?: $req->currency) === $c['code'] ? 'selected' : '' }}>{{ $c['text'] }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">{{$lang->write('Terms & notes')}}</label>
            <textarea class="form-control" id="settings_terms_text" rows="3" placeholder="{{$lang->write('Payment terms, validity, delivery time…')}}">{{ $req->terms_text }}</textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$lang->write('Close')}}</button>
        <button type="button" class="btn btn-primary" onclick="submitProformaSettings()">{{$lang->write('Save settings')}}</button>
      </div>
    </div>
  </div>
</div>

{{-- Edit installment / add installment --}}
<div class="modal fade" id="paymentEdit" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="paymentEditTitle">{{$lang->write('Edit installment')}}</h5>
        <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.6" /></svg>
        </button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="payment_edit_id">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">{{$lang->write('Label')}}</label>
            <input type="text" class="form-control" id="payment_label" maxlength="191">
          </div>
          <div class="col-6">
            <label class="form-label">{{$lang->write('Amount')}}</label>
            <input type="number" step="any" class="form-control" id="payment_amount">
          </div>
          <div class="col-6">
            <label class="form-label">{{$lang->write('Currency')}}</label>
            <select class="form-select" id="payment_currency">
              @foreach ($currencies as $c)
                <option value="{{ $c['code'] }}">{{ $c['text'] }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">%</label>
            <input type="number" step="any" class="form-control" id="payment_percentage" min="0" max="100">
          </div>
          <div class="col-6">
            <label class="form-label">{{$lang->write('Due date')}}</label>
            <input type="date" class="form-control" id="payment_due_date">
          </div>
          <div class="col-12">
            <label class="form-label">{{$lang->write('Notes')}}</label>
            <textarea class="form-control" id="payment_notes" rows="2" maxlength="500"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$lang->write('Close')}}</button>
        <button type="button" class="btn btn-primary" onclick="submitPayment()">{{$lang->write('Save')}}</button>
      </div>
    </div>
  </div>
</div>

{{-- Apply markup % to all items (Phase 10) --}}
<div class="modal fade" id="markupModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ $lang->write('Apply cost-plus markup') }}</h5>
        <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.6" /></svg>
        </button>
      </div>
      <div class="modal-body">
        <p class="text-muted small">
          {{ $lang->write('Recomputes every item\'s unit price as') }}
          <code>unit_cost × (1 + markup/100)</code>.
          {{ $lang->write('All current per-item prices will be overwritten.') }}
        </p>
        <div class="mb-3">
          <label class="form-label">{{ $lang->write('Markup percent') }} <span class="text-danger">*</span></label>
          <div class="input-group">
            <input type="number" step="any" class="form-control" id="markup_pct" value="25" placeholder="e.g. 25">
            <span class="input-group-text">%</span>
          </div>
          <small class="text-muted">
            {{ $lang->write('Allowed range −99 to +1000. Use a negative number to set discounted pricing below cost.') }}
          </small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ $lang->write('Close') }}</button>
        <button type="button" class="btn btn-primary" onclick="submitMarkup()">{{ $lang->write('Apply') }}</button>
      </div>
    </div>
  </div>
</div>

{{-- Email proforma to client (Phase 4) --}}
@php
    // Read templates from settings.json so the operator's edits in
    // /settings flow through to every modal. Hardcoded fallbacks kick in
    // if the keys are blank (which they shouldn't be after the Phase 8
    // migration, but defensive code is cheap).
    $sysSettings = (new \App\Http\Controllers\settingsController())->get();
    $companyName = $sysSettings['company_name'] ?? 'Our company';
    $defaultSubject = $sysSettings['proforma_email_subject'] ?:
        'Proforma {number} from {company}';
    $defaultBody    = $sysSettings['proforma_email_body'] ?:
        "Dear {client},\n\nPlease find your proforma {number} attached and available online at:\n{link}\n\nOnce reviewed, you can approve directly from the link above.\n\nTotal: {total}\n\nThank you,\n{company}";
    // Pre-substitute the two placeholders that don't change across sends so
    // the operator sees a readable default — {link} / {client} / {total}
    // still need server-side resolution at send time.
    $defaultSubject = str_replace(['{number}', '{company}'], [$req->request_number, $companyName], $defaultSubject);
@endphp
<div class="modal fade" id="emailProforma" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header" style="background: linear-gradient(135deg, var(--color-blue-100, #dbeafe), transparent);">
        <div class="d-flex align-items-center gap-2">
          <div style="width:36px;height:36px;border-radius:8px;background:var(--color-blue-600, #2563eb);color:white;display:flex;align-items:center;justify-content:center;">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          </div>
          <h5 class="modal-title">{{ $lang->write('Email proforma to client') }}</h5>
        </div>
        <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.6" /></svg>
        </button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12 col-md-6">
            <label class="form-label">{{ $lang->write('To') }}</label>
            <input type="email" class="form-control" id="email_to" value="{{ $client->email ?? '' }}">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">{{ $lang->write('Cc (comma-separated)') }}</label>
            <input type="text" class="form-control" id="email_cc" placeholder="optional">
          </div>
          <div class="col-12">
            <label class="form-label">{{ $lang->write('Subject') }}</label>
            <input type="text" class="form-control" id="email_subject" value="{{ $defaultSubject }}" maxlength="191">
          </div>
          <div class="col-12">
            <label class="form-label">{{ $lang->write('Body') }}</label>
            <textarea class="form-control" id="email_body" rows="8" maxlength="5000">{{ $defaultBody }}</textarea>
            <small class="text-muted">
              {{ $lang->write('Placeholders:') }}
              <code>{link}</code> · <code>{number}</code> · <code>{client}</code> · <code>{total}</code> · <code>{company}</code>
            </small>
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" value="1" id="email_attach_pdf" checked>
              <label class="form-check-label" for="email_attach_pdf">
                {{ $lang->write('Attach PDF') }}
              </label>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ $lang->write('Close') }}</button>
        <button type="button" class="btn btn-primary" onclick="sendEmail()">{{ $lang->write('Send') }}</button>
      </div>
    </div>
  </div>
</div>

{{-- Mark installment paid --}}
<div class="modal fade" id="markPaidModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <div class="modal-header" style="background: linear-gradient(135deg, var(--color-green-100, #d1fae5), transparent);">
        <div class="d-flex align-items-center gap-2">
          <div style="width:36px;height:36px;border-radius:8px;background:var(--color-green-600, #059669);color:white;display:flex;align-items:center;justify-content:center;">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="20 6 9 17 4 12"></polyline></svg>
          </div>
          <h5 class="modal-title">{{$lang->write('Mark installment paid')}}</h5>
        </div>
        <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.6" /></svg>
        </button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="markpaid_id">
        <div class="alert alert-light" id="markpaid_summary"></div>
        <div class="row g-3">
          <div class="col-6">
            <label class="form-label">{{$lang->write('Payment method')}}</label>
            <select class="form-select" id="markpaid_method">
                <option value="wallet">{{$lang->write('From client wallet (already with us)')}}</option>
                <option value="cash">{{$lang->write('Cash received now')}}</option>
            </select>
            <small class="text-muted">{{$lang->write('Wallet: no new journal entry. Cash: posts a client deposit linked to this proforma.')}}</small>
          </div>
          <div class="col-6">
            <label class="form-label">{{$lang->write('Branch (for cash)')}}</label>
            <select class="form-select" id="markpaid_branch">
                <option value="">{{$lang->write('Select')}}</option>
                @foreach (DB::table('branches')->where('deleted','false')->orderBy('id')->get() as $b)
                    <option value="{{ $b->id }}">{{ $lang->branch($b->id) }}</option>
                @endforeach
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">{{$lang->write('Amount received')}}</label>
            <input type="number" step="any" class="form-control" id="markpaid_amount">
            <small class="text-muted">{{$lang->write('Defaults to the installment amount. Set lower for a partial payment.')}}</small>
          </div>
          <div class="col-12">
            <label class="form-label">{{$lang->write('Notes')}}</label>
            <textarea class="form-control" id="markpaid_notes" rows="2" maxlength="500"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$lang->write('Close')}}</button>
        <button type="button" class="btn btn-success" onclick="submitMarkPaid()">{{$lang->write('Record payment')}}</button>
      </div>
    </div>
  </div>
</div>

{{-- Add new installment (separate to differentiate intent) --}}
<div class="modal fade" id="addPayment" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{$lang->write('Add installment')}}</h5>
        <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.6" /></svg>
        </button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">{{$lang->write('Label')}}</label>
            <input type="text" class="form-control" id="addpay_label" maxlength="191">
          </div>
          <div class="col-6">
            <label class="form-label">{{$lang->write('Amount')}}</label>
            <input type="number" step="any" class="form-control" id="addpay_amount">
          </div>
          <div class="col-6">
            <label class="form-label">{{$lang->write('Currency')}}</label>
            <select class="form-select" id="addpay_currency">
              @foreach ($currencies as $c)
                <option value="{{ $c['code'] }}" {{ $c['code'] === ($req->display_currency ?: $req->currency) ? 'selected' : '' }}>{{ $c['text'] }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">%</label>
            <input type="number" step="any" class="form-control" id="addpay_percentage" min="0" max="100">
          </div>
          <div class="col-6">
            <label class="form-label">{{$lang->write('Due date')}}</label>
            <input type="date" class="form-control" id="addpay_due_date">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$lang->write('Close')}}</button>
        <button type="button" class="btn btn-primary" onclick="submitAddPayment()">{{$lang->write('Add')}}</button>
      </div>
    </div>
  </div>
</div>

<div class="card mt-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="card-title mb-0">{{$lang->write('Quotes')}}</h5>
            @if (!$isFinal && !$hasCommission)
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addQuote">
                    {{ $lang->write('Add quote') }}
                </button>
            @endif
        </div>

        @if (count($quotes) < 1)
            <p class="text-muted">{{$lang->write('No quotes yet.')}}</p>
        @else
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>{{$lang->write('Supplier')}}</th>
                        <th class="text-end">{{$lang->write('Unit price')}}</th>
                        <th class="text-end">{{$lang->write('Quantity')}}</th>
                        <th class="text-end">{{$lang->write('Total')}}</th>
                        <th>{{$lang->write('Lead time')}}</th>
                        <th>{{$lang->write('Status')}}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($quotes as $q)
                    <tr>
                        <td>{{ $q->supplier_name ?? $q->supplier_name_freeform ?? '—' }}</td>
                        <td class="text-end">{{ number_format((float) $q->unit_price, 2) }} {{ strtoupper($q->currency) }}</td>
                        <td class="text-end">{{ $q->quantity ? rtrim(rtrim(number_format((float) $q->quantity, 4, '.', ''), '0'), '.') : '—' }}</td>
                        <td class="text-end">{{ number_format((float) $q->total_price, 2) }} {{ strtoupper($q->currency) }}</td>
                        <td>{{ $q->lead_time_days ? $q->lead_time_days . 'd' : '—' }}</td>
                        <td>
                            @if ($q->status === 'accepted')
                                <span class="badge bg-success">{{$lang->write('Accepted')}}</span>
                            @elseif ($q->status === 'rejected')
                                <span class="badge bg-secondary">{{$lang->write('Rejected')}}</span>
                            @else
                                <span class="badge bg-primary">{{$lang->write('Proposed')}}</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @if (!$hasCommission && !$isFinal && $q->status === 'proposed')
                                <button class="btn btn-sm btn-outline-success"
                                    onclick="showAcceptQuote({{$q->id}}, '{{ $q->currency }}', {{ (float) $q->total_price }})">
                                    {{$lang->write('Accept')}}
                                </button>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

{{-- Add-quote modal --}}
<div class="modal fade" id="addQuote" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{$lang->write('Add quote')}}</h5>
        <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.3" /></svg>
        </button>
      </div>
      <div class="modal-body">
          <div class="mb-3">
            <label>{{$lang->write('Supplier')}} ({{$lang->write('optional')}}):</label>
            <select class="form-select inp" data-name="supplier_id">
              <option value="">{{$lang->write('— Free-form name below —')}}</option>
              @foreach ($suppliers as $s)
                <option value="{{$s->id}}">{{$s->name}}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label>{{$lang->write('Free-form supplier name')}}:</label>
            <input type="text" class="form-control inp" data-name="supplier_name_freeform" placeholder="{{$lang->write('Used only when no supplier selected above')}}">
          </div>
          <div class="row">
            <div class="mb-3 col-6"><label>{{$lang->write('Unit price')}} : *</label><input type="number" step="any" class="form-control inp req" data-name="unit_price"></div>
            <div class="mb-3 col-6"><label>{{$lang->write('Quantity')}} :</label><input type="number" step="any" class="form-control inp" data-name="quantity"></div>
            <div class="mb-3 col-6">
              <label>{{$lang->write('Currency')}} : *</label>
              <select class="form-select inp req" data-name="currency">
                <option value="">{{$lang->write('Select')}}</option>
                @foreach ($currencies as $cur)
                  <option value="{{$cur['code']}}" {{ $cur['code'] === $req->currency ? 'selected' : '' }}>{{$cur['text']}}</option>
                @endforeach
              </select>
            </div>
            <div class="mb-3 col-6"><label>{{$lang->write('Lead time (days)')}}:</label><input type="number" class="form-control inp" data-name="lead_time_days"></div>
            <div class="mb-3 col-12"><label>{{$lang->write('Notes')}}:</label><textarea class="form-control inp" data-name="notes" rows="2"></textarea></div>
          </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$lang->write('Close')}}</button>
        <button type="button" class="btn btn-primary" onclick="submitQuote()">{{$lang->write('Add quote')}}</button>
      </div>
    </div>
  </div>
</div>

{{-- Accept-quote modal — pre-filled by JS --}}
<div class="modal fade" id="acceptQuote" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{$lang->write('Accept quote — set commission')}}</h5>
        <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.3" /></svg>
        </button>
      </div>
      <div class="modal-body">
        <input type="hidden" class="inp req" data-name="quote_id">
        <div class="mb-3">
          <label>{{$lang->write('Commission amount')}} : *</label>
          <input type="number" step="any" class="form-control inp req" data-name="commission_amount">
        </div>
        <div class="mb-3">
          <label>{{$lang->write('Currency')}} : *</label>
          <select class="form-select inp req" data-name="commission_currency">
            <option value="">{{$lang->write('Select')}}</option>
            @foreach ($currencies as $cur)
              <option value="{{$cur['code']}}">{{$cur['text']}}</option>
            @endforeach
          </select>
        </div>
        <p class="small text-muted mb-0">
          {{ $lang->write('Posting:') }}
          <code>Dr 1100</code> AR clients · <code>Cr 4020</code> {{ $lang->write('Sourcing commission revenue') }}
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$lang->write('Close')}}</button>
        <button type="button" class="btn btn-success" onclick="submitAcceptQuote()">{{$lang->write('Post commission')}}</button>
      </div>
    </div>
  </div>
</div>

{{-- Activity timeline (Phase 4) --}}
@if (isset($timeline) && count($timeline) > 0)
    @php
        $actionLabels = [
            'sourcing_create'              => ['label' => 'Proforma created',           'color' => 'secondary'],
            'sourcing_update'              => ['label' => 'Updated',                    'color' => 'secondary'],
            'sourcing_cancel'              => ['label' => 'Canceled',                   'color' => 'dark'],
            'sourcing_fulfill'             => ['label' => 'Marked fulfilled',           'color' => 'success'],
            'sourcing_quote_add'           => ['label' => 'Supplier quote added',       'color' => 'info'],
            'sourcing_quote_accept'        => ['label' => 'Supplier quote accepted',    'color' => 'warning'],
            'sourcing_item_add'            => ['label' => 'Item added',                 'color' => 'secondary'],
            'sourcing_item_update'         => ['label' => 'Item updated',               'color' => 'secondary'],
            'sourcing_item_delete'         => ['label' => 'Item deleted',               'color' => 'secondary'],
            'sourcing_item_photos_upload'  => ['label' => 'Photos uploaded',            'color' => 'secondary'],
            'sourcing_payment_plan'        => ['label' => 'Payment plan generated',    'color' => 'secondary'],
            'sourcing_installment_paid'    => ['label' => 'Installment paid',           'color' => 'success'],
            'sourcing_proforma_send'       => ['label' => 'Sent to client',             'color' => 'primary'],
            'sourcing_proforma_settings'   => ['label' => 'Proforma settings changed',  'color' => 'secondary'],
            'sourcing_proforma_approve'    => ['label' => 'Approved',                   'color' => 'success'],
            'sourcing_client_viewed'       => ['label' => 'Client opened share link',   'color' => 'info'],
            'sourcing_email_sent'          => ['label' => 'Emailed to client',          'color' => 'primary'],
            'sourcing_freight_handoff'     => ['label' => 'Handed off to freight',      'color' => 'success'],
        ];
    @endphp
    <div class="card mt-4">
        <div class="card-body">
            <h5 class="card-title mb-3">{{ $lang->write('Activity timeline') }}</h5>
            <div style="position:relative;padding-inline-start:1.5rem;border-inline-start:2px solid var(--color-border);">
                @foreach ($timeline as $ev)
                    @php
                        $info  = $actionLabels[$ev->action] ?? ['label' => $ev->action, 'color' => 'secondary'];
                    @endphp
                    <div class="mb-3" style="position:relative;">
                        <div style="position:absolute;left:-1.85rem;top:.25rem;width:.7rem;height:.7rem;border-radius:50%;background:var(--color-{{ $info['color'] }}-500, #6b7280);"></div>
                        <div class="d-flex justify-content-between">
                            <div>
                                <span class="badge bg-{{ $info['color'] }}">{{ $lang->write($info['label']) }}</span>
                                @if (!empty($ev->context))
                                    <span class="small text-muted">— {{ $ev->context }}</span>
                                @endif
                            </div>
                            <div class="small text-muted">
                                {{ substr($ev->created_at, 0, 19) }}
                                @if ($ev->user_name)
                                    · {{ $ev->user_name }}
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif

@if (!empty($req->client_viewed_at))
    <div class="alert alert-secondary mt-2 small">
        {{ $lang->write('Client view tracking') }}:
        {{ $lang->write('first opened') }} <strong>{{ substr($req->client_viewed_at, 0, 19) }}</strong>
        · {{ $lang->write('total views') }} <strong>{{ $req->client_view_count }}</strong>
    </div>
@endif

{{-- Phase 15 — health trend loader. Pulls last 14d of snapshots and
     draws a tiny line chart. Hides itself if there's only 1 point. --}}
<script>
(function () {
    var wrap = document.getElementById('healthTrendWrap');
    if (!wrap) return;
    var pid = wrap.getAttribute('data-proforma-id');
    fetch('{{ url('/sourcing') }}/' + pid + '/health-trend', {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { return r.ok ? r.json() : null; })
    .then(function (data) {
        if (!data || !data.series || data.series.length < 2) return;
        var svg = document.getElementById('healthTrendSvg');
        var cap = document.getElementById('healthTrendCaption');
        var W = 280, H = 60, pad = 4;
        var series = data.series;
        var n = series.length;
        var scoreToY = function (s) { return H - pad - (s / 100) * (H - 2 * pad); };
        var stepX = (W - 2 * pad) / Math.max(1, n - 1);
        var pts = series.map(function (p, i) {
            return (pad + i * stepX).toFixed(1) + ',' + scoreToY(p.score).toFixed(1);
        }).join(' ');
        var first = series[0].score, last = series[n - 1].score;
        var delta = last - first;
        var color = delta > 0 ? '#198754' : (delta < 0 ? '#dc3545' : '#6c757d');
        svg.innerHTML =
            '<polyline points="' + pts + '" fill="none" stroke="' + color + '" stroke-width="1.6"/>' +
            series.map(function (p, i) {
                return '<circle cx="' + (pad + i * stepX).toFixed(1) + '" cy="' + scoreToY(p.score).toFixed(1) + '" r="1.6" fill="' + color + '"/>';
            }).join('');
        var arrow = delta > 0 ? '▲' : (delta < 0 ? '▼' : '→');
        cap.textContent = n + ' day(s) · ' + first + ' → ' + last + '  ' + arrow + ' ' + (delta >= 0 ? '+' : '') + delta;
        wrap.style.display = 'block';
    })
    .catch(function () { /* silent — chart is supplementary */ });
})();
</script>

@endsection
