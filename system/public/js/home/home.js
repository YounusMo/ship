//------------------------------------------------------------
function load(){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    
    tableLoader('show','.main-table');

    $.ajax({
        url :'/analytics/load',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            $('.res').html(e)
        }
    })
}
//------------------------------------------------------------
// load()
//------------------------------------------------------------