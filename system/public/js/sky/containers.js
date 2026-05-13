//---------------------------------------------------------
function loadcontainers(){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('search', $('.search').val());
    formData.append('showDeleted', showDeleted);
    
    tableLoader('show','.main-table');

    $.ajax({
        url :'/shipping/sky/load_containers?page='+page,
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            tableLoader('hide','.main-table',e,100,()=>{
                $('.table_counter').text($('.count').val())
                afterLoad()

                $('.status').on('change',function(){
                    let id = $(this).data('id')
                    let val = $(this).val()
                    change_status(id,val)
                })
            });
        }
    })
}
//---------------------------------------------------------------------------------------
function loadCanceledcontainers(){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('search', $('.search').val());
    formData.append('showDeleted', showDeleted);
    
    tableLoader('show','.main-table');

    $.ajax({
        url :'/shipping/sky/load_canceled_containers?page='+page,
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            tableLoader('hide','.main-table',e,100,()=>{
                $('.table_counter').text($('.count').val())
                afterLoad()

                $('.status').on('change',function(){
                    let id = $(this).data('id')
                    let val = $(this).val()
                    change_status(id,val)
                })
            });
        }
    })
}
//---------------------------------------------------------------------------------------
function printContainer(id){
    var formData = new FormData();

    const token = $('meta[name="csrf-token"]').attr('content');
  
    formData.append('_token', token);
    formData.append('id', id);
 
    $.ajax({
        url :'/shipping/sky/print_container',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            $('#print_data').html(e)
            let page_title = $('title').text()
            let title    = $('#all_reports .client_data[data-name="title"]').val()
            
            document.title = `${title}`;
    
            printJS({printable:'print_data',type:'html'})

            setTimeout(() => {
                document.title = page_title
            }, 500);
        },
        error:e=>{
            console.log(e)
            hideLoader()
            showErr('Somthing went wrong')
        }
    })
}
//---------------------------------------------------------------------------------------
function complete_sea_withdraw(){
     var formData = new FormData();

    const token = $('meta[name="csrf-token"]').attr('content');
    const id = $('#sea_withdraw .branch_selector .inp[data-name="branch"]').val();
    const currency = $('#sea_withdraw .inp[data-name="currency"]').val();
    const value = $('#sea_withdraw .inp[data-name="value"]').val();
    const purpose = $('#sea_withdraw .inp[data-name="purpose"]').val();
    const container = $('#sea_withdraw .inp[data-name="container"]').val();
    const notes = $('#sea_withdraw .inp[data-name="notes"]').val();
    const transactionNumber = $('#sea_withdraw .inp[data-name="transaction_number"]').val();
    const payment_supplier  = $('#sea_withdraw .inp[data-name="payment_supplier"]').val()

    formData.append('_token', token);
    formData.append('branch', id);
    formData.append('value', value);
    formData.append('currency', currency);
    formData.append('notes', notes); 
    formData.append('purpose', purpose); 
    formData.append('container', container); 
    formData.append('payment_supplier', payment_supplier); 
    formData.append('transaction_number', transactionNumber);;
    
    if(!currency || !value || !purpose || !container || (!payment_supplier && purpose === 'container_fee_value')){
        showErr(langContent['Insert required data']) 
        return;
    }
    showLoader()

    $('.sea_withdraw_btn').attr('disabled',true)

    $.ajax({
        url :'/company/sky_withdraw',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            $('.sea_withdraw_btn').attr('disabled',false)
            if(e.type === 'balance_err'){
                makeAlert(langContent['Balance is not enough'],'err')
                return;
            }
            
            hideLoader(langContent['Completed successfully'])
            $('#sea_withdraw').modal('hide')


            reset_sys_selector();

            loadcontainers();
        },
        error:e=>{
            console.log(e)
            hideLoader()
            showErr('Somthing went wrong')
            $('.sea_withdraw_btn').attr('disabled',false)
        }
    })
}
//---------------------------------------------------------------------------------------
function cancelContainer(id){
    ask(langContent['Do you want to cancel ?'],function(){
        var formData = new FormData();

        const token = $('meta[name="csrf-token"]').attr('content');
    
        formData.append('_token', token);
        formData.append('id', id);
    
        $.ajax({
            url :'/shipping/sky/cancel_container',
            type:'POST',
            contentType: false,
            processData: false,
            async:true,
            data:formData,
            success:e=>{
            loadcontainers();
            makeAlert(langContent['Completed successfully'],'success');
            },
            error:e=>{
                console.log(e)
                hideLoader()
                showErr('Somthing went wrong')
            }
        })
    },()=>false)
}
//---------------------------------------------------------------------------------------
function change_status(id,val){
    var formData = new FormData();

    const token = $('meta[name="csrf-token"]').attr('content');
  
    formData.append('_token', token);
    formData.append('id', id);
    formData.append('val', val);
 
    $.ajax({
        url :'/shipping/sky/change_status',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
           
        },
        error:e=>{
            console.log(e)
            hideLoader()
            showErr('Somthing went wrong')
        }
    })
}
//---------------------------------------------------------------------------------------
sys_selector('#sea_withdraw .branch_selector')
sys_selector('#new_custom_container .branch_selector')
//---------------------------------------------------------------------------------------
$(`#new_custom_container .inp[data-name="payment"] , #new_custom_container .inp[data-name="payment_supplier"]`).on('change',function(){
    let payment = $(`#new_custom_container .inp[data-name="payment"]`).val();
    let payment_supplier = $(`#new_custom_container .inp[data-name="payment_supplier"]`).val();

    if(payment === 'pay2' || payment_supplier === 'pay2'){
        $(`#new_custom_container .branch_selector .sys_selector button`).attr('disabled',false)
    }else{
        $(`#new_custom_container .branch_selector .sys_selector button`).attr('disabled',true)
        $(`#new_custom_container .inp[data-name="branch"]`).val('')
        $(`#new_custom_container .branch_selector .sys_selector button`).text(langContent['Select'])
    }
})
//---------------------------------------------------------------------------------------
$('#customs .inp[data-name="payment"]').on('change',function(){
    if($(this).val() === 'pay2'){
        $(`#customs .branch_ .sys_selector button`).attr('disabled',false)
    }else{
        $(`#customs .branch_ .sys_selector button`).attr('disabled',true)
    }
})
//---------------------------------------------------------------------------------------
function showCustoms(id_){
    
    $('#customs .inp').val('')
    $('#customs').modal('show')

    reset_sys_selector()

    $('#customs').modal('show')
    $('#customs .inp[data-name="container"]').val(id_)

    $(`#customs .branch_ .sys_selector button`).attr('disabled',true)
    $(`#customs .inp[data-name="branch"]`).val('')
    $(`#customs .branch_ .sys_selector button`).text(langContent['Select'])

    $('#customs .inp[data-name="pay_for"]').val('export_port_customs_fee')
    $('#customs .custom_').removeClass('d-none')
    $('#customs .custom_2').addClass('d-none')
}
//---------------------------------------------------------------------------------------
function container_sea_withdraw(id_){
    
    $('#sea_withdraw .inp').val('')
    $('#sea_withdraw').modal('show')
    $('#sea_withdraw ._supp').addClass('d-none')
    $('#sea_withdraw ._supp').addClass('d-none')
    reset_sys_selector()

    $('#sea_withdraw').modal('show')
    $('#sea_withdraw .inp[data-name="container"]').val(id_)
}
//---------------------------------------------------------------------------------------
sys_selector('#new_custom_container .client_selector',()=>{
    $('#new_custom_container .client_selector li').hide()

    $('#new_custom_container .client_selector .sm_search').on('change keyup',function(){
        if($(this).val() === ''){
            $('#new_custom_container .client_selector li').hide()
        }
    })
})
//---------------------------------------------------------------------------------------
$('.new_custom_container').on('click',function(){
    reset_sys_selector();

    $("#new_custom_container .inp[type='text']").val('')
    $('#new_custom_container .client_selector li').hide()
    $("#new_custom_container").modal('show')

    $('#new_custom_container .profit').text('0.00 $')
    $('#new_custom_container .inp[data-name="commission"]').val('true');
    $('#new_custom_container .inp[data-name="commission"]').attr('disabled',false);

    $(`#new_custom_container .branch_selector .sys_selector button`).attr('disabled',true)
    $(`#new_custom_container .inp[data-name="branch"]`).val('')
    $(`#new_custom_container .branch_selector .sys_selector button`).text(langContent['Select'])
})
//---------------------------------------------------------------------------------------
$('#new_custom_container .inp').on('keyup change',function(){
    let client_price  = parseFloat($('#new_custom_container .inp[data-name="client_price"]').val())
    let commission    = parseFloat($('#new_custom_container .inp[data-name="commission"]').val())
    let cost          = parseFloat($('#new_custom_container .inp[data-name="cost"]').val())

    client_price = !isNaN(client_price) ? client_price : 0;
    commission   = !isNaN(commission  ) ? commission   : 0;
    cost         = !isNaN(cost        ) ? cost         : 0;
    
    let profit  = (client_price + commission) - cost ;

    if(!isNaN(profit)){
        $('#new_custom_container .profit').text(profit.toFixed(2) + ' $')
    }else{
        $('#new_custom_container .profit').text('')
    }
})
//---------------------------------------------------------------------------------------
// $('#new_custom_container .inp[data-name="with_commission"]').on('change',function(){
//     let val = $(this).val();

//     $('#new_custom_container .inp[data-name="commission"]').val('');

//     if(val === 'true'){
//         $('#new_custom_container .inp[data-name="commission"]').attr('disabled',false);
//     }else{
//         $('#new_custom_container .inp[data-name="commission"]').attr('disabled',true);
//     }
// })
//---------------------------------------------------------------------------------------
sys_selector('#new_custom_container .supplier')
sys_selector('#customs .custom_')
sys_selector('#customs .custom_2')
sys_selector('#customs .branch_')
//---------------------------------------------------------------------------------------
$('#sea_withdraw .inp[data-name="purpose"]').on('change',function(){
    let val = $(this).val();

    if(val === 'container_fee_value'){
        $('#sea_withdraw ._supp').removeClass('d-none')
    }else{
        $('#sea_withdraw ._supp').addClass('d-none')
    }
})
//---------------------------------------------------------------------------------------
$('#customs .inp[data-name="pay_for"]').on('change',function(){
    let val = $(this).val();
    

    if(val === 'export_port_customs_fee'){
        $('#customs .custom_').removeClass('d-none')
        $('#customs .custom_2').addClass('d-none')
    }

    if(val === 'import_port_customs_fee'){
        $('#customs .custom_2').removeClass('d-none')
        $('#customs .custom_').addClass('d-none')
    }
})
//---------------------------------------------------------------------------------------
$('.complete_custom_btn').on('click',function(){
    var formData = new FormData();
    
    let id     = $('#customs .inp[data-name="container"]').val()
  
    let branch = $('#customs .inp[data-name="branch"]').val()
    let value  = $('#customs .inp[data-name="value"]').val()
    let payment  = $('#customs .inp[data-name="payment"]').val()
    let pay_for  = $('#customs .inp[data-name="pay_for"]').val()
    let currency  = $('#customs .inp[data-name="currency"]').val()
    
    let custom = null;

    if(pay_for === 'export_port_customs_fee'){
        custom = $('#customs .inp[data-name="custom"]').val()
    }else{
        custom = $('#customs .inp[data-name="custom2"]').val()
    }

    let notes    = $('#customs .inp[data-name="notes"]').val()

    if(!custom || !value || !payment || !pay_for || !currency){
        showErr(langContent['Insert required data']) 
        return;
    }

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('id',id);
    formData.append('branch',branch);
    formData.append('custom',custom);
    formData.append('value',value);
    formData.append('payment',payment);
    formData.append('pay_for',pay_for);
    formData.append('notes',notes);
    formData.append('currency',currency);
    
    showLoader();

    $.ajax({
        url :'/shipping/sky/withdraw_custom_broker',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            console.log(e)
            hideLoader() 
            if(e.type === 'balance_err'){
                makeAlert(langContent['Balance is not enough'],'err')
                return;
            }
            $('.complete_custom_btn').attr('disabled',false)
            $('#customs').modal('hide')
            loadcontainers();
        },
        error:e=>{
            console.log(e)
            hideLoader()
            showErr('Somthing went wrong')
        }
    })
})
//---------------------------------------------------------------------------------------
function create_custom_container(){
    var formData = new FormData();

    let number = $('#new_custom_container .inp[data-name="number"]').val()
    let name   = $('#new_custom_container .inp[data-name="name"]').val()
    let arrival= $('#new_custom_container .inp[data-name="arrival"]').val()
    let size   = $('#new_custom_container .inp[data-name="size"]').val()
    let client_id   = $('#new_custom_container .client_selector .inp[data-name="client_id"]').val()
    let ship_from   = $('#new_custom_container .inp[data-name="ship_from"]').val()
    let client_price   = $('#new_custom_container .inp[data-name="client_price"]').val()
    let commission   = $('#new_custom_container .inp[data-name="commission"]').val()
    let supplier   = $('#new_custom_container .inp[data-name="supplier"]').val()
    let cost       = $('#new_custom_container .inp[data-name="cost"]').val()
    let payment    = $('#new_custom_container .inp[data-name="payment"]').val()
    let payment_supplier    = $('#new_custom_container .inp[data-name="payment_supplier"]').val()
    // let with_commission   = $('#new_custom_container .inp[data-name="with_commission"]').val()
    let profit    = $('#new_custom_container .profit').text()
    let branch    = $(`#new_custom_container .inp[data-name="branch"]`).val()

    if(!payment && !branch){
        showErr(langContent['Insert required data']) 
        return;
    }

    if(payment === 'pay2'){
        if(!payment || !branch){
            showErr(langContent['Insert required data']) 
            return;
        }
    }

    if(payment === 'pay'){
        if(!payment){
            showErr(langContent['Insert required data']) 
            return;
        }
    }

    if(!payment_supplier && !supplier){
        showErr(langContent['Insert required data']) 
        return;
    }

    if(payment_supplier === 'pay2'){
        if(!payment_supplier || !supplier){
            showErr(langContent['Insert required data']) 
            return;
        }
    }

    if(payment_supplier === 'pay'){
        if(!payment_supplier){
            showErr(langContent['Insert required data']) 
            return;
        }
    }

    if(!client_id || !ship_from || !client_price || !cost || !number || !name || !arrival || !size || !supplier || !payment || !payment_supplier){
        showErr(langContent['Insert required data']) 
        return;
    }

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('number', number);
    formData.append('name', name);
    formData.append('arrival', arrival);
    formData.append('size', size);

    formData.append('client_id', client_id);
    formData.append('ship_from', ship_from);
    formData.append('client_price', client_price);
    formData.append('commission', commission);
    formData.append('cost', cost);
    formData.append('profit', profit);
    formData.append('supplier', supplier);
    formData.append('payment_supplier', payment_supplier);
    formData.append('payment', payment);
    formData.append('branch', branch);
    
    showLoader();

    $.ajax({
        url :'/shipping/sky/new_custom_container',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            if(e.type === 'balance_err'){
                makeAlert(langContent['Balance is not enough'],'err')
                return;
            }
            
            makeAlert(langContent['Created successfully'])
            hideLoader()
            loadcontainers()

            $('#new_custom_container').modal('hide')
            $('#new_custom_container .inp').val('')
        },
        error:e=>{
            console.log(e)
            hideLoader()
            showErr('Somthing went wrong')
        }
    })
}
//---------------------------------------------------------------------------------------
function showContainer(id){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('id',id);
    
    showLoader();

    $.ajax({
        url :'/shipping/sky/show_custom_container',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            $('.ajax_elements').append(e)
            hideLoader() 
            $('#show_custom_container').modal('show')
        },
        error:e=>{
            console.log(e)
            hideLoader()
            showErr('Somthing went wrong')
        }
    })
}
//---------------------------------------------------------------------------------------
function showPackingList(id,title){
    var formData = new FormData();

    let page_title = $('title').text()

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('id',id);
    
    $.ajax({
        url :'/shipping/sky/print_packing_list',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            $("#print_data").html(e)

            document.title = title
            
            printJS({printable:'print_data',type:'html'})

            setTimeout(() => {
                document.title = page_title
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
//---------------------------------------------------------------------------------------
function addLink(){
    var formData = new FormData();

    if($('#link .link').val().trim() === ''){
        showErr(langContent['Insert required data']) 
        return;
    }

    showLoader(true,'modal')

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('id',id);
    formData.append('link',$('#link .link').val());
    
    $.ajax({
        url :'/shipping/sky/add_link',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            $('#link').modal('hide')
            hideLoader(langContent['Saved successfully'])
            loadcontainers();
        },
        error:e=>{
            console.log(e)
            hideLoader()
            showErr('Somthing went wrong')
        }
    })
}
//---------------------------------------------------------------------------------------
function showLink(id_,link){
    id = id_
    $('#link').modal('show')
    $('#link .link').val(link)
}
//---------------------------------------------------------------------------------------
