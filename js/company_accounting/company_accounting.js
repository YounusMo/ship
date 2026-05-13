let page = 1;
let id = null
let table = $('.page_name').val().trim();
//---------------------------------------------------------
function load(){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    
    formData.append('from', $('.from').val());
    formData.append('to', $('.to').val());
    formData.append('type', $('.type').val());
    formData.append('currency', $('.currency').val());
    formData.append('branch', $('.branch .inp[data-name="branch"]').val());
    
    tableLoader('show','.main-table');

    if($('.type').val() === 'branch_comission'){
        $('.branch .form-select').attr('disabled',true)
    }else{
        $('.branch .form-select').attr('disabled',false)
    }

    $.ajax({
        url :'/company/load?page='+page,
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            tableLoader('hide','.main-table',e,100,()=>{
                $('.table_counter').text($('.count').val())
                afterLoad()
            });
        }
    })
}
//---------------------------------------------------------------------------------------
load()
sys_selector('.branch')
sys_selector('#deposit .branch_selector')
sys_selector('#add_expenses .branch_selector')

sys_selector('#add_expenses .users_selector')

sys_selector('#add_expenses .purpose_selector' , function(){
    $('#add_expenses .purpose_selector li').on('click',function(){
        let val = $(this).data('val')

        if(val === 'salary'){
            $('#add_expenses .users_selector').removeClass('d-none')
        }else{
            $('#add_expenses .users_selector').addClass('d-none')
        }
    });
})

sys_selector('#transfer .branch_selector')
sys_selector('#fix_branch .branch_selector')
sys_selector('#fix_branch .branch_selector2')

sys_selector('#sea_withdraw .container_selector')
sys_selector('#sea_withdraw .branch_selector')

