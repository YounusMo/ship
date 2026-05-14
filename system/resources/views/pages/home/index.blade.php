@php
    use App\Http\Controllers\langController;
    use App\Http\Controllers\dataController;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;

    $lang = new langController();
    $data = new dataController();
    $user = auth()->user();
    $isBranchAdmin = ($user->type === 'branch_admin');

    // ---------- Aggregations ----------
    $clientQuery = DB::table('clients')->where('deleted', 'false')->where('not_active', 'false');
    if ($isBranchAdmin) {
        $clientQuery->where('branch', $user->branch);
    }
    $balanceTotals = $clientQuery->selectRaw('
        SUM(CAST(balance_usd AS DECIMAL(20,4))) AS usd,
        SUM(CAST(balance_eur AS DECIMAL(20,4))) AS eur,
        SUM(CAST(balance_den AS DECIMAL(20,4))) AS den,
        SUM(CAST(balance_cny AS DECIMAL(20,4))) AS cny,
        COUNT(*) AS clients_count
    ')->first();

    $pendingCount = DB::table('clients_transactions')
        ->where('status', 'pending')
        ->when($isBranchAdmin, fn($q) => $q->where('branch', $user->branch))
        ->count();

    $branchCount = DB::table('branches')
        ->where('deleted', 'false')
        ->when($isBranchAdmin, fn($q) => $q->where('id', $user->branch))
        ->count();

    $todayDeposits = DB::table('clients_transactions')
        ->where('type', 'deposit')
        ->where('status', 'approved')
        ->where('created_date', date('Y-m-d'))
        ->when($isBranchAdmin, fn($q) => $q->where('branch', $user->branch))
        ->count();

    $todayWithdrawals = DB::table('clients_transactions')
        ->where('type', 'withdraw')
        ->where('status', 'approved')
        ->where('created_date', date('Y-m-d'))
        ->when($isBranchAdmin, fn($q) => $q->where('branch', $user->branch))
        ->count();

    // Recent activity from audit_log (if present)
    $recent = collect();
    $userNames = collect();
    if (Schema::hasTable('audit_log')) {
        $recent = DB::table('audit_log')
            ->orderBy('id', 'desc')
            ->limit(8)
            ->get();
        if ($recent->count() > 0) {
            $userNames = DB::table('users')
                ->whereIn('id', $recent->pluck('user_id')->filter()->unique())
                ->pluck('name', 'id');
        }
    }

    function fmt_amount($v) {
        return number_format(floatval($v), 2, '.', ',');
    }
@endphp
@extends('layout')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">
            {{ $lang->write('Welcome back') }}{{ $user->name ? ', '.$user->name : '' }}
        </h1>
        <div class="page-subtitle">
            {{ date('l, j F Y') }}
            @if ($isBranchAdmin)
                &middot; {{ $lang->write('Branch view') }}: {{ $lang->branch($user->branch) }}
            @endif
        </div>
    </div>
    <div class="page-actions">
        <a href="{{ url('/clients/all') }}" class="btn btn-secondary">
            {{ $lang->write('Clients') }}
        </a>
        <a href="{{ url('/treasury') }}" class="btn btn-primary">
            {{ $lang->write('Open treasury') }}
        </a>
    </div>
</div>

{{-- ============ Balance KPIs ============ --}}
<div class="kpi-grid">
    <div class="kpi-tile accent">
        <div class="kpi-label">{{ $lang->write('Total clients') }}</div>
        <div class="kpi-value">{{ number_format($balanceTotals->clients_count ?? 0) }}</div>
        <div class="kpi-sub">
            {{ $branchCount }} {{ $lang->write('active branches') }}
        </div>
    </div>

    <div class="kpi-tile">
        <div class="kpi-label">USD {{ $lang->write('balances') }}</div>
        <div class="kpi-value">{{ fmt_amount($balanceTotals->usd ?? 0) }}</div>
        <div class="kpi-sub">
            <span class="currency-badge usd">USD</span>
            {{ $lang->write('Across all clients') }}
        </div>
    </div>

    <div class="kpi-tile">
        <div class="kpi-label">EUR {{ $lang->write('balances') }}</div>
        <div class="kpi-value">{{ fmt_amount($balanceTotals->eur ?? 0) }}</div>
        <div class="kpi-sub">
            <span class="currency-badge eur">EUR</span>
            {{ $lang->write('Across all clients') }}
        </div>
    </div>

    <div class="kpi-tile">
        <div class="kpi-label">LYD {{ $lang->write('balances') }}</div>
        <div class="kpi-value">{{ fmt_amount($balanceTotals->den ?? 0) }}</div>
        <div class="kpi-sub">
            <span class="currency-badge den">LYD</span>
            {{ $lang->write('Across all clients') }}
        </div>
    </div>

    <div class="kpi-tile">
        <div class="kpi-label">CNY {{ $lang->write('balances') }}</div>
        <div class="kpi-value">{{ fmt_amount($balanceTotals->cny ?? 0) }}</div>
        <div class="kpi-sub">
            <span class="currency-badge cny">CNY</span>
            {{ $lang->write('Across all clients') }}
        </div>
    </div>
</div>

{{-- ============ Operational tiles ============ --}}
<div class="kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
    <div class="kpi-tile">
        <div class="kpi-label">{{ $lang->write('Pending approvals') }}</div>
        <div class="kpi-value" style="color: {{ $pendingCount > 0 ? 'var(--color-amber-600)' : 'var(--color-text-muted)' }};">{{ $pendingCount }}</div>
        @if ($pendingCount > 0)
            <a href="{{ url('/clients/all?pending=true') }}" class="kpi-sub" style="text-decoration:none;color:var(--color-amber-600);">
                {{ $lang->write('Review now') }} &rarr;
            </a>
        @else
            <div class="kpi-sub">{{ $lang->write('Nothing waiting') }}</div>
        @endif
    </div>

    <div class="kpi-tile">
        <div class="kpi-label">{{ $lang->write('Today, deposits') }}</div>
        <div class="kpi-value text-success">{{ $todayDeposits }}</div>
        <div class="kpi-sub">{{ $lang->write('Approved') }}</div>
    </div>

    <div class="kpi-tile">
        <div class="kpi-label">{{ $lang->write('Today, withdrawals') }}</div>
        <div class="kpi-value text-danger">{{ $todayWithdrawals }}</div>
        <div class="kpi-sub">{{ $lang->write('Approved') }}</div>
    </div>
</div>

{{-- ============ Recent activity ============ --}}
@if ($recent->count() > 0)
<div class="card">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h2 class="h4 mb-0">{{ $lang->write('Recent activity') }}</h2>
            @if ($user->type === 'admin')
                <a href="{{ url('/audit') }}" class="btn btn-secondary btn-sm">
                    {{ $lang->write('View full audit log') }}
                </a>
            @endif
        </div>
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>{{ $lang->write('When') }}</th>
                    <th>{{ $lang->write('Who') }}</th>
                    <th>{{ $lang->write('Action') }}</th>
                    <th>{{ $lang->write('Target') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($recent as $row)
                    <tr>
                        <td><small class="text-muted">{{ $row->created_at }}</small></td>
                        <td>{{ $userNames[$row->user_id] ?? '—' }}</td>
                        <td><span class="badge-finance">{{ $row->action }}</span></td>
                        <td>
                            <span class="text-muted">{{ $row->target_table }}</span>
                            @if ($row->target_id)
                                <small class="text-subtle">#{{ $row->target_id }}</small>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@else
<div class="card">
    <div class="card-body empty-state">
        <div class="empty-icon">📒</div>
        <div class="empty-title">{{ $lang->write('No activity yet') }}</div>
        <div>{{ $lang->write('Start by creating clients or recording a deposit.') }}</div>
    </div>
</div>
@endif

@endsection
