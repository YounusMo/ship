let page = 1;
let id = null
let table = $('.page_name').val().trim();
//---------------------------------------------------------
function load(){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('currency', $('.currency').val());
    
    tableLoader('show','.main-table');

    $.ajax({
        url :'/old_balance_archive/load?page='+page,
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            tableLoader('hide','.main-table',e,100,()=>{
                afterLoad()
            });
        }
    })
}
//---------------------------------------------------------------------------------------
load()
//---------------------------------------------------------------------------------------
$('.from, .to,.currency').on('change',function(){
    load()
})
//---------------------------------------------------------------------------------------