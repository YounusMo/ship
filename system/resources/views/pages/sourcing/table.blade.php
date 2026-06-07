@php
    use App\Http\Controllers\langController;
    $lang = new langController();

    $badgeClass = [
        'open'      => 'bg-secondary',
        'searching' => 'bg-info',
        'quoted'    => 'bg-primary',
        'accepted'  => 'bg-warning',
        'fulfilled' => 'bg-success',
        'canceled'  => 'bg-dark',
    ];
@endphp
@if (count($rows) < 1)
    <img src="{{asset('images/empty.png')}}" class="d-block mx-auto" style="width: 500px ; opacity:.5">
    @php return @endphp
@endif
<input type="hidden" class="count" value="{{$count}}">
<table class="table table-sm align-middle">
    <thead>
        <tr>
            <th style="width:32px;">
                <input type="checkbox" id="select_all_proformas" class="form-check-input">
            </th>
            <th>{{$lang->write('Number')}}</th>
            <th>{{$lang->write('Client')}}</th>
            <th>{{$lang->write('Title')}}</th>
            <th>{{$lang->write('Branch')}}</th>
            <th>{{$lang->write('Status')}}</th>
            <th class="text-end">{{$lang->write('Commission')}}</th>
            <th>{{$lang->write('Created at')}}</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $r)
            <tr>
                <td><input type="checkbox" class="form-check-input proforma-select" value="{{ $r->id }}"></td>
                <td><code>{{$r->request_number}}</code></td>
                <td>
                    @if ($r->client_code) <span class="text-muted">{{$r->client_code}}</span> — @endif
                    {{$r->client_name}}
                </td>
                <td>{{$r->title}}</td>
                <td>{{$r->branch_name ?? '—'}}</td>
                <td>
                    <span class="badge {{$badgeClass[$r->status] ?? 'bg-secondary'}}">
                        {{$lang->write('sourcing.status.' . $r->status)}}
                    </span>
                </td>
                <td class="text-end">
                    @if ($r->commission_amount)
                        {{ number_format((float) $r->commission_amount, 2) }}
                        <span class="text-muted">{{strtoupper($r->commission_currency)}}</span>
                    @else
                        —
                    @endif
                </td>
                <td>{{ substr($r->created_at, 0, 16) }}</td>
                <td class="text-end">
                    <a class="btn btn-sm btn-outline-primary" href="{{url('/sourcing/' . $r->id)}}">{{$lang->write('Open')}}</a>
                    @if (empty($r->deleted_at))
                        <button class="btn btn-sm btn-outline-secondary" onclick="trashProforma({{ $r->id }})" title="{{ $lang->write('Move to trash') }}">🗑</button>
                    @else
                        <button class="btn btn-sm btn-outline-success" onclick="restoreProforma({{ $r->id }})" title="{{ $lang->write('Restore') }}">↺</button>
                        @if ($r->status === 'canceled')
                            <button class="btn btn-sm btn-outline-danger" onclick="destroyProforma({{ $r->id }})" title="{{ $lang->write('Hard-delete') }}">⨯</button>
                        @endif
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
