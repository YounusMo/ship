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
            {{ $lang->write('Product catalog') }}
        </h1>
        <div class="page-subtitle">
            {{ $lang->write('Reusable products that pre-fill the Add item form on a proforma') }}
        </div>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="openCatalogEdit()">+ {{ $lang->write('New product') }}</button>
    </div>
</div>

<div class="card">
    <div class="card-body">
        @if (count($rows) < 1)
            <p class="text-muted mb-0">
                {{ $lang->write('Catalog is empty. Add items here, or tick "Also save to catalog" while creating proforma items.') }}
            </p>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>{{ $lang->write('Name') }}</th>
                            <th>{{ $lang->write('Code') }}</th>
                            <th>{{ $lang->write('Unit') }}</th>
                            <th class="text-end">{{ $lang->write('Default cost') }}</th>
                            <th class="text-end">{{ $lang->write('Default price') }}</th>
                            <th class="text-end">{{ $lang->write('Used') }}</th>
                            <th>{{ $lang->write('Status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($rows as $r)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $r->name }}</div>
                                @if ($r->description)
                                    <div class="small text-muted text-truncate" style="max-width:300px;">{{ $r->description }}</div>
                                @endif
                            </td>
                            <td>{{ $r->code ?: '—' }}</td>
                            <td>{{ $r->unit }}</td>
                            <td class="text-end">
                                {{ number_format((float) $r->default_unit_cost, 2) }}
                                <span class="text-muted small">{{ strtoupper($r->default_unit_cost_currency) }}</span>
                            </td>
                            <td class="text-end">{{ number_format((float) $r->default_unit_price, 2) }}</td>
                            <td class="text-end">{{ $r->usage_count }}</td>
                            <td>
                                @if ($r->is_active)
                                    <span class="badge bg-success">{{ $lang->write('Active') }}</span>
                                @else
                                    <span class="badge bg-secondary">{{ $lang->write('Inactive') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-secondary" onclick='openCatalogEdit(@json($r))'>{{ $lang->write('Edit') }}</button>
                                @if ($r->is_active)
                                    <button class="btn btn-sm btn-outline-danger" onclick="deactivateCatalogItem({{ $r->id }})">{{ $lang->write('Deactivate') }}</button>
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

{{-- Catalog edit modal --}}
<div class="modal fade" id="catEditModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="catEditTitle">{{ $lang->write('New product') }}</h5>
        <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.6" /></svg>
        </button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="cat_id" value="">
        <div class="row g-3">
          <div class="col-12 col-md-8">
            <label class="form-label">{{ $lang->write('Name') }} <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="cat_name" maxlength="191">
          </div>
          <div class="col-6 col-md-4">
            <label class="form-label">{{ $lang->write('Code') }}</label>
            <input type="text" class="form-control" id="cat_code" maxlength="64">
          </div>
          <div class="col-12">
            <label class="form-label">{{ $lang->write('Description') }}</label>
            <textarea class="form-control" id="cat_description" rows="2"></textarea>
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label">{{ $lang->write('Unit') }}</label>
            <input type="text" class="form-control" id="cat_unit" value="pcs" maxlength="32">
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label">{{ $lang->write('Default cost') }} <span class="text-danger">*</span></label>
            <input type="number" step="any" class="form-control" id="cat_default_unit_cost">
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label">{{ $lang->write('Cost currency') }} <span class="text-danger">*</span></label>
            <select class="form-select" id="cat_default_unit_cost_currency">
              @foreach ($data->currencies as $c)
                <option value="{{ $c['code'] }}">{{ $c['text'] }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label">{{ $lang->write('Default price') }} <span class="text-danger">*</span></label>
            <input type="number" step="any" class="form-control" id="cat_default_unit_price">
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label">{{ $lang->write('Weight (KG)') }}</label>
            <input type="number" step="any" class="form-control" id="cat_default_weight_kg">
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label">CBM</label>
            <input type="number" step="any" class="form-control" id="cat_default_cbm">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ $lang->write('Close') }}</button>
        <button type="button" class="btn btn-primary" onclick="saveCatalogItem()">{{ $lang->write('Save') }}</button>
      </div>
    </div>
  </div>
</div>

<script>
function openCatalogEdit(row) {
    document.getElementById('cat_id').value          = row ? row.id : '';
    document.getElementById('cat_name').value        = row ? row.name : '';
    document.getElementById('cat_code').value        = row && row.code ? row.code : '';
    document.getElementById('cat_description').value = row && row.description ? row.description : '';
    document.getElementById('cat_unit').value        = row && row.unit ? row.unit : 'pcs';
    document.getElementById('cat_default_unit_cost').value          = row ? row.default_unit_cost : '';
    document.getElementById('cat_default_unit_cost_currency').value = row && row.default_unit_cost_currency ? row.default_unit_cost_currency : 'usd';
    document.getElementById('cat_default_unit_price').value         = row ? row.default_unit_price : '';
    document.getElementById('cat_default_weight_kg').value          = row && row.default_weight_kg ? row.default_weight_kg : '';
    document.getElementById('cat_default_cbm').value                = row && row.default_cbm ? row.default_cbm : '';
    document.getElementById('catEditTitle').textContent = row ? 'Edit product' : 'New product';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('catEditModal')).show();
}

function saveCatalogItem() {
    const fd = new FormData();
    fd.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
    const id = document.getElementById('cat_id').value;
    if (id) fd.append('id', id);
    [
        'name','code','description','unit',
        'default_unit_cost','default_unit_cost_currency','default_unit_price',
        'default_weight_kg','default_cbm'
    ].forEach(k => {
        const v = document.getElementById('cat_' + k).value;
        if (v !== '' && v !== null) fd.append(k, v);
    });
    fetch('/sourcing/catalog/save', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.type === 'success') window.location.reload();
            else alert(res.message || 'Failed');
        });
}

function deactivateCatalogItem(id) {
    if (!confirm('Deactivate this catalog item? Existing proforma rows are unaffected.')) return;
    const fd = new FormData();
    fd.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
    fd.append('id', id);
    fetch('/sourcing/catalog/delete', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.type === 'success') window.location.reload();
            else alert(res.message || 'Failed');
        });
}
</script>

@endsection
