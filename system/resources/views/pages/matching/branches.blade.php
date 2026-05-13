@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\branchesController;
    use App\Http\Controllers\langController;

    $lang = new langController();
    $dataController = new dataController();
    $branchesController = new branchesController();

    $currencies = $dataController->currencies;
    
    $tot_usd = 0;
    $tot_cny = 0;
    $tot_eur = 0;
    $tot_den = 0;
@endphp

<div class='mb-3'>
    <table class="table" style="width:100%">
        <thead>
            <tr>
                <th colspan="5" class="text-center h4" style="background-color: #5b5b5b;color:white"><strong>{{$lang->write('Branches')}}</strong></th>
            </tr>
        </thead>
        <thead>
            <tr>
                <th style="text-align: left;background-color: #ebebeb;">{{$lang->write('Branch')}}</th>
                <th style="text-align: left;background-color: #ebebeb;">{{$lang->write('USD')}}</th>
                <th style="text-align: left;background-color: #ebebeb;">{{$lang->write('RMB')}}</th>
                <th style="text-align: left;background-color: #ebebeb;">{{$lang->write('DEN')}}</th>
                <th style="text-align: left;background-color: #ebebeb;">{{$lang->write('EUR')}}</th>
            </tr>
        </thead>
        <tbody>
            @php
                $branches = DB::table('branches')->whereNot('id',12)->orderBy('id','desc')->get();
            @endphp
            @foreach ($branches as $branch)
                @php
                    $bls = $branchesController->search_br_balance($branch->id , $from ,$to);

                    $tot_usd += $bls[0];
                    $tot_cny += $bls[2];
                    $tot_eur += $bls[1];
                    $tot_den += $bls[3];
                @endphp
                <tr>
                    <td>
                        @php
                            switch (auth()->user()->lang) {
                                case 'ar':
                                    echo $branch->name;
                                break;
                                case 'en':
                                    echo $branch->name_en;
                                break;
                                case 'zh':
                                    echo $branch->name_zh;
                                break;
                            }
                        @endphp
                    </td>
                    <td>{{number_format($bls[0], null, '.', ',')}}</td>
                    <td>{{number_format($bls[2], null, '.', ',')}}</td>
                    <td>{{number_format($bls[3], null, '.', ',')}}</td>
                    <td>{{number_format($bls[1], null, '.', ',')}}</td>
                </tr>
            @endforeach
            <tr>
                <td style="background-color: #dedede"><strong>{{$lang->write('Total')}} :</strong></td>
                <td style="background-color: #dedede"><strong>{{number_format($tot_usd, null, '.', ',')}}</strong></td>
                <td style="background-color: #dedede"><strong>{{number_format($tot_cny, null, '.', ',')}}</strong></td>
                <td style="background-color: #dedede"><strong>{{number_format($tot_den, null, '.', ',')}}</strong></td>
                <td style="background-color: #dedede"><strong>{{number_format($tot_eur, null, '.', ',')}}</strong></td>
            </tr>
        </tbody>
    </table>

    {{-- <table class="table"></table> --}}
</div>