let page = 1;
let id = null
let reportid = null
let pending_ = 'false';
let positive_ = 'false';
let negative_ = 'false';
let allReportCurrency = null;
let hoverAllReportLi = false;
let table = $('.page_name').val().trim();
//---------------------------------------------------------
function load(){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('search', $('.search').val());
    formData.append('showDeleted', showDeleted);
    formData.append('pending', pending_);
    formData.append('positive', positive_);
    formData.append('negative', negative_);

    tableLoader('show','.main-table');

    $.ajax({
        url :'/clients/load?page='+page,
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            tableLoader('hide','.main-table',e,100,()=>{
                $('.table_counter').text($('.count').val())
                afterLoad()

                sys_selector('#deposit .branch_selector',function(){
                    $('#deposit .branch_selector li').on('click',function(){
                        let branch    = $('#deposit .inp[data-name="branch"]').val()
                        let currency  = $('#deposit .inp[data-name="currency"]').val()
                        let client_id = $('#deposit .inp[data-name="id"]').val();

                        get_totals_deposit(branch , currency , client_id)
                    })
                })

                sys_selector('#withdraw .branch_selector',function(){
                    $('#withdraw .branch_selector li').on('click',function(){
                        let branch    = $('#withdraw .inp[data-name="branch"]').val()
                        let currency  = $('#withdraw .inp[data-name="currency"]').val()
                        let client_id = $('#withdraw .inp[data-name="id"]').val();

                        get_totals_withd(branch , currency , client_id)
                    })
                })

                sys_selector('#withdraw_commission .branch_selector',function(){
                    $('#withdraw_commission .branch_selector li').on('click',function(){
                        let branch    = $('#withdraw_commission .inp[data-name="branch"]').val()
                        let currency  = $('#withdraw_commission .inp[data-name="currency"]').val()
                        let client_id = $('#withdraw_commission .inp[data-name="id"]').val();

                        get_totals_withd(branch , currency , client_id)
                    })
                })

            });
        }
    })
}
//---------------------------------------------------------------------------------------
$('#new .branch_selector_ li').on('click',function(){
    get_client_code()
})
//---------------------------------------------------------------------------------------
$('.reset_filters').on('click',function(){
    pending_  = 'false'
    positive_ = 'false'
    negative_ = 'false'
    $('.search').val('')
    load()
})
//---------------------------------------------------------------------------------------
$('.get_pending').on('click',function(){
    pending_ = pending_ === 'false' ?  'true' : 'false'
    positive_ = 'false'
    negative_ = 'false'
    load()
})
//---------------------------------------------------------------------------------------
$('.get_positive').on('click',function(){
    positive_ = positive_ === 'false' ?  'true' : 'false'
    negative_ = 'false'
    pending_ = 'false'
    load()
})
//---------------------------------------------------------------------------------------
$('.get_negative').on('click',function(){
    negative_ = negative_ === 'false' ?  'true' : 'false'
    positive_ = 'false'
    pending_ = 'false'
    load()
})
//---------------------------------------------------------------------------------------
function get_client_code(){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('branch', $('#new .inp[data-name="branch"]').val());
    
    $.ajax({
        url :'/clients/get_code',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            $('#new .inp[data-name="code"]').val(e)
        }
    })
}
//---------------------------------------------------------------------------------------
function show_deposit(id,transaction_number){
    $('#deposit .inp').val(null)

    $('#deposit .inp').val('')
    $('#deposit').modal('show')

    $('#deposit .inp[data-name="id"]').val(id)
    $('#deposit .inp[data-name="transaction_number"]').val(transaction_number)

    $('.total_tras_res , .total_client_res').text('')
    $('._total_tras , ._client_tras').val('0')

    reset_sys_selector()
}
//---------------------------------------------------------------------------------------
$('.inp[data-name="old_balance"]').on('change',function(){
    let val = $(this).val();
  
    
    if(val === 'true' || val === ''){
        $('#withdraw .branch_selector').addClass('d-none')
    } else {
        $('#withdraw .branch_selector').removeClass('d-none')
    }
})
//---------------------------------------------------------------------------------------
function show_withdraw(id,transaction_number){
    $('#withdraw .inp').val(null)

    $('#withdraw .inp').val('')
    $('#withdraw').modal('show')


    $('#withdraw .inp[data-name="id"]').val(id)
    $('#withdraw .inp[data-name="transaction_number"]').val(transaction_number)

    $('.total_tras_res , .total_client_res').text('')
    $('._total_tras , ._client_tras').val('0')


    reset_sys_selector()
}
//---------------------------------------------------------------------------------------
function show_withdraw_commission(id,transaction_number){
    $('#withdraw_commission .inp').val(null)

    $('#withdraw_commission .inp').val('')
    $('#withdraw_commission').modal('show')


    $('#withdraw_commission .inp[data-name="id"]').val(id)
    $('#withdraw_commission .inp[data-name="transaction_number"]').val(transaction_number)

    $('.total_tras_res , .total_client_res').text('')
    $('._total_tras , ._client_tras').val('0')

    reset_sys_selector()
}
//---------------------------------------------------------------------------------------
function show_transfer(id,transaction_number){
    $('#transfer .inp').val(null)

    $('#transfer .inp').val('')
    $('#transfer .result_transfer').text('')
    $('#transfer').modal('show')
    $('#transfer .switched').val('false'); 

    $('#transfer .inp[data-name="id"]').val(id)
    $('#transfer .inp[data-name="transaction_number"]').val(transaction_number)
    reset_sys_selector()
}
//---------------------------------------------------------------------------------------
sys_selector('#transfer_clients .to_client_div',()=>{
    $('#transfer_clients .to_client_div li').hide()

    $('#transfer_clients .to_client_div .sm_search').on('change keyup',function(){
        if($(this).val() === ''){
            $('#transfer_clients .to_client_div li').hide()
        }
    })
})
//---------------------------------------------------------------------------------------
function show_transfer_clients(id,transaction_number){
    $('#transfer_clients .inp').val(null)

    $('#transfer_clients .inp').val('')
    $('#transfer_clients .result_transfer').text('')
    $('#transfer_clients').modal('show')


    $('#transfer_clients .inp[data-name="id"]').val(id)
    $('#transfer_clients .inp[data-name="transaction_number"]').val(transaction_number)
    reset_sys_selector()
}
//---------------------------------------------------------------------------------------
function show_reports(id){

    $('#reports').modal('show')

    reportid = id

    deposit_report()
}
//---------------------------------------------------------------------------------------
function show_all_reports(id){

    $('#all_reports').modal('show')

    $('#reports .sidebar_ul li[data-cur="usd"]').addClass('active')
    
    reportid = id
    $('#all_reports .from , #all_reports .to').val('')
    all_report_currency('usd')

    allReportCurrency = 'usd'

}
//---------------------------------------------------------------------------------------
function printAllReports(){
    let page_title = $('title').text()

    let title    = $('#all_reports .client_data[data-name="title"]').val()
    let code     = $('#all_reports .client_data[data-name="code"]').val()
    let currency = $('#all_reports .client_data[data-name="currency"]').val()

    document.title = `${title}_${currency}`;
    
    printJS({printable:'report_content',type:'html'})

    setTimeout(() => {
        document.title = page_title
    }, 500);
}
//---------------------------------------------------------------------------------------
function all_report_currency(cur){
    var formData = new FormData();

    if(hoverAllReportLi){
        $('#all_reports .from , #all_reports .to').val('')
    }

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('client_id', reportid);
    formData.append('currency', cur);
    formData.append('from', $('#all_reports .from').val());
    formData.append('to', $('#all_reports .to').val());
    
    customLoader('show' , '.report_content')

    $('#all_reports .sidebar_ul li').removeClass('active')
    $('#all_reports .sidebar_ul li[data-cur="'+cur+'"]').addClass('active')

    $.ajax({
        url :'/clients/reports/all',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            customLoader('hide' , '#all_reports .report_content',e,100,function(){
                let title    = $('#all_reports .client_data[data-name="title"]').val()

                allReportCurrency = cur

                if(!title){
                    $('#all_reports .print_btn').addClass('d-none')
                }else{
                    $('#all_reports .print_btn').removeClass('d-none')
                }
            })

          
        }
    })
}
//---------------------------------------------------------------------------------------
function deposit_report(){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('client_id', reportid);
    formData.append('currency', $('#reports .depo_currency').val() ?? '');
    
    customLoader('show' , '.report_content')

    $('#reports .sidebar_ul li').removeClass('active')
    $('#reports .sidebar_ul .deposit_li').addClass('active')

    $.ajax({
        url :'/clients/reports/deposit',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            customLoader('hide' , '#reports .report_content',e,100,function(){
                $('.depo_currency').on('change',function(){
                    deposit_report()
                })
            })
        }
    })
}
//---------------------------------------------------------------------------------------
function pending(type){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('id', reportid);
    formData.append('type', type);
    
    customLoader('show' , '.report_content')

    $('#pending .sidebar_ul li').removeClass('active')
    
    if(type === 'deposit'){
        $('#pending .sidebar_ul .deposit_li').addClass('active')
    }
    
    if(type === 'withdraw'){
        $('#pending .sidebar_ul .withdraw_li').addClass('active')
    }

    if(type === 'withdraw_commission'){
        $('#pending .sidebar_ul .withdraw_commission_li').addClass('active')
    }

    if(type === 'transfer'){
        $('#pending .sidebar_ul .transfer_li').addClass('active')
    }

    $.ajax({
        url :'/clients/reports/pending',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            customLoader('hide' , '#pending .report_content',e,100,function(){
                $('#pending .tr_ input').on('change keyup',function(){
                    let id  = $(this).data('id');
                    let from = $('#pending .tr_[data-id=' + id + '] .from_currency_').val();
                    let to   = $('#pending .tr_[data-id=' + id + '] .to_currency_').val();
                    let value = $('#pending .tr_[data-id=' + id + '] .val_').val();
                    let exchange_rate = $('#pending .tr_[data-id=' + id + '] .exchange_').val();


                    if(from && to && value && exchange_rate && (from !== to)){
                        let calc = parseFloat(value) * parseFloat(exchange_rate);

                        calc = calc.toFixed(3);

                        $('#pending .tr_[data-id=' + id + '] .result_transfer').text(`${parseInt(calc)} ${get_cur(to,'symbol')}`)
                        $('#pending .tr_[data-id=' + id + '] .result_').val(`${parseInt(calc)}`)
                    }else{
                        $('#pending .tr_[data-id=' + id + '] .result_').val(``)
                        $('#pending .tr_[data-id=' + id + '] .result_transfer').text(``)
                    }
                })
            })
        }
    })
}
//---------------------------------------------------------------------------------------
function approveReject(id,status,type){

    
    ask(langContent['Do you want to complete ?'] , ()=>{
        
        var formData = new FormData();

        formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
        formData.append('id', id);
        formData.append('status', status);
        formData.append('type', type);


        if(type === 'transfer'){
            // formData.append('value', $('#pending .val_[data-id="'+id+'"]').val());
            // formData.append('exchange_rate', $('#pending .exchange_[data-id="'+id+'"]').val());
            // formData.append('from_currency', $('#pending .from_currency_[data-id="'+id+'"]').val());
            // formData.append('to_currency', $('#pending .to_currency_[data-id="'+id+'"]').val());
            // formData.append('result', $('#pending .result_[data-id="'+id+'"]').val());
        }else{
            formData.append('value', $('#pending .val_[data-id="'+id+'"]').val());
        }

        $.ajax({
            url :'/clients/reports/approveReject',
            type:'POST',
            contentType: false,
            processData: false,
            async:true,
            data:formData,
            success:e=>{
                makeAlert(langContent['Completed successfully'],'success')
                $('#pending').modal('hide')
                load()
            },
            error:e=>{
                showErr('Somthing went wrong')
            }
        })
    } , ()=>false)
}
//---------------------------------------------------------------------------------------
function peningTransaction(id){
    reportid = id

    pending('deposit')

    $('#pending').modal('show')
}
//---------------------------------------------------------------------------------------
function transfer_report(){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('client_id', reportid);
    formData.append('currency', $('#reports .depo_currency').val() ?? '');
    
    customLoader('show' , '.report_content')

    $('#reports .sidebar_ul li').removeClass('active')
    $('#reports .sidebar_ul .transfer_li').addClass('active')

    $.ajax({
        url :'/clients/reports/transfer',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            customLoader('hide' , '#reports .report_content',e,100,function(){
                $('.transfer_currency').on('change',function(){
                    transfer_report()
                })
            })
        }
    })
}
//---------------------------------------------------------------------------------------
function transfer_clients_report(){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('client_id', reportid);
    formData.append('currency', $('#reports .transfer_client_currency').val() ?? '');
    
    customLoader('show' , '.report_content')

    $('#reports .sidebar_ul li').removeClass('active')
    $('#reports .sidebar_ul .transfer_clients_li').addClass('active')

    $.ajax({
        url :'/clients/reports/transfer_clients',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            customLoader('hide' , '#reports .report_content',e,100,function(){
                $('.transfer_client_currency').on('change',function(){
                    transfer_clients_report()
                })
            })
        }
    })
}
//---------------------------------------------------------------------------------------
function exp_report(){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('client_id', reportid);
    formData.append('currency', $('#reports .exp_currency').val() ?? '');
    
    customLoader('show' , '.report_content')

    $('#reports .sidebar_ul li').removeClass('active')
    $('#reports .sidebar_ul .exp_li').addClass('active')

    $.ajax({
        url :'/clients/reports/exp_report',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            customLoader('hide' , '#reports .report_content',e,100,function(){
                $('.exp_currency').on('change',function(){
                    exp_report()
                })
            })
        }
    })
}
//---------------------------------------------------------------------------------------
function withdraw_report(){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('client_id', reportid);
    formData.append('currency', $('#reports .with_currency').val() ?? '');
    
    customLoader('show' , '.report_content')

    $('#reports .sidebar_ul li').removeClass('active')
    $('#reports .sidebar_ul .withdraw_li').addClass('active')
    
    $.ajax({
        url :'/clients/reports/withdraw',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            customLoader('hide' , '#reports .report_content',e,100,function(){
                $('.with_currency').on('change',function(){
                    withdraw_report()
                })
            })
        }
    })
}
//---------------------------------------------------------------------------------------
function withdraw_commission_report(){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('client_id', reportid);
    formData.append('currency', $('#reports .with_currency').val() ?? '');
    
    customLoader('show' , '.report_content')

    $('#reports .sidebar_ul li').removeClass('active')
    $('#reports .sidebar_ul .withdraw_commission_li').addClass('active')
    
    $.ajax({
        url :'/clients/reports/withdraw_commission',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            customLoader('hide' , '#reports .report_content',e,100,function(){
                $('.with_currency').on('change',function(){
                    withdraw_commission_report()
                })
            })
        }
    })
}
//---------------------------------------------------------------------------------------
function edit(id_){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('id', id_);
    
    showLoader()

    $.ajax({
        url :'/clients/edit',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            hideLoader()
            id = id_

            editMode = true;
            $('#edit').remove();
            
            $('.ajax_elements').append(e)

            $('#edit').modal('show')

            sys_selector()

        }
    })
}
//---------------------------------------------------------------------------------------
function deposit(){
    var formData = new FormData();

    const token = $('meta[name="csrf-token"]').attr('content');
    const id = $('#deposit .inp[data-name="id"]').val();
    const currency = $('#deposit .inp[data-name="currency"]').val();
    const value = $('#deposit .inp[data-name="value"]').val();
    const branch = $('#deposit .inp[data-name="branch"]').val();
    const notes = $('#deposit .inp[data-name="notes"]').val();
    const transactionNumber = $('#deposit .inp[data-name="transaction_number"]').val();
    const commission = $('#deposit .inp[data-name="commission"]').val();

    formData.append('_token', token);
    formData.append('id', id);
    formData.append('currency', currency);
    formData.append('value', value);
    formData.append('branch', branch);
    formData.append('notes', notes);
    formData.append('commission', commission);
    formData.append('transaction_number', transactionNumber);
    formData.append('status', 'pending');
    
    if(!currency || !value || !branch){
        showErr(langContent['Insert required data']) 
        return;
    }
    showLoader()

    $('.deposit_btn').attr('disabled',true)

    $.ajax({
        url :'/clients/deposit',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            hideLoader(langContent['Completed successfully'])
            $('#deposit').modal('hide')
            $('.deposit_btn').attr('disabled',false)
            
            reset_sys_selector();
            load()
        },
        error:e=>{
            console.log(e)
            hideLoader()
            showErr('Somthing went wrong')
            $('.deposit_btn').attr('disabled',false)
        }
    })
}
//---------------------------------------------------------------------------------------
function transfer_client(){
    var formData = new FormData();

    const token = $('meta[name="csrf-token"]').attr('content');
    const id = $('#transfer_clients .inp[data-name="id"]').val();
    const currency = $('#transfer_clients .inp[data-name="currency"]').val();
    const value = $('#transfer_clients .inp[data-name="value"]').val();
    const to_client = $('#transfer_clients .inp[data-name="to_client"]').val();
    const notes = $('#transfer_clients .inp[data-name="notes"]').val();
    const transactionNumber = $('#transfer_clients .inp[data-name="transaction_number"]').val();


    formData.append('_token', token);
    formData.append('id', id);
    formData.append('currency', currency);
    formData.append('value', value);
    formData.append('to_client', to_client);
    formData.append('notes', notes);
    
    formData.append('transaction_number', transactionNumber);
    
    if(!currency || !value || !to_client){
        showErr(langContent['Insert required data']) 
        return;
    }
    showLoader()

    $('.transfer_client_btn').attr('disabled',true)

    $.ajax({
        url :'/clients/transfer_client',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            hideLoader(langContent['Completed successfully'])
            $('#transfer_clients').modal('hide')
            $('.transfer_client_btn').attr('disabled',false)
            
            reset_sys_selector();
            load()
        },
        error:e=>{
            console.log(e)
            hideLoader()
            showErr('Somthing went wrong')
            $('.transfer_client_btn').attr('disabled',false)
        }
    })
}
//---------------------------------------------------------------------------------------
// function get_cur(cur,what){
    