sys_selector('#sky_withdraw .container_selector')
sys_selector('#sky_withdraw .branch_selector')
//---------------------------------------------------------------------------------------
$('.from , .to , .type , .currency').on('change',function(){
    load()
})
//---------------------------------------------------------------------------------------
$('.branch li').on('click',function(){
    load()
})
//---------------------------------------------------------------------------------------
$('.deposit_branch').on('click',function(){
    $('#deposit').modal('show')
    
    $('#deposit_branch .inp').val('')
    $('#deposit .inp').val(null)
    
    reset_sys_selector()

    $('#deposit .inp[data-name="transaction_number"]').val(transactionNumber('deposit_branch',3))
})
//---------------------------------------------------------------------------------------
$('.deposit_commission_branch').on('click',function(){
    $('#commission').modal('show')
    
    $('#treasury .inp').val('')
    $('#commission .inp').val(null)
    
    reset_sys_selector()

    $('#commission .inp[data-name="transaction_number"]').val(transactionNumber('deposit_commission_branch',3))
})
//---------------------------------------------------------------------------------------
$('.transfer_branch').on('click',function(){
    $('#transfer').modal('show')
    
    $('#transfer .inp').val(null)
    $('#transfer .result_transfer').text('')

    reset_sys_selector()
    
    $('#transfer .inp[data-name="transaction_number"]').val(transactionNumber('transfer_branch',3))
})
//---------------------------------------------------------------------------------------
$('.fix_branch').on('click',function(){
    $('#fix_branch').modal('show')
    
    $('#fix_branch .inp').val(null)

    reset_sys_selector()
    
    $('#fix_branch .inp[data-name="transaction_number"]').val(transactionNumber('fix_branch',3))
})
//---------------------------------------------------------------------------------------
$('.add_expenses').on('click',function(){
    $('#add_expenses').modal('show')
    
    $('#add_expenses .inp').val('')
    $('#add_expenses .inp').val(null)


    reset_sys_selector()

    $('#add_expenses .users_selector').addClass('d-none')
    
    $('#add_expenses .inp[data-name="transaction_number"]').val(transactionNumber('expenses_branch',3))
})
//---------------------------------------------------------------------------------------
$('.container_sea_withdraw').on('click',function(){
    $('#sea_withdraw').modal('show')
    
    $('#sea_withdraw .inp').val('')
    $('#sea_withdraw .inp').val(null)

    reset_sys_selector()
    
    $('#sea_withdraw .inp[data-name="transaction_number"]').val(transactionNumber('container_sea_withdraw',4))
})
//---------------------------------------------------------------------------------------
$('.container_sky_withdraw').on('click',function(){
    $('#sky_withdraw').modal('show')
    
    $('#sky_withdraw .inp').val('')
    $('#sky_withdraw .inp').val(null)

    reset_sys_selector()
    
    $('#sky_withdraw .inp[data-name="transaction_number"]').val(transactionNumber('container_sky_withdraw',4))
})
//---------------------------------------------------------------------------------------
$('.search').on('keyup',function(e){
    if(e.keyCode == 13){
        load();
    }
})
//---------------------------------------------------------------------------------------
$('#transfer .inp').on('change keyup',function(){
    const from = $('#transfer .inp[data-name="from_currency"]').val();
    const to   = $('#transfer .inp[data-name="to_currency"]').val();
    const value = $('#transfer .inp[data-name="value"]').val();
    const exchange_rate = $('#transfer .inp[data-name="exchange_rate"]').val();

    if(from && to && value && exchange_rate && (from !== to)){
        let calc = parseFloat(value) * parseFloat(exchange_rate);

        // calc = calc.toFixed(2);

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
    const id = $('#transfer .branch_selector .inp[data-name="branch"]').val();
    const from = $('#transfer .inp[data-name="from_currency"]').val();
    const to  = $('#transfer .inp[data-name="to_currency"]').val();
    const value = $('#transfer .inp[data-name="value"]').val();
    const result = $('#transfer .inp[data-name="result"]').val();
    const exchange_rate = $('#transfer .inp[data-name="exchange_rate"]').val();
    const notes = $('#transfer .notes').val();
    const transactionNumber = $('#transfer .inp[data-name="transaction_number"]').val();

    formData.append('_token', token);
    formData.append('branch', id);
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
        url :'/company/transfer_branch',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            $('.transfer_btn').attr('disabled',false)

            if(e.type === 'balance_err'){
                makeAlert(langContent['Balance is not enough'],'err')
                return;
            }

            hideLoader(langContent['Transfered successfully'])
            $('#transfer').modal('hide')


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

    formData.append('_token', token);
    formData.append('branch', id);
    formData.append('value', value);
    formData.append('currency', currency);
    formData.append('notes', notes); 
    formData.append('purpose', purpose); 
    formData.append('container', container); 
    formData.append('transaction_number', transactionNumber);;
    
    if(!currency || !value || !purpose || !container){
        showErr(langContent['Insert required data']) 
        return;
    }
    showLoader()

    $('.sea_withdraw_btn').attr('disabled',true)

    $.ajax({
        url :'/company/sea_withdraw',
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
            load()
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
function complete_sky_withdraw(){
    var formData = new FormData();

    const token = $('meta[name="csrf-token"]').attr('content');
    const id = $('#sky_withdraw .branch_selector .inp[data-name="branch"]').val();
    const currency = $('#sky_withdraw .inp[data-name="currency"]').val();
    const value = $('#sky_withdraw .inp[data-name="value"]').val();
    const purpose = $('#sky_withdraw .inp[data-name="purpose"]').val();
    const container = $('#sky_withdraw .inp[data-name="container"]').val();
    const notes = $('#sky_withdraw .inp[data-name="notes"]').val();
    const transactionNumber = $('#sky_withdraw .inp[data-name="transaction_number"]').val();

    formData.append('_token', token);
    formData.append('branch', id);
    formData.append('value', value);
    formData.append('currency', currency);
    formData.append('notes', notes); 
    formData.append('purpose', purpose); 
    formData.append('container', container); 
    formData.append('transaction_number', transactionNumber);;
    
    if(!currency || !value || !purpose || !container){
        showErr(langContent['Insert required data']) 
        return;
    }
    showLoader()

    $('.sky_withdraw_btn').attr('disabled',true)

    $.ajax({
        url :'/company/sky_withdraw',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            $('.sky_withdraw_btn').attr('disabled',false)
            if(e.type === 'balance_err'){
                makeAlert(langContent['Balance is not enough'],'err')
                return;
            }
            
            hideLoader(langContent['Completed successfully'])
            $('#sky_withdraw').modal('hide')


            reset_sys_selector();
            load()
        },
        error:e=>{
            console.log(e)
            hideLoader()
            showErr('Somthing went wrong')
            $('.sky_withdraw_btn').attr('disabled',false)
        }
    })
}
//---------------------------------------------------------------------------------------
function fix_branch(){
    var formData = new FormData();

    const token = $('meta[name="csrf-token"]').attr('content');
    const from_branch  = $('#fix_branch .branch_selector .inp[data-name="from_branch"]').val();
    const to_branch    = $('#fix_branch .branch_selector2 .inp[data-name="to_branch"]').val();
    const currency = $('#fix_branch .inp[data-name="currency"]').val();
    const value = $('#fix_branch .inp[data-name="value"]').val();
    const notes = $('#fix_branch .inp[data-name="notes"]').val();
    const transactionNumber = $('#fix_branch .inp[data-name="transaction_number"]').val();

    formData.append('_token', token);
    formData.append('from_branch', from_branch);
    formData.append('to_branch', to_branch);
    formData.append('currency', currency);
    formData.append('value', value);
    formData.append('notes', notes); 
    formData.append('transaction_number', transactionNumber);;
    
    if( from_branch === to_branch){
        showErr(langContent['Please select different branches']) 
        return;
    }
    
    if(!from_branch || !to_branch || !value || !currency){
        showErr(langContent['Insert required data']) 
        return;
    }
    showLoader()

    $('.fix_branch_btn').attr('disabled',true)

    $.ajax({
        url :'/company/fix_branch',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            $('.fix_branch_btn').attr('disabled',false)
            
            if(e.type === 'balance_err'){
                makeAlert(langContent['Balance is not enough'],'err')
                return;
            }

            hideLoader(langContent['Transfered successfully'])
            $('#fix_branch').modal('hide')
            
            reset_sys_selector();
            load()
        },
        error:e=>{
            console.log(e)
            hideLoader()
            showErr('Somthing went wrong')
            $('.fix_branch_btn').attr('disabled',false)
        }
    })
}
//---------------------------------------------------------------------------------------
function deposit(){
    var formData = new FormData();

    const token = $('meta[name="csrf-token"]').attr('content');
    const currency = $('#deposit .inp[data-name="currency"]').val();
    const value = $('#deposit .inp[data-name="value"]').val();
    const branch = $('#deposit .inp[data-name="branch"]').val();
    const notes = $('#deposit .inp[data-name="notes"]').val();
    const transactionNumber = $('#deposit .inp[data-name="transaction_number"]').val();

    formData.append('_token', token);
    formData.append('currency', currency);
    formData.append('value', value);
    formData.append('branch', branch);
    formData.append('notes', notes);
    formData.append('transaction_number', transactionNumber);;
    
    if(!currency || !value || !branch){
        showErr(langContent['Insert required data']) 
        return;
    }
    showLoader()

    $('#deposit .deposit_btn').attr('disabled',true)

    $.ajax({
        url :'/company/deposit_branch',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            hideLoader(langContent['Completed successfully'])
            $('#deposit').modal('hide')
            $('#deposit .deposit_btn').attr('disabled',false)


            reset_sys_selector();
            load()
        },
        error:e=>{
            console.log(e)
            hideLoader()
            showErr('Somthing went wrong')
            $('#deposit .deposit_btn').attr('disabled',false)
        }
    })
}
//---------------------------------------------------------------------------------------
function deposit_commission(){
    var formData = new FormData();

    const token = $('meta[name="csrf-token"]').attr('content');
    const currency = $('#commission .inp[data-name="currency"]').val();
    const value = $('#commission .inp[data-name="value"]').val();
    const notes = $('#commission .inp[data-name="notes"]').val();
    const transactionNumber = $('#commission .inp[data-name="transaction_number"]').val();

    formData.append('_token', token);
    formData.append('currency', currency);
    formData.append('value', value);
    formData.append('branch', 15);
    formData.append('notes', notes);
    formData.append('transaction_number', transactionNumber);;
    
    if(!currency || !value ){
        showErr(langContent['Insert required data']) 
        return;
    }
    showLoader()

    $('#commission .deposit_btn').attr('disabled',true)

    $.ajax({
        url :'/company/deposit_commission',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            hideLoader(langContent['Completed successfully'])
            $('#commission').modal('hide')
            $('#commission .deposit_btn').attr('disabled',false)


            reset_sys_selector();
            load()
        },
        error:e=>{
            console.log(e)
            hideLoader()
            showErr('Somthing went wrong')
            $('#commission .deposit_btn').attr('disabled',false)
        }
    })
}
//---------------------------------------------------------------------------------------
function add_expenses(){
    var formData = new FormData();

    const token = $('meta[name="csrf-token"]').attr('content');
    const currency = $('#add_expenses .inp[data-name="currency"]').val();
    const value = $('#add_expenses .inp[data-name="value"]').val();
    const branch = $('#add_expenses .inp[data-name="branch"]').val();
    const notes = $('#add_expenses .inp[data-name="notes"]').val();
    const purpose = $('#add_expenses .inp[data-name="purpose"]').val();
    const users = $('#add_expenses .inp[data-name="users"]').val();
    const transactionNumber = $('#add_expenses .inp[data-name="transaction_number"]').val();

    formData.append('_token', token);
    formData.append('currency', currency);
    formData.append('value', value);
    formData.append('branch', branch);
    formData.append('notes', notes);
    formData.append('users', users);
    formData.append('purpose', purpose);
    formData.append('transaction_number', transactionNumber);;
    
    if(!currency || !value || !branch || !purpose){
        showErr(langContent['Insert required data']) 
        return;
    }

    if(purpose === 'salary' && ! users){
        showErr(langContent['Insert required data']) 
        return;
    }

    showLoader()

    $('.add_expenses_btn').attr('disabled',true)

    $.ajax({
        url :'/company/add_expenses',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            $('.add_expenses_btn').attr('disabled',false)

            if(e.type === 'balance_err'){
                makeAlert(langContent['Balance is not enough'],'err')
                return;
            }

            hideLoader(langContent['Completed successfully'])
            $('#add_expenses').modal('hide')


            reset_sys_selector();
            load()
        },
        error:e=>{
            console.log(e)
            hideLoader()
            showErr('Somthing went wrong')
            $('.add_expenses_btn').attr('disabled',false)
        }
    })
}
//---------------------------------------------------------------------------------------
$('.print').on('click',function(){
    
    $('.pagination').css('display','none')
    $('.small').css('display','none')
    printJS({printable:'printable',type:'html'})

    setTimeout(() => {
        $('.pagination').attr('style','')
    }, 500);

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