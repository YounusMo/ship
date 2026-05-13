@php
    use App\Http\Controllers\langController;
    $lang = new langController();
@endphp

<div class="filter-options" style="display:none">
    <select data-fill="action">
        @foreach ($actions as $a)
            <option value="{{ $a }}">{{ $a }}</option>
        @endforeach
    </select>
    <select data-fill="target_table">
        @foreach ($tables as $t)
            <option value="{{ $t }}">{{ $t }}</option>
        @endforeach
    </select>
</div>

<table class="table table-sm">
    <thead>
        <tr>
            <th>#</th>
            <th>{{ $lang->write('When') }}</th>
            <th>{{ $lang->write('Who') }}</th>
            <th>{{ $lang->write('Action') }}</th>
            <th>{{ $lang->write('Target') }}</th>
            <th>{{ $lang->write('Context') }}</th>
            <th>{{ $lang->write('IP') }}</th>
            <th>{{ $lang->write('Details') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($get as $row)
            <tr>
                <td>{{ $row->id }}</td>
                <td>{{ $row->created_at }}</td>
                <td>
                    {{ $users[$row->user_id] ?? '#' . ($row->user_id ?? '?') }}
                    @if ($row->user_type)
                        <small class="text-muted d-block">{{ $row->user_type }}</small>
                    @endif
                </td>
                <td><span class="badge bg-secondary">{{ $row->action }}</span></td>
                <td>
                    {{ $row->target_table }}
                    @if ($row->target_id)
                        <small class="text-muted">#{{ $row->target_id }}</small>
                    @endif
                </td>
                <td>{{ $row->context }}</td>
                <td><small class="text-muted">{{ $row->ip }}</small></td>
                <td>
                    @if ($row->payload)
                        <details>
                            <summary class="text-primary" style="cursor:pointer">{{ $lang->write('Show') }}</summary>
                            <pre class="mb-0" style="white-space:pre-wrap;font-size:11px">{{ json_encode(json_decode($row->payload, true), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
                        </details>
                    @endif
                </td>
            </tr>
        @endforeach
        @if (!count($get))
            <tr><td colspan="8" class="text-center text-muted">{{ $lang->write('No records yet') }}</td></tr>
        @endif
    </tbody>
</table>

<div class="d-flex justify-content-center">
    {{ $get->links() }}
</div>
