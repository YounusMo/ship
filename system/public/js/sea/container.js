//---------------------------------------------------------
$('.container_data .inp').on('keyup change',function(){
    let id = $(this).data('id')
    let kg        = parseFloat($(`.container_data .inp[data-name="kg"][data-id="${id}"]`).val())
    let cbm       = parseFloat($(`.container_data .inp[data-name="cbm"][data-id="${id}"]`).val())
    let price     = parseFloat($(`.container_data .inp[data-name="price"][data-id="${id}"]`).val())
    let plus      = parseFloat($(`.container_data .inp[data-name="plus"][data-id="${id}"]`).val())
    let unit      = $(`.container_data .inp[data-name="unit"][data-id="${id}"]`).val()
    let currency  = $(`.container_data .inp[data-name="currency"][data-id="${id}"]`).val()
    let payment   = $(`.container_data .inp[data-name="payment"][data-id="${id}"]`).val()
    let payment_old   = $(`.container_data .inp[data-name="payment"][data-id="${id}"]`).data('old')
    let branch = $(`.container_data .inp[data-name="branch_${id}"]`).val()
    let total = 0;

    if(unit === 'cbm'){
        total = cbm * price;
    }

    if(unit === 'kg'){
        total = kg * price;
    }
    
    if(plus > 0){
        total += plus;
    }

    $(`.cur[data-id="${id}"]`).text(get_cur(currency,'symbol'))
    $(`.total[data-id="${id}"]`).text(numberFormat(total))
    
    if(payment !== payment_old){

        if(! branch){
            showErr(langContent['Select branch']);
            $(`.container_data .inp[data-name="payment"][data-id="${id}"]`).val(payment_old)
            return;
        }

        ask(langContent['Do you want to compete the payment ?'] , function(){
            $(`.container_data .inp[data-id="${id}"]`).attr('disabled',true)
            $(`.container_data .branch_${id} button`).attr('disabled',true)
            $(`.container_data .branch_${id} button`).removeClass('form-select')
            $(`.container_data .branch_${id} button`).addClass('form-control')

            saveContainer()
        } , ()=>{
            $(`.container_data .inp[data-name="payment"][data-id="${id}"]`).val(payment_old)
        })
    }else{
        saveContainer()
    }

    
})
//---------------------------------------------------------
sys_selector('#payment .branch_',function(){
    $('#payment .branch_ button').attr('disabled',true)
})
//---------------------------------------------------------
function showPay(id_){
    id = id_
    $('#payment').modal('show')

    let data = {
        payment  : $(`.payment .inp[data-name="payment"][data-id="${id}"]`).val(),
        branch   : $(`.payment .inp[data-name="branch"]`).val(),
    }

    $(`#container_data .inp[data-name="payment"]`).val(data.payment)
    $(`#container_data .inp[data-name="branch"]`).val(data.branch)

    // $(`#payment .branch_ .sys_selector button`).attr('disabled',false)

    $(`#payment .inp`).val('')
    reset_sys_selector();
}
//---------------------------------------------------------
$(`#payment .inp[data-name="payment"]`).on('change',function(){
    
    if($(this).val() === 'pay2'){
        $(`#payment .branch_ .sys_selector button`).attr('disabled',false)
    }else{
        $(`#payment .branch_ .sys_selector button`).attr('disabled',true)
        reset_sys_selector();
    }
})
//---------------------------------------------------------
function save_pay(){

    let payment = $(`#payment .inp[data-name="payment"]`).val()
    let branch  = $(`#payment .inp[data-name="branch"]`).val()

    let ok = true;

    if(!payment && !branch){
        ok = false;
    }
    if(payment === 'pay2'){
        if(!payment || !branch){
            ok = false;
        }
    }


    if(payment === 'pay'){
        if(!payment){
            ok = false;
        }
    }

    if(!ok){
        showErr(langContent['Insert required data']) 
        return;
    }

    $(`.container_data .inp[data-id="${id}"][data-name="payment"]`).val(payment)
    $(`.container_data .inp[data-id="${id}"][data-name="branch"]`).val(branch)

    $(`.tr_item[data-id=${id}] button`).attr('disabled',true)
    $(`.tr_item[data-id=${id}] .cancel`).attr('disabled',false)
    $(`.tr_item[data-id=${id}] .delivery`).attr('disabled',false)
    
    $('#payment').modal('hide')
    saveContainer()

    setTimeout(() => {
        window.location.reload()
    }, 100);
}
//---------------------------------------------------------
function showContainer_data(id_){
    id = id_
    $('#container_data').modal('show')

    let data = {
        id         : id,
        number     : $(`.container_data .inp[data-name="number"][data-id="${id}"]`).val(),
        kg         : $(`.container_data .inp[data-name="kg"][data-id="${id}"]`).val(),
        cbm        : $(`.container_data .inp[data-name="cbm"][data-id="${id}"]`).val(),
        price      : $(`.container_data .inp[data-name="price"][data-id="${id}"]`).val(),
        new_price  : $(`.container_data .inp[data-name="new_price"][data-id="${id}"]`).val(),
        plus       : $(`.container_data .inp[data-name="plus"][data-id="${id}"]`).val(),
        currency   : $(`.container_data .inp[data-name="currency"][data-id="${id}"]`).val(),
    }

    $(`#container_data .inp[data-name="number"]`).val(data.number)
    $(`#container_data .inp[data-name="kg"]`).val(data.kg)
    $(`#container_data .inp[data-name="cbm"]`).val(data.cbm)
    $(`#container_data .inp[data-name="price"]`).val(data.price)
    $(`#container_data .inp[data-name="new_price"]`).val(data.new_price)
    $(`#container_data .inp[data-name="plus"]`).val(data.plus)
    $(`#container_data .inp[data-name="currency"]`).val(data.currency)
}
//---------------------------------------------------------
function save_data(){

    let ok = true;

    $(`#container_data .req`).each(function(){
        if($(this).val() === ''){
            ok = false;
        }
    })


    if(!ok){
        showErr(langContent['Insert required data']) 
        return;
    }

    let data = {
        number    : $(`#container_data .inp[data-name="number"]`).val(),
        kg        : $(`#container_data .inp[data-name="kg"]`).val(),
        cbm       : $(`#container_data .inp[data-name="cbm"]`).val(),
        price     : $(`#container_data .inp[data-name="price"]`).val(),
        new_price : $(`#container_data .inp[data-name="new_price"]`).val(),
        plus      : $(`#container_data .inp[data-name="plus"]`).val(),
        currency  : $(`#container_data .inp[data-name="currency"]`).val(),
    }

    
    $(`.container_data .inp[data-id="${id}"][data-name="number"]`).val(data.number)
    $(`.container_data .inp[data-id="${id}"][data-name="kg"]`).val(data.kg)
    $(`.container_data .inp[data-id="${id}"][data-name="cbm"]`).val(data.cbm)
    $(`.container_data .inp[data-id="${id}"][data-name="price"]`).val(data.price)
    $(`.container_data .inp[data-id="${id}"][data-name="new_price"]`).val(data.new_price)
    $(`.container_data .inp[data-id="${id}"][data-name="plus"]`).val(data.plus)
    $(`.container_data .inp[data-id="${id}"][data-name="currency"]`).val(data.currency)
    
    $('#container_data').modal('hide')
    saveContainer()
}
//---------------------------------------------------------
function saveContainer(){

    let data = [];

    $('.tr_item').each(function(){
        let id = $(this).data('id')

        data.push({
            id     : id,
            number : $(`.container_data .inp[data-name="number"][data-id="${id}"]`).val(),
            kg     : $(`.container_data .inp[data-name="kg"][data-id="${id}"]`).val(),
            cbm    : $(`.container_data .inp[data-name="cbm"][data-id="${id}"]`).val(),
            price  : $(`.container_data .inp[data-name="price"][data-id="${id}"]`).val(),
            new_price  : $(`.container_data .inp[data-name="new_price"][data-id="${id}"]`).val(),
            plus   : $(`.container_data .inp[data-name="plus"][data-id="${id}"]`).val(),
            currency   : $(`.container_data .inp[data-name="currency"][data-id="${id}"]`).val(),
            payment    : $(`.container_data .inp[data-name="payment"][data-id="${id}"]`).val(),
            branch     : $(`.container_data .inp[data-name="branch"][data-id="${id}"]`).val(),
            payment_pending   : $(`.container_data .inp[data-name="payment_pending"][data-id="${id}"]`).val(),
        })
    })
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('data', JSON.stringify(data));
    formData.append('container_id', $('.container_id').val());
    
    tableLoader('show','.main-table');

    $.ajax({
        url :'/shipping/sea/save_container',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            // console.log(e)
        }
    })
}
//---------------------------------------------------------
function delivery(id){
    var formData = new FormData();

    let page_title = $('title').text()

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('id',id);
    
    $.ajax({
        url :'/shipping/sea/print_delivery',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            $("#print_data").html(e)
            
            printJS({printable:'print_data',type:'html'})

            setTimeout(() => {
                // document.title = page_title
                $("#print_data").empty()
            }, 500);
        },
        error:e=>{
            console.log(e)
            hideLoader()
            showErr('Somthing went wrong')
        }
    })
}
//---------------------------------------------------------
function cancel_in_container(id){
    ask(langContent['Do you want to cancel ?'],()=>{
        var formData = new FormData();

        formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
        formData.append('id',id);
        
        $.ajax({
            url :'/shipping/sea/cancel_in_container',
            type:'POST',
            contentType: false,
            processData: false,
            async:true,
            data:formData,
            success:e=>{
                window.location.reload();
            },
            error:e=>{
                console.log(e)
                hideLoader()
                showErr('Somthing went wrong')
            }
        })
    },()=>false)
}
//---------------------------------------------------------
$('.money').on('change keyup',function(){
    let val = $(this).val()
    val = parseInt(val.replace(/[^0-9]/g, '')) || 0;
    $(this).val(val);
});
//---------------------------------------------------------
function showConfirmPay(id){
    ask(langContent['Do you want to confirm the payment ?'],()=>{
        $(`.container_data .inp[data-id="${id}"][data-name="payment_pending"]`).val('confirmed')
        saveContainer()

        setTimeout(() => {
            window.location.reload();
        }, 100);
    });
}
//---------------------------------------------------------

