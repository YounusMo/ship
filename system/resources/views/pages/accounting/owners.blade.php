@php
    if (!in_array(auth()->user()->type, ['admin'])) { abort(403); }
@endphp
@extends('layout')
@section('content')

<div class="row mb-3 align-items-end">
    <div class="col-12 mb-2">
        <h4 class="h4">{{ $lang->write('Owners') }}</h4>
        <small class="text-muted">{{ $lang->write('Company owners and their shares. Treasury transactions tagged with an owner purpose can be linked to a specific owner.') }}</small>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h6 class="mb-3">{{ $lang->write('Add owner') }}</h6>
        <form id="own-form" class="row g-2">
            @csrf
            <div class="col-md-3"><input class="form-control form-control-sm" name="name" placeholder="{{ $lang->write('Name (Arabic)') }}" required></div>
            <div class="col-md-3"><input class="form-control form-control-sm" name="name_en" placeholder="{{ $lang->write('Name (English)') }}"></div>
            <div class="col-md-2"><input class="form-control form-control-sm" name="share_percentage" type="number" step="0.001" placeholder="{{ $lang->write('Share %') }}"></div>
            <div class="col-md-2"><input class="form-control form-control-sm" name="national_id" placeholder="{{ $lang->write('National ID') }}"></div>
            <div class="col-md-2"><input class="form-control form-control-sm" name="phone" placeholder="{{ $lang->write('Phone') }}"></div>
            <div class="col-md-4"><input class="form-control form-control-sm" name="email" placeholder="{{ $lang->write('Email') }}"></div>
            <div class="col-md-6"><input class="form-control form-control-sm" name="notes" placeholder="{{ $lang->write('Notes') }}"></div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100" type="submit">{{ $lang->write('Save') }}</button></div>
        </form>
    </div>
</div>

<div class="table-responsive">
<table class="table table-sm align-middle">
    <thead class="table-light">
        <tr>
            <th>#</th>
            <th>{{ $lang->write('Name') }}</th>
            <th>{{ $lang->write('Share %') }}</th>
            <th>{{ $lang->write('National ID') }}</th>
            <th>{{ $lang->write('Phone') }}</th>
            <th>{{ $lang->write('Email') }}</th>
            <th>{{ $lang->write('Status') }}</th>
            <th>{{ $lang->write('Action') }}</th>
        </tr>
    </thead>
    <tbody>
    @forelse ($owners as $o)
        <tr>
            <td>{{ $o->id }}</td>
            <td>{{ $o->name }} <span class="text-muted small">{{ $o->name_en }}</span></td>
            <td>{{ number_format((float) $o->share_percentage, 3) }}%</td>
            <td>{{ $o->national_id }}</td>
            <td>{{ $o->phone }}</td>
            <td>{{ $o->email }}</td>
            <td>
                @if ($o->active) <span class="badge bg-success">{{ $lang->write('Active') }}</span>
                @else <span class="badge bg-secondary">{{ $lang->write('Inactive') }}</span> @endif
            </td>
            <td>
                <button class="btn btn-sm btn-danger" onclick="delOwner({{ $o->id }})">{{ $lang->write('Delete') }}</button>
            </td>
        </tr>
    @empty
        <tr><td colspan="8" class="text-center text-muted py-4">{{ $lang->write('No owners yet') }}</td></tr>
    @endforelse
    </tbody>
</table>
</div>

<script>
$('#own-form').on('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fetch('/accounting/owners', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json()).then(j => { if (j.type === 'success') location.reload(); });
});
function delOwner(id) {
    if (!confirm('{{ $lang->write("Delete this owner?") }}')) return;
    $.ajax({
        url: '/accounting/owners/' + id, type: 'DELETE',
        data: { _token: '{{ csrf_token() }}' },
        success: () => location.reload(),
    });
}
</script>

@endsection
