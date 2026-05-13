@php
    use App\Http\Controllers\langController;
    use App\Http\Controllers\dataController;

    $lang  = new langController();
    $data  = new dataController();

    function _fmt($v) {
        return number_format(floatval($v), 2, '.', ',');
    }
@endphp

@if ($onlyDiff && empty($rows))
    <div class="alert alert-success">
        {{ $lang->write('No discrepancies found on this page. Page through to inspect the rest.') }}
    </div>
@endif

<table class="table table-sm align-middle">
    <thead>
        <tr>
            <th>{{ $lang->write('Code') }}</th>
            <th>{{ $lang->write('Name') }}</th>
            @foreach (['usd', 'eur', 'den', 'cny'] as $cur)
                <th class="text-end">{{ strtoupper($cur) }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['code'] }}</td>
                <td>{{ $row['name'] }}</td>
                @foreach (['usd', 'eur', 'den', 'cny'] as $cur)
                    @php $d = $row['diffs'][$cur] ?? null; @endphp
                    <td class="text-end">
                        @if ($d)
                            <div class="text-danger" title="{{ $lang->write('Stored vs computed') }}">
                                <strong>{{ _fmt($d['diff']) }}</strong>
                            </div>
                            <small class="text-muted d-block">
                                {{ $lang->write('Stored') }}: {{ _fmt($d['stored']) }}<br>
                                {{ $lang->write('Computed') }}: {{ _fmt($d['computed']) }}
                            </small>
                        @else
                            <span class="text-muted">{{ _fmt($row['stored'][$cur] ?? 0) }}</span>
                        @endif
                    </td>
                @endforeach
            </tr>
        @endforeach
        @if (empty($rows))
            <tr>
                <td colspan="6" class="text-center text-muted">
                    {{ $lang->write('Nothing to show.') }}
                </td>
            </tr>
        @endif
    </tbody>
    @if (array_filter($totalDrift, fn($v) => abs($v) > 0.01))
        <tfoot>
            <tr class="fw-bold">
                <td colspan="2">{{ $lang->write('Total drift (this page)') }}</td>
                @foreach (['usd', 'eur', 'den', 'cny'] as $cur)
                    <td class="text-end {{ abs($totalDrift[$cur]) > 0.01 ? 'text-danger' : '' }}">
                        {{ _fmt($totalDrift[$cur]) }}
                    </td>
                @endforeach
            </tr>
        </tfoot>
    @endif
</table>

<div class="d-flex justify-content-center">
    {{ $clients->links() }}
</div>
