//-------------------------------------------------------
const page_action = $('.page_action').val()
//-------------------------------------------------------
if(page_action === 'new'){
    addColumn('true')
}
//-------------------------------------------------------
$('.add_column').on('click',function(){
    addColumn()
})
//-------------------------------------------------------
function addColumn(first){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('first', first);

    $.ajax({
        url :'/tables/add_column',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
           $('.all_cols').append(e)
        }
    })
}
//-------------------------------------------------------
function EditCol(){
    $('#types').modal('show')
}
//-------------------------------------------------------
function DelCol(id){
    $(`.the_column[data-id="${id}"]`).remove()
}
//-------------------------------------------------------