//     var formData = new FormData();
//     const token = $('meta[name="csrf-token"]').attr('content');

//     formData.append('_token', token);
//     formData.append('currency', cur);
//     formData.append('what', what);

//     let res = null;

//     $.ajax({
//         url :'/get_currencies',
//         type:'POST',
//         contentType: false,
//         processData: false,
//         async:false,
//         data:formData,
//         success:e=>{
//            res = e
//         }
//     })

//     return res;
// }
//---------------------------------------------------------------------------------------
$('#transfer .inp').on('change keyup',function(){
    const from = $('#transfer .inp[data-name="from_currency"]').val();
    const to   = $('#transfer .inp[data-name="to_currency"]').val();
    const value = $('#transfer .inp[data-name="value"]').val();
    const exchange_rate = $('#transfer .inp[data-name="exchange_rate"]').val();

    if(from && to && value && exchange_rate && (from !== to)){
        let calc = parseFloat(value) * parseFloat(exchange_rate);

        calc = calc.toFixed(3);

        $('#transfer .result_transfer').text(`${value} ${get_cur(from,'symbol')} X ${exchange_rate} = ${parseInt(calc)} ${get_cur(to,'symbol')}`)
        $('#transfer .inp[data-name="result"]').val(`${parseInt(calc)}`)
    }else{
        $('#transfer .inp[data-name="result"]').val(``)
        $('#transfer .result_transfer').text(``)
    }
})
//---------------------------------------------------------------------------------------
function transfer(){
    var formData = new FormData();

    const token = $('meta[name="csrf-token"]').attr('content');
    const id = $('#transfer .inp[data-name="id"]').val();
    const from = $('#transfer .inp[data-name="from_currency"]').val();
    const to  = $('#transfer .inp[data-name="to_currency"]').val();
    const value = $('#transfer .inp[data-name="value"]').val();
    const result = $('#transfer .inp[data-name="result"]').val();
    const exchange_rate = $('#transfer .inp[data-name="exchange_rate"]').val();
    const notes = $('#transfer .notes').val();
    const transactionNumber = $('#transfer .inp[data-name="transaction_number"]').val();

    formData.append('_token', token);
    formData.append('id', id);
    formData.append('from', from);
    formData.append('to', to);
    formData.append('value', value);
    formData.append('result', result);
    formData.append('notes', notes); 
    formData.append('exchange_rate', exchange_rate); 
    formData.append('transaction_number', transactionNumber);;
    
    if(!from || !to || !value || !exchange_rate || !result){
        showErr(langContent['Insert required data']) 
        return;
    }
    showLoader()

    $('.transfer_btn').attr('disabled',true)

    $.ajax({
        url :'/clients/transfer',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            hideLoader(langContent['Transfered successfully'])
            $('#transfer').modal('hide')
            $('.transfer_btn').attr('disabled',false)


            reset_sys_selector();
            load()
        },
        error:e=>{
            console.log(e)
            hideLoader()
            showErr('Somthing went wrong')
            $('.transfer_btn').attr('disabled',false)
        }
    })
}
//---------------------------------------------------------------------------------------
function withdraw(){
    var formData = new FormData();

    const token = $('meta[name="csrf-token"]').attr('content');
    const id = $('#withdraw .inp[data-name="id"]').val();
    const currency = $('#withdraw .inp[data-name="currency"]').val();
    const value = $('#withdraw .inp[data-name="value"]').val();
    const branch = $('#withdraw .inp[data-name="branch"]').val();
    const notes = $('#withdraw .inp[data-name="notes"]').val();
    const commission = $('#withdraw .inp[data-name="commission"]').val();
    const old_balance = $('#withdraw .inp[data-name="old_balance"]').val();
    const transactionNumber = $('#withdraw .inp[data-name="transaction_number"]').val();

    formData.append('_token', token);
    formData.append('id', id);
    formData.append('currency', currency);
    formData.append('value', value);
    formData.append('branch', branch);
    formData.append('notes', notes);
    formData.append('old_balance', old_balance);
    formData.append('commission',commission);
    formData.append('transaction_number', transactionNumber);
    formData.append('status', 'pending');
    
    if(!currency || !value || !old_balance){
        showErr(langContent['Insert required data']) 
        return;
    }


    if(old_balance === 'false' && !branch){
        showErr(langContent['Insert required data']) 
        return;
    }

    

    showLoader()

    $('.withdraw_btn').attr('disabled',true)

    $.ajax({
        url :'/clients/withdraw',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            hideLoader(langContent['Completed successfully'])
            $('#withdraw').modal('hide')
            $('.withdraw_btn').attr('disabled',false)

            reset_sys_selector();
            load()
        },
        error:e=>{
            console.log(e)
            hideLoader()
            showErr('Somthing went wrong')
            $('.withdraw_btn').attr('disabled',false)
        }
    })
}
//---------------------------------------------------------------------------------------
function withdraw_commission(){
    var formData = new FormData();

    const token = $('meta[name="csrf-token"]').attr('content');
    const id = $('#withdraw_commission .inp[data-name="id"]').val();
    const currency = $('#withdraw_commission .inp[data-name="currency"]').val();
    // const value = $('#withdraw_commission .inp[data-name="value"]').val();
    // const branch = $('#withdraw_commission .inp[data-name="branch"]').val();
    const notes = $('#withdraw_commission .inp[data-name="notes"]').val();
    const commission = $('#withdraw_commission .inp[data-name="commission"]').val();
    const transactionNumber = $('#withdraw_commission .inp[data-name="transaction_number"]').val();

    formData.append('_token', token);
    formData.append('id', id);
    formData.append('currency', currency);
    formData.append('value', 0);
    formData.append('branch', 15);
    formData.append('notes', notes);
    formData.append('commission',commission);
    formData.append('transaction_number', transactionNumber);
    formData.append('status', 'pending');
    
    if(!currency){
        showErr(langContent['Insert required data']) 
        return;
    }
    showLoader()

    $('.withdraw_btn').attr('disabled',true)

    $.ajax({
        url :'/clients/withdraw_commission',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            hideLoader(langContent['Completed successfully'])
            $('#withdraw_commission').modal('hide')
            $('.withdraw_btn').attr('disabled',false)

            reset_sys_selector();
            load()
        },
        error:e=>{
            console.log(e)
            hideLoader()
            showErr('Somthing went wrong')
            $('.withdraw_btn').attr('disabled',false)
        }
    })
}
//---------------------------------------------------------------------------------------
load()
//---------------------------------------------------------------------------------------
$('.new').on('click',function(){
    $('#new').modal('show')
    $('#new .inp').val('')
    reset_sys_selector()
    editMode = false;
})
//---------------------------------------------------------------------------------------
$('.show_trash').on('click',function(){

    if(showDeleted === 'true'){
        $('.in_trash').addClass('d-none')
        $('.out_trash').removeClass('d-none')
        
        // $(this).text(langContent['Show deleted'])
        
    }else{
        $('.in_trash').removeClass('d-none')
        $('.out_trash').addClass('d-none')
        // $(this).text(langContent['Back'])
    }


    showDeleted = showDeleted === 'true' ? 'false' : 'true'
    load()
})
//---------------------------------------------------------------------------------------
function create(){

    let ok = true;

    $('#new .req').each(function(){
        if($(this).val() === ''){
            ok = false;
            console.log($(this).data('name'))
        }
    })
    
    var formData = new FormData();
    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('name', $('#new .inp[data-name="name"]').val());
    formData.append('pass_txt', $('#new .inp[data-name="pass_txt"]').val());
    formData.append('email', $('#new .inp[data-name="email"]').val());
    formData.append('phone', $('#new .inp[data-name="phone"]').val());
    formData.append('country', $('#new .inp[data-name="country"]').val());
    formData.append('branch', $('#new .inp[data-name="branch"]').val());
    formData.append('type', $('#new .inp[data-name="type"]').val());
    formData.append('code', $('#new .inp[data-name="code"]').val());
 

    if(ok){
        showLoader(true,'modal')
        
        $('.create_btn').attr('disabled',true)

        setTimeout(() => {
            $.ajax({
                url :'/clients/create',
                type:'POST',
                processData: false,
                contentType: false,
                async:true,
                data:formData,
                success:e=>{
                    
                    $('.create_btn').attr('disabled',false)

                    if(e.type === 'exist'){
                        showErr(langContent['Already exist'])
                        return;
                    }

                    hideLoader(langContent['Created successfully'])
                    $('#new').modal('hide')

                    $('#new .inp').val('')

                    reset_sys_selector()

                    load()
                },
                error:e=>{
                    console.log(e)
                    hideLoader()
                    showErr('Somthing went wrong')
                    $('.create_btn').attr('disabled',false)
                }
            })
        }, 500);
    }else{
        showErr(langContent['Insert required data']) 
    }
    
}
//---------------------------------------------------------------------------------------
function save(){

    let ok = true;

    $('#edit .req').each(function(){
        if($(this).val() === ''){
            ok = false;
            console.log($(this).data('name'))
        }
    })
    
    var formData = new FormData();
    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('name', $('#edit .inp[data-name="name"]').val());
    formData.append('email', $('#edit .inp[data-name="email"]').val());
    formData.append('code', $('#edit .inp[data-name="code"]').val());
    formData.append('phone', $('#edit .inp[data-name="phone"]').val());
    formData.append('country', $('#edit .inp[data-name="country"]').val());
    formData.append('branch', $('#edit .inp[data-name="branch"]').val());
    formData.append('type', $('#edit .inp[data-name="type"]').val());
    formData.append('pass_txt', $('#edit .inp[data-name="pass_txt"]').val());
    formData.append('id', id);
 

    if(ok){
        showLoader(true,'modal')
        
        $('.save_btn').attr('disabled',true)

        setTimeout(() => {
            $.ajax({
                url :'/clients/save',
                type:'POST',
                processData: false,
                contentType: false,
                async:true,
                data:formData,
                success:e=>{
                    
                    $('.save_btn').attr('disabled',false)


                    if(e.type === 'exist'){
                        showErr(langContent['Already exist'])
                        return;
                    }

                    hideLoader(langContent['Saved successfully'])
                    $('#edit').modal('hide')

                    $('#edit .inp').val('')

                    reset_sys_selector()
                    
                    load()
                },
                error:e=>{
                    console.log(e)
                    hideLoader()
                    showErr('Somthing went wrong')
                    $('.save_btn').attr('disabled',false)
                }
            })
        }, 500);
    }else{
        showErr(langContent['Insert required data']) 
    }
    
}
//---------------------------------------------------------------------------------------
$('.search').on('keyup',function(e){
    if(e.keyCode == 13){
        load();
    }
})
//---------------------------------------------------------------------------------------
function switchCur(){
    
    const switched = $('#transfer .switched').val();

    let from = $('#transfer .inp[data-name="from_currency"]').val();
    let to   = $('#transfer .inp[data-name="to_currency"]').val();
    let value = $('#transfer .inp[data-name="value"]').val();
    let exchange_rate = $('#transfer .inp[data-name="exchange_rate"]').val();


    $('#transfer .inp[data-name="from_currency"]').val(to);
    $('#transfer .inp[data-name="to_currency"]').val(from);


    from = $('#transfer .inp[data-name="from_currency"]').val();
    to   = $('#transfer .inp[data-name="to_currency"]').val();

    if(from && to && value && exchange_rate && (from !== to)){
        
        let calc_ = 1 / parseFloat(exchange_rate);
        // calc_ = calc_.toFixed(3);
        $('#transfer .inp[data-name="exchange_rate"]').val(calc_)


        exchange_rate = calc_;

        let calc = parseFloat(value) * parseFloat(exchange_rate);

        // calc = calc.toFixed(3);
        
        $('#transfer .result_transfer').text(`${value} ${get_cur(from,'symbol')} X ${exchange_rate} = ${parseInt(calc)} ${get_cur(to,'symbol')}`)
        $('#transfer .inp[data-name="result"]').val(`${parseInt(calc)}`)  
    }else{
        $('#transfer .inp[data-name="result"]').val(``)
        $('#transfer .result_transfer').text(``)
    }
}
//---------------------------------------------------------------------------------------
function switchCurPending(id){
    
    let from = $('#pending .tr_[data-id=' + id + '] .from_currency_').val();
    let to   = $('#pending .tr_[data-id=' + id + '] .to_currency_').val();
    let value = $('#pending .tr_[data-id=' + id + '] .val_').val();
    let exchange_rate = $('#pending .tr_[data-id=' + id + '] .exchange_').val();


    $('#pending .tr_[data-id=' + id + '] .from_currency_').val(to);
    $('#pending .tr_[data-id=' + id + '] .to_currency_').val(from);


    from = $('#pending .tr_[data-id=' + id + '] .from_currency_').val();
    to   = $('#pending .tr_[data-id=' + id + '] .to_currency_').val();

    if(from && to && value && exchange_rate && (from !== to)){
        
        let calc_ = 1 / parseFloat(exchange_rate);
        // calc_ = calc_.toFixed(3);
        $('#pending .tr_[data-id=' + id + '] .exchange_').val(calc_)


        exchange_rate = calc_;

        let calc = parseFloat(value) * parseFloat(exchange_rate);

        // calc = calc.toFixed(3);
        
        $('#pending .tr_[data-id=' + id + '] .result_transfer').text(`${parseInt(calc)} ${get_cur(to,'symbol')}`)
        $('#pending .tr_[data-id=' + id + '] .result_').val(`${parseInt(calc)}`)  
    }else{
        $('#pending .tr_[data-id=' + id + '] .result_').val(``)
        $('#pending .tr_[data-id=' + id + '] .result_transfer').text(``)
    }
}
//---------------------------------------------------------------------------------------
$('#all_reports .sidebar_ul li').on('mousemove',function(){
    hoverAllReportLi = true
})
//---------------------------------------------------------------------------------------
$('#all_reports .sidebar_ul li').on('mouseleave',function(){
    hoverAllReportLi = false
})
//---------------------------------------------------------------------------------------
$('#all_reports .from , #all_reports .to').on('change',function(){
    all_report_currency(allReportCurrency)
})
//---------------------------------------------------------------------------------------
function get_totals_deposit(branch , currency , client_id){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('branch', branch);
    formData.append('currency', currency);
    formData.append('client_id', client_id);

    $.ajax({
        url :'/treasury/get_totals',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            $('#deposit .total_client_res').text((e[1]) + get_cur(currency,'symbol'))
            $('#deposit ._client_tras').val(e[1])

            $('#deposit .total_tras_res').text((e[0]) + get_cur(currency,'symbol'))
            $('#deposit ._total_tras').val(e[0])
            calcDepositTreasury()
            calcDepositBl()
        }
    })
}
//---------------------------------------------------------------------------------------
$('#deposit .inp[data-name="value"]').on('change keyup',function(){
    calcDepositBl()
    calcDepositTreasury()
})
//---------------------------------------------------------------------------------------
function calcDepositBl(){
    let val = parseFloat($('#deposit .inp[data-name="value"]').val())
    let bl  = parseFloat($('#deposit ._client_tras').val())

    let cur = $('#deposit .inp[data-name="currency"]').val()

    if(!isNaN(val) && !isNaN(bl) && cur){
      $('#deposit .total_client_res').text((bl + val).toFixed(2) + get_cur(cur,'symbol'))
    }
}
//---------------------------------------------------------------------------------------
function calcDepositTreasury(){
    let val = parseFloat($('#deposit .inp[data-name="value"]').val())
    let commission = $('#deposit .inp[data-name="commission"]').val() ? parseFloat($('#deposit .inp[data-name="commission"]').val()) : 0
    let bl  = parseFloat($('#deposit ._total_tras').val())

    let cur = $('#deposit .inp[data-name="currency"]').val()
    
    if(!isNaN(val) && !isNaN(bl) && cur && !isNaN(commission)){
        $('#deposit .total_tras_res').text((bl + val + commission).toFixed(2) + get_cur(cur,'symbol'))
    }
}
//---------------------------------------------------------------------------------------
$('#deposit select').on('change',function(){
    let branch    = $('#deposit .inp[data-name="branch"]').val()
    let currency  = $('#deposit .inp[data-name="currency"]').val()
    let client_id = $('#deposit .inp[data-name="id"]').val();
    
    get_totals_deposit(branch , currency , client_id)
})
//---------------------------------------------------------------------------------------
$('#deposit .inp[data-name="commission"], #deposit .inp[data-name="value"]').on('change keyup',function(){
    calcDepositTreasury()
})
//---------------------------------------------------------------------------------------
function get_totals_withd(branch , currency , client_id){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('branch', branch);
    formData.append('currency', currency);
    formData.append('client_id', client_id);

    $.ajax({
        url :'/treasury/get_totals',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            console.log(e)
            $('#withdraw .total_client_res').text((e[1]) - get_cur(currency,'symbol'))
            $('#withdraw ._client_tras').val(e[1])

            $('#withdraw .total_tras_res').text((e[0]) - get_cur(currency,'symbol'))
            $('#withdraw ._total_tras').val(e[0])
            calcWidthdTreasury()
            calcWidthdBl()
        }
    })
}
//---------------------------------------------------------------------------------------
$('#withdraw .inp[data-name="value"]').on('change keyup',function(){
    calcWidthdBl()
    calcWidthdTreasury()
})
//---------------------------------------------------------------------------------------
function calcWidthdBl(){
    let val = parseFloat($('#withdraw .inp[data-name="value"]').val())
    let bl  = parseFloat($('#withdraw ._client_tras').val())

    let cur = $('#withdraw .inp[data-name="currency"]').val()

    if(!isNaN(val) && !isNaN(bl) && cur){
      $('#withdraw .total_client_res').text((bl - val).toFixed(2) + get_cur(cur,'symbol'))
    }
}
//---------------------------------------------------------------------------------------
function calcWidthdTreasury(){
    let val = parseFloat($('#withdraw .inp[data-name="value"]').val())
    let bl  = parseFloat($('#withdraw ._total_tras').val())

    let cur = $('#withdraw .inp[data-name="currency"]').val()

    if(!isNaN(val) && !isNaN(bl) && cur){
      $('#withdraw .total_tras_res').text((bl - val).toFixed(2) + get_cur(cur,'symbol'))
    }
}
//---------------------------------------------------------------------------------------
$('#withdraw select:not(.old_balance)').on('change',function(){
    let branch    = $('#withdraw .inp[data-name="branch"]').val()
    let currency  = $('#withdraw .inp[data-name="currency"]').val()
    let client_id = $('#withdraw .inp[data-name="id"]').val();
    
    get_totals_withd(branch , currency , client_id)
})
//---------------------------------------------------------------------------------------
$('#withdraw .inp[data-name="value"]').on('change keyup',function(){
    calcWidthdTreasury()
})
//---------------------------------------------------------------------------------------
function del_transcation(id,type){

    let msg = 'Do you want to delete ?';

    if(type === 'transfer_client'){
        msg = 'The process will be deleted from both sides, are you sure ?';
    }

    ask(langContent[msg],function(){
        var formData = new FormData();

        formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
        formData.append('id', id);
        
        $.ajax({
            url :'/clients/del_transaction',
            type:'POST',
            contentType: false,
            processData: false,
            async:true,
            data:formData,
            success:e=>{
                $('#new .inp[data-name="code"]').val(e)

                if(type === 'deposit'){
                    deposit_report()
                }
                if(type === 'withdraw'){
                    withdraw_report()
                }
                if(type === 'withdraw_commission'){
                    withdraw_commission_report()
                }
                if(type === 'transfer'){
                    transfer_report()
                }
                if(type === 'transfer_client'){
                    transfer_clients_report()
                }

                load()
            }
        })
    },()=>false)
}
//---------------------------------------------------------------------------------------
$('.money').on('change keyup',function(){
    let val = $(this).val()
    val = parseInt(val.replace(/[^0-9]/g, '')) || 0;
    $(this).val(val);
});
//---------------------------------------------------------------------------------------