//---------------------------------------------------------
function loadcontainers(){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    
    tableLoader('show','.main-table');

    $.ajax({
        url :'/client/shipping/sky/load_containers?page='+page,
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
loadcontainers()
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