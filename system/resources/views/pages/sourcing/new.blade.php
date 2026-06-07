@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\langController;

    $lang           = new langController();
    $dataController = new dataController();

    $currencies = $dataController->currencies;

    $branches = DB::table('branches')->where('deleted', 'false');
    if (in_array(auth()->user()->type, ['branch_admin'])) {
        $branches = $branches->where('id', auth()->user()->branch);
    }
    $branches = $branches->orderBy('id', 'DESC')->get();

    $clients = DB::table('clients')->where('deleted', 'false');
    if (in_array(auth()->user()->type, ['branch_admin'])) {
        $clients = $clients->where('branch', auth()->user()->branch);
    }
    $clients = $clients->orderBy('id', 'DESC')->limit(2000)->get();
@endphp
<div class="modal fade" id="new" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{$lang->write('New sourcing request')}}</h5>
                <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                        <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.3" />
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="mb-3 col-12 col-md-6">
                        <label>{{$lang->write('Client')}} : *</label>
                        <select class="form-select inp req" data-name="client_id">
                            <option value="">{{$lang->write('Select')}}</option>
                            @foreach ($clients as $c)
                                <option value="{{$c->id}}">{{$c->code}} — {{$c->name}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3 col-12 col-md-6">
                        <label>{{$lang->write('Branch')}} :</label>
                        <select class="form-select inp" data-name="branch_id">
                            <option value="">{{$lang->write('Select')}}</option>
                            @foreach ($branches as $b)
                                <option value="{{$b->id}}">{{$lang->branch($b->id)}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3 col-12">
                        <label>{{$lang->write('Title')}} : *</label>
                        <input type="text" class="form-control inp req" data-name="title" placeholder="{{$lang->write('Short label, e.g. 50 LED bulbs from Yiwu')}}">
                    </div>
                    <div class="mb-3 col-12">
                        <label>{{$lang->write('Description')}} :</label>
                        <textarea class="form-control inp" data-name="description" rows="3" placeholder="{{$lang->write('Specs, brand preferences, links, photos')}}"></textarea>
                    </div>
                    <div class="mb-3 col-6 col-md-3">
                        <label>{{$lang->write('Target quantity')}} :</label>
                        <input type="number" step="any" class="form-control inp" data-name="target_quantity">
                    </div>
                    <div class="mb-3 col-6 col-md-3">
                        <label>{{$lang->write('Unit')}} :</label>
                        <input type="text" class="form-control inp" data-name="target_unit" placeholder="pcs / kg / box">
                    </div>
                    <div class="mb-3 col-6 col-md-3">
                        <label>{{$lang->write('Target unit price')}} :</label>
                        <input type="number" step="any" class="form-control inp" data-name="target_unit_price">
                    </div>
                    <div class="mb-3 col-6 col-md-3">
                        <label>{{$lang->write('Currency')}} : *</label>
                        <select class="form-select inp req" data-name="currency">
                            <option value="">{{$lang->write('Select')}}</option>
                            @foreach ($currencies as $cur)
                                <option value="{{$cur['code']}}">{{$cur['text']}}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$lang->write('Close')}}</button>
                <button type="button" class="btn btn-primary create_btn" onclick="create()">{{$lang->write('Create')}}</button>
            </div>
        </div>
    </div>
</div>
