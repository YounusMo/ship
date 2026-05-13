//---------------------------------------------------------
let checkedItem = [];
//---------------------------------------------------------
function loadoutside(){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('search', $('.search').val());
    formData.append('showDeleted', showDeleted);
    
    tableLoader('show','.main-table');

    $.ajax({
        url :'/shipping/sky/load_outside?page='+page,
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
function insert_exist(){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('container_id', $('#exist_ .inp[data-name="container"]').val());
    formData.append('ids', checkedItem.join(',')   );
    
    tableLoader('show','.main-table');

    $.ajax({
        url :'/shipping/sky/insert_exist',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            loadoutside()
            hideLoader()
            $('#exist_').modal('hide')
            checkedItem = [];
        }
    })
}
//---------------------------------------------------------------------------------------
function create_container(){
    var formData = new FormData();

    let number = $('#new_container .inp[data-name="number"]').val()
    let name   = $('#new_container .inp[data-name="name"]').val()
    let arrival= $('#new_container .inp[data-name="arrival"]').val()
    // let type   = $('#new_container .inp[data-name="type"]').val()
    // let size   = $('#new_container .inp[data-name="size"]').val()
    let supplier   = $('#new_container .inp[data-name="supplier"]').val()
    let notes   = $('#new_container .inp[data-name="notes"]').val()


    if(!number || !name || !arrival   || !supplier){
        showErr(langContent['Insert required data']) 
        return;
    }

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('ids', JSON.stringify(checkedItem));
    formData.append('number', number);
    formData.append('name', name);
    formData.append('arrival', arrival);
    // formData.append('type', type);
    // formData.append('size', size);
    formData.append('supplier', supplier);
    formData.append('notes', notes);
    
    showLoader();

    $.ajax({
        url :'/shipping/sky/create_container',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            makeAlert(langContent['Created successfully'])
            hideLoader()
            loadoutside()

            $('#new_container').modal('hide')
            $('#new_container .inp').val('')
            checkedItem = [];
        },
        error:e=>{
            console.log(e)
            hideLoader()
            showErr('Somthing went wrong')
        }
    })
}
//---------------------------------------------------------------------------------------
$('.new_container').on('click',function(){
    checkedItem = [];

    $('.chk_item').each(function(){
        if($(this).is(':checked')){
            checkedItem.push($(this).val())
        }
    })

    if(checkedItem.length > 0){
        $('#new_container').modal('show')
    }else{
        showErr('Select first');
    }
})
//---------------------------------------------------------------------------------------
$('.insert_to_exist').on('click',function(){
    checkedItem = [];
    $('.chk_item').each(function(){
        if($(this).is(':checked')){
            checkedItem.push($(this).val())
        }
    })

    if(checkedItem.length > 0){
        reset_sys_selector();
        $('#exist_').modal('show')
    }else{
        showErr('Select first');
    }
})
//---------------------------------------------------------------------------------------
sys_selector('#new_container .supplier')
sys_selector('#exist_ .container_selector')
//---------------------------------------------------------------------------------------
