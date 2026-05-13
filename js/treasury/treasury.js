let page = 1;
let id = null
let table = $('.page_name').val().trim();
//---------------------------------------------------------
function load(){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    
    formData.append('date', $('.date').val());
    formData.append('date2', $('.date2').val());
    formData.append('currency', $('.currency').val());
    formData.append('branch', $('.branch .inp[data-name="branch"]').val());
    
    tableLoader('show','.main-table');

    $.ajax({
        url :'/treasury/load',
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
function loadBalance(){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    
    formData.append('date', $('.date').val());
    formData.append('branch', $('.branch .inp[data-name="branch"]').val());
    
    $.ajax({
        url :'/treasury/load_balance',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
           $('.balances_').html(e)
        }
    })
}
//---------------------------------------------------------------------------------------
load()
loadBalance()
sys_selector('.branch')
//---------------------------------------------------------------------------------------
$('.date, .currency,.date2').on('change',function(){
    load()
    loadBalance()
})
//---------------------------------------------------------------------------------------
$('.branch li').on('click',function(){
    load()
    loadBalance()
})
//---------------------------------------------------------------------------------------
$('.search').on('keyup',function(e){
    if(e.keyCode == 13){
        load();
    }
})
//---------------------------------------------------------------------------------------
$('.print').on('click',function(){
    printJS({printable:'printable',type:'html'})
})
//---------------------------------------------------------------------------------------