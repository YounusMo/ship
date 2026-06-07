@php
    if (!in_array(auth()->user()->type, ['admin'])) { abort(403); }

    // Group active + inactive accounts by type for readable rendering.
    // Inside each type, root accounts (parent_id = null) render first; their
    // children render right after, indented one level. We only support a
    // single level of nesting today — that's all the seeded tree uses.
    $byId       = [];
    $childrenOf = [];
    foreach ($accounts as $a) {
        $byId[$a->id] = $a;
        $childrenOf[$a->parent_id ?? 0][] = $a;
    }

    $typeOrder = ['asset', 'liability', 'equity', 'revenue', 'expense'];
    $byType    = array_fill_keys($typeOrder, []);
    foreach ($accounts as $a) {
        if ($a->parent_id === null) {
            $byType[$a->type][] = ['row' => $a, 'depth' => 0];
            foreach (($childrenOf[$a->id] ?? []) as $child) {
                $byType[$child->type][] = ['row' => $child, 'depth' => 1];
            }
        }
    }
    // Orphans: a child whose parent_id points at a row that isn't in the
    // result set (shouldn't happen with the current seed but keeps the view
    // honest if someone deactivates a parent later).
    foreach ($accounts as $a) {
        if ($a->parent_id !== null && !isset($byId[$a->parent_id])) {
            $byType[$a->type][] = ['row' => $a, 'depth' => 0];
        }
    }
@endphp
@extends('layout')
@section('content')

<div class="row mb-3 align-items-end">
    <div class="col-12 mb-2">
        <h4 class="h4">{{ $lang->write('Chart of Accounts') }}</h4>
        <small class="text-muted">{{ $lang->write('The standard accounts the trial balance derives from. System accounts cannot be removed.') }}</small>
    </div>
</div>

<div class="table-responsive">
<table class="table table-sm align-middle">
    <thead class="table-light">
        <tr>
            <th style="width:90px;">{{ $lang->write('Code') }}</th>
            <th>{{ $lang->write('Account') }}</th>
            <th style="width:140px;">{{ $lang->write('Normal balance') }}</th>
            <th style="width:160px;">{{ $lang->write('Status') }}</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($typeOrder as $type)
        @if (!empty($byType[$type]))
            <tr class="table-secondary">
                <td colspan="4" class="fw-semibold">
                    {{ $lang->write('account.type.' . $type) }}
                </td>
            </tr>
            @foreach ($byType[$type] as $entry)
                @php $a = $entry['row']; $depth = $entry['depth']; @endphp
                <tr>
                    <td class="text-muted">{{ $a->code }}</td>
                    <td>
                        @if ($depth > 0)
                            <span class="text-muted" style="margin-inline-start: {{ $depth * 1.5 }}rem;">↳</span>
                        @endif
                        {{ $lang->write($a->name) }}
                    </td>
                    <td>{{ $lang->write('account.normal.' . $a->normal_balance) }}</td>
                    <td>
                        @if ($a->is_system) <span class="badge bg-info">{{ $lang->write('System') }}</span> @endif
                        @if (!$a->is_active) <span class="badge bg-warning">{{ $lang->write('Inactive') }}</span> @endif
                    </td>
                </tr>
            @endforeach
        @endif
    @endforeach
    </tbody>
</table>
</div>

@endsection
