@php
    use App\Http\Controllers\langController;
    $lang = new langController();

    function _fmt_br($v) {
        return number_format(floatval($v), 2, '.', ',');
    }
@endphp

@if ($onlyDiff && empty($rows))
    <div class="alert alert-success">
        {{ $lang->write('All branches reconcile cleanly.') }}
    </div>
@endif

<table class="table table-sm align-middle">
    <thead>
        <tr>
            <th>{{ $lang->write('Branch') }}</th>
            @foreach (['usd', 'eur', 'den', 'cny'] as $cur)
                <th class="text-end">{{ strtoupper($cur) }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>
                    {{ $row['name'] }}
                    @if (!empty($row['name_en']) && $row['name_en'] !== $row['name'])
                        <small class="text-muted d-block">{{ $row['name_en'] }}</small>
                    @endif
                </td>
                @foreach (['usd', 'eur', 'den', 'cny'] as $cur)
                    @php $d = $row['diffs'][$cur] ?? null; @endphp
                    <td class="text-end">
                        @if ($d)
                            <div class="text-danger" title="{{ $lang->write('Stored vs computed') }}">
                                <strong>{{ _fmt_br($d['diff']) }}</strong>
                            </div>
                            <small class="text-muted d-block">
                                {{ $lang->write('Stored') }}: {{ _fmt_br($d['stored']) }}<br>
                                {{ $lang->write('Computed') }}: {{ _fmt_br($d['computed']) }}
                            </small>
                        @else
                            <span class="text-muted">{{ _fmt_br($row['stored'][$cur] ?? 0) }}</span>
                        @endif
                    </td>
                @endforeach
            </tr>
        @endforeach
        @if (empty($rows))
            <tr>
                <td colspan="5" class="text-center text-muted">
                    {{ $lang->write('Nothing to show.') }}
                </td>
            </tr>
        @endif
    </tbody>
    @if (array_filter($totalDrift, fn($v) => abs($v) > 0.01))
        <tfoot>
            <tr class="fw-bold">
                <td>{{ $lang->write('Total drift') }}</td>
                @foreach (['usd', 'eur', 'den', 'cny'] as $cur)
                    <td class="text-end {{ abs($totalDrift[$cur]) > 0.01 ? 'text-danger' : '' }}">
                        {{ _fmt_br($totalDrift[$cur]) }}
                    </td>
                @endforeach
            </tr>
        </tfoot>
    @endif
</table>
