let page = 1;
let id = null
let reportid = null
let table = $('.page_name').val().trim();
//---------------------------------------------------------
function load(){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('search', $('.search').val());
    formData.append('showDeleted', showDeleted);
    
    tableLoader('show','.main-table');

    $.ajax({
        url :'/customs_brokers/load?page='+page,
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            tableLoader('hide','.main-table',e,100,()=>{
                $('.table_counter').text($('.count').val())
                afterLoad()

                sys_selector('.branch_selector')

            });
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

    reset_sys_selector()
}
//---------------------------------------------------------------------------------------
function show_reports(id){

    $('#reports').modal('show')

    reportid = id

    report()
}
//---------------------------------------------------------------------------------------
function report(){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('supplier_id', reportid);
    
    customLoader('show' , '.report_content')


    $.ajax({
        url :'/customs_brokers/reports',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            customLoader('hide' , '.report_content',e,100)
        }
    })
}
function edit(id_){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('id', id_);
    
    showLoader()

    $.ajax({
        url :'/customs_brokers/edit',
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
    const purpose = $('#deposit .inp[data-name="purpose"]').val();
    const transactionNumber = $('#deposit .inp[data-name="transaction_number"]').val();

    formData.append('_token', token);
    formData.append('broker_id', id);
    formData.append('currency', currency);
    formData.append('value', value);
    formData.append('branch', branch);
    formData.append('notes', notes);
    formData.append('purpose', purpose);
    formData.append('transaction_number', transactionNumber);;

    if(!currency || !value || !branch || !purpose){
        showErr(langContent['Insert required data'])
        return;
    }
    showLoader()

    $('.deposit_btn').attr('disabled',true)

    $.ajax({
        url :'/customs_brokers/deposit',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            $('.deposit_btn').attr('disabled',false)
            if(e.type === 'balance_err'){
                makeAlert(langContent['Balance is not enough'],'err')
                return;
            }
            console.log(e)
            hideLoader(langContent['Completed successfully'])
            $('#deposit').modal('hide')
            


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
function get_cur(cur,what){
    
    var formData = new FormData();
    const token = $('meta[name="csrf-token"]').attr('content');

    formData.append('_token', token);
    formData.append('currency', cur);
    formData.append('what', what);

    let res = null;

    $.ajax({
        url :'/get_currencies',
        type:'POST',
        contentType: false,
        processData: false,
        async:false,
        data:formData,
        success:e=>{
           res = e
        }
    })

    return res;
}
//---------------------------------------------------------------------------------------
load()
//---------------------------------------------------------------------------------------
$('.new').on('click',function(){
    $('#new').modal('show')
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
    formData.append('type', $('#new .inp[data-name="type"]').val());

    if(ok){
        showLoader(true,'modal')
        
        $('.create_btn').attr('disabled',true)

        setTimeout(() => {
            $.ajax({
                url :'/customs_brokers/create',
                type:'POST',
                processData: false,
                contentType: false,
                async:true,
                data:formData,
                success:e=>{
                    
                    hideLoader(langContent['Created successfully'])
                    $('#new').modal('hide')
                    $('.create_btn').attr('disabled',false)

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
    formData.append('type', $('#edit .inp[data-name="type"]').val());
    formData.append('id', id);
 
    if(ok){
        showLoader(true,'modal')
        
        $('.save_btn').attr('disabled',true)

        setTimeout(() => {
            $.ajax({
                url :'/customs_brokers/save',
                type:'POST',
                processData: false,
                contentType: false,
                async:true,
                data:formData,
                success:e=>{
                    
                    hideLoader(langContent['Saved successfully'])
                    $('#edit').modal('hide')
                    $('.save_btn').attr('disabled',false)

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
function printAllReports(){
    let page_title = $('title').text()

    let title    = $('#reports .client_data[data-name="title"]').val()
    let currency = $('#reports .client_data[data-name="currency"]').val()

    document.title = `${title}_${currency}`;
    
    printJS({printable:'report_content',type:'html'})

    setTimeout(() => {
        document.title = page_title
    }, 500);
}
//---------------------------------------------------------------------------------------
$('.money').on('change keyup',function(){
    let val = $(this).val()
    val = parseInt(val.replace(/[^0-9]/g, '')) || 0;
    $(this).val(val);
});
//---------------------------------------------------------------------------------------