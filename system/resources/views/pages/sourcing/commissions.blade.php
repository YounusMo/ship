@php
    use App\Http\Controllers\langController;
    $lang = new langController();
    if (!in_array(auth()->user()->type, ['admin'])) {
        abort(403, 'Unauthorized');
    }
@endphp
@extends('layout')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">
            <a href="{{url('/sourcing')}}" class="text-muted text-decoration-none">
                {{ $lang->write('Sourcing requests') }} ›
            </a>
            {{ $lang->write('Commissions') }}
        </h1>
        <div class="page-subtitle">
            {{ $lang->write('CoA 4020 (Sourcing commission revenue) activity') }}
        </div>
    </div>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-auto">
        <label class="form-label small text-muted">{{$lang->write('From')}}</label>
        <input type="date" name="from" value="{{$from}}" class="form-control form-control-sm">
    </div>
    <div class="col-auto">
        <label class="form-label small text-muted">{{$lang->write('To')}}</label>
        <input type="date" name="to" value="{{$to}}" class="form-control form-control-sm">
    </div>
    <div class="col-auto align-self-end">
        <button class="btn btn-primary btn-sm">{{$lang->write('Apply')}}</button>
    </div>
</form>

<div class="row mb-3">
    @foreach ($totals as $t)
        <div class="col-12 col-md-3 mb-2">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">{{ strtoupper($t->currency) }}</div>
                    <div class="h4 mb-0">{{ number_format((float) $t->total, 2) }}</div>
                </div>
            </div>
        </div>
    @endforeach
    @if (count($totals) < 1)
        <div class="col-12">
            <div class="alert alert-info mb-0">
                {{ $lang->write('No sourcing commissions posted in this period.') }}
            </div>
        </div>
    @endif
</div>

<div class="card">
    <div class="card-body">
        <h5 class="card-title">{{$lang->write('Posted commissions')}}</h5>
        @if (count($rows) < 1)
            <p class="text-muted mb-0">{{$lang->write('Nothing in this period.')}}</p>
        @else
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>{{$lang->write('Posted at')}}</th>
                        <th>{{$lang->write('Number')}}</th>
                        <th>{{$lang->write('Client')}}</th>
                        <th>{{$lang->write('Title')}}</th>
                        <th class="text-end">{{$lang->write('Commission')}}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($rows as $r)
                    <tr>
                        <td>{{ substr($r->commission_posted_at, 0, 16) }}</td>
                        <td><code>{{$r->request_number}}</code></td>
                        <td>{{ $r->client_code }} — {{ $r->client_name }}</td>
                        <td>{{$r->title}}</td>
                        <td class="text-end">
                            {{ number_format((float) $r->commission_amount, 2) }}
                            <span class="text-muted">{{ strtoupper($r->commission_currency) }}</span>
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="{{url('/sourcing/' . $r->id)}}">{{$lang->write('Open')}}</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

@endsection
