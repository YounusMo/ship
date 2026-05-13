let page = 1;
let id = null
let table = $('.page_name').val().trim();
//---------------------------------------------------------
function load(){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('search', $('.search').val());
    formData.append('showDeleted', showDeleted);
    
    tableLoader('show','.main-table');

    $.ajax({
        url :'/branches/load?page='+page,
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
function edit(id_){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('id', id_);
    
    showLoader()

    $.ajax({
        url :'/branches/edit',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            hideLoader()
            id = id_
            $('#edit').remove();
            editMode = true;
            $('.ajax_elements').append(e)

            $('#edit').modal('show')

        }
    })
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
    formData.append('name_en', $('#new .inp[data-name="name_en"]').val());
    formData.append('name_zh', $('#new .inp[data-name="name_zh"]').val());
 

    if(ok){
        showLoader(true,'modal')
        
        $('.create_btn').attr('disabled',true)

        setTimeout(() => {
            $.ajax({
                url :'/branches/create',
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
    formData.append('name_en', $('#edit .inp[data-name="name_en"]').val());
    formData.append('name_zh', $('#edit .inp[data-name="name_zh"]').val());
    formData.append('id', id);
 

    if(ok){
        showLoader(true,'modal')
        
        $('.save_btn').attr('disabled',true)

        setTimeout(() => {
            $.ajax({
                url :'/branches/save',
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