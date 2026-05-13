//---------------------------------------------------------
let selectedFiles = [];
let deletedFiles  = [];
//---------------------------------------------------------
function loadReceived(){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('search', $('.search').val());
    formData.append('showDeleted', showDeleted);
    
    tableLoader('show','.main-table');

    $.ajax({
        url :'/shipping/sky/load_received?page='+page,
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
function editReseved(id_){
    var formData = new FormData();

    id = id_

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('id', id);
    
    showLoader();

    $.ajax({
        url :'/shipping/sky/edit_received',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            hideLoader()
            $('#edit_reseved').remove();
            $('.ajax_elements').html(e);
            $('#edit_reseved').modal('show');

            selectedFiles = []
            deletedFiles  = []

            $("#edit_reseved #file_").on("change", function(event){
                const files = event.target.files;

                $.each(files, function(index, file){
                    if(file){
                        const reader = new FileReader();
                        let id = transactionNumber('img', index);

                        reader.onload = function(e){
                            // حفظ الملف في المصفوفة مع الـ id
                            selectedFiles.push({id: id, file: file});
                            $("#edit_reseved #preview").append(`
                                <div style="position: relative" class='main_img mx-2' data-id='${id}'>
                                    <button onclick="removeImg('${id}')">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 48 48">
                                            <g fill="none" stroke-linejoin="round" stroke-width="4">
                                                <path fill="#ff2f2fff" stroke="#000" d="M24 44C35.0457 44 44 35.0457 44 24C44 12.9543 35.0457 4 24 4C12.9543 4 4 12.9543 4 24C4 35.0457 12.9543 44 24 44Z" />
                                                <path stroke="#fff" stroke-linecap="round" d="M29.6567 18.3432L18.343 29.6569" />
                                                <path stroke="#fff" stroke-linecap="round" d="M18.3433 18.3432L29.657 29.6569" />
                                            </g>
                                        </svg>
                                    </button>
                                    <img src="${e.target.result}" alt="">
                                </div>    
                            `);
                        }
                        reader.readAsDataURL(file);
                    }
                });

                setTimeout(() => {
                    countIMGSEdit()
                }, 10);
            });
        }
    })
}
//---------------------------------------------------------------------------------------
sys_selector('#new_received .client_selector',()=>{
    $('#new_received .client_selector li').hide()

    $('#new_received .client_selector .sm_search').on('change keyup',function(){
        if($(this).val() === ''){
            $('#new_received .client_selector li').hide()
        }
    })
})
//---------------------------------------------------------------------------------------
function next_received(){

    let client_id = $('#new_received .client_selector .inp[data-name="client_id"]').val()
    let ship_from = $('#new_received .client_selector .inp[data-name="ship_from"]').val()

    if(!client_id || !ship_from){
        showErr(langContent['Insert required data']) 
        return;
    }
    
    showLoader(true,'modal')
        
    $('.next_received_btn').attr('disabled',true)

    var formData = new FormData();
    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('id',client_id);

    $.ajax({
        url :'/clients/get_client_data',
        type:'POST',
        processData: false,
        contentType: false,
        async:true,
        data:formData,
        success:e=>{
            hideLoader()
            $('#new_received .client_selector').addClass('d-none')
            $('#new_received .new_received_btn').removeClass('d-none')
            $('#new_received .next_received_btn').addClass('d-none')
            $('#new_received .data').removeClass('d-none')
            $('#new_received .inp[data-name="client_code"]').val(e.code)
            $('#new_received .client_name').val(e.name)

            $('.new_received_btn').attr('disabled',false)

            selectedFiles = []
            deletedFiles  = []
        },
        error:e=>{
            console.log(e)
            hideLoader()
            showErr('Somthing went wrong')
            $('.next_received_btn').attr('disabled',false)
        }
    })

    $('#new_received .client_selector').addClass('d-none')
    $('#new_received .data').removeClass('d-none')
}
//---------------------------------------------------------------------------------------
function save_received(){

    let names = [];
    let values= [];

    let ok = true;

    $('#edit_reseved .req').each(function(){
        if($(this).val() === ''){
            ok = false;
            console.log($(this).data('name'))
        }
    })

    $('#edit_reseved .inp').each(function(){
        names.push($(this).data('name'))
        values.push($(this).val())
    })

    var formData = new FormData();
    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('names', JSON.stringify(names));
    formData.append('values', JSON.stringify(values));
    formData.append('id', id);
    formData.append('deletedFiles', JSON.stringify(deletedFiles));
 
    selectedFiles.forEach((item, index) => {
        formData.append("images[]", item.file);
    });

    if(ok){
        showLoader(true,'modal')
        
        $('.save_received_btn').attr('disabled',true)

        setTimeout(() => {
            $.ajax({
                url :'/shipping/sky/save_received',
                type:'POST',
                processData: false,
                contentType: false,
                async:true,
                data:formData,
                success:e=>{
                    
                    hideLoader(langContent['Saved successfully'])
                    $('#edit_reseved').modal('hide')
                    $('.save_received_btn').attr('disabled',false)

                    $('#edit_reseved .inp').val('')

                    loadReceived()
                },
                error:e=>{
                    console.log(e)
                    hideLoader()
                    showErr('Somthing went wrong')
                    $('.save_received_btn').attr('disabled',false)
                }
            })
        }, 500);
    }else{
        showErr(langContent['Insert required data']) 
    } 
}
//---------------------------------------------------------------------------------------
$('.show_received').on('click',function(){
    $('#new_received .client_selector').removeClass('d-none')
    $('#new_received .data').addClass('d-none')

    $('#new_received .next_received_btn').attr('disabled',false)

    $('#new_received .client_selector li').hide()
    
    $('#new_received .new_received_btn').addClass('d-none')
    $('#new_received .next_received_btn').removeClass('d-none')

    $('#new_received').modal('show')
    $('#new_received .inp[data-name="ship_from"]').val('china')
    // $('#new_received .inp').val(null)

    reset_sys_selector();
    
    $('#new_received .inp[data-name="transaction_number"]').val(transactionNumber('received',5))
})
//---------------------------------------------------------------------------------------
function new_received(){

    let names = [];
    let values= [];

    let ok = true;

    $('#new_received .req').each(function(){
        if($(this).val() === ''){
            ok = false;
            console.log($(this).data('name'))
        }
    })

    $('#new_received .inp').each(function(){
        names.push($(this).data('name'))
        values.push($(this).val())
    })

    var formData = new FormData();
    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('names', JSON.stringify(names));
    formData.append('values', JSON.stringify(values));

    selectedFiles.forEach((item, index) => {
        formData.append("images[]", item.file);
    });
 
    if(ok){
        showLoader(true,'modal')
        
        $('.new_received_btn').attr('disabled',true)

        setTimeout(() => {
            $.ajax({
                url :'/shipping/sky/new_received',
                type:'POST',
                processData: false,
                contentType: false,
                async:true,
                data:formData,
                success:e=>{
                    
                    hideLoader(langContent['Created successfully'])
                    $('#new_received').modal('hide')
                    $('.new_received_btn').attr('disabled',false)

                    $('#new_received .inp').val('')
                    $('#new_received .inp[data-name="ship_from"]').val('china')
                    
                    reset_sys_selector();

                    loadReceived()
                },
                error:e=>{
                    console.log(e)
                    hideLoader()
                    showErr('Somthing went wrong')
                    $('.new_received_btn').attr('disabled',false)
                }
            })
        }, 500);
    }else{
        showErr(langContent['Insert required data']) 
    }   
}
//---------------------------------------------------------------------------------------
function cancel_in_container(id){

    ask(langContent['Do you want to cancel ?'] , ()=>{

        var formData = new FormData();
        formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
        formData.append('id', id);

        $.ajax({
            url :'/shipping/sky/cancel',
            type:'POST',
            processData: false,
            contentType: false,
            async:true,
            data:formData,
            success:e=>{
                loadReceived()
            },
            error:e=>{
                console.log(e)
                hideLoader()
                showErr('Somthing went wrong')
            }
        })
    } , ()=>false)
}
//---------------------------------------------------------------------------------------
function removeImg(id){
    selectedFiles = selectedFiles.filter(item => item.id !== id);

    $('#new_received #preview .main_img[data-id="'+id+'"]').remove()

    countIMGS()
}
//---------------------------------------------------------------------------------------
function countIMGS(){
    let count = $('#new_received #preview .main_img').length;

    if(count < 1){
        $('#new_received .file_holder').removeClass('d-none')
    }else{
        $('#new_received .file_holder').addClass('d-none')
    }
}
//---------------------------------------------------------------------------------------
$("#new_received #file_").on("change", function(event){
    const files = event.target.files;

    $.each(files, function(index, file){
        if(file){
            const reader = new FileReader();
            let id = transactionNumber('img', index);

            reader.onload = function(e){
                // حفظ الملف في المصفوفة مع الـ id
                selectedFiles.push({id: id, file: file});

                $("#new_received #preview").append(`
                    <div style="position: relative" class='main_img mx-2' data-id='${id}'>
                        <button onclick="removeImg('${id}')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 48 48">
                                <g fill="none" stroke-linejoin="round" stroke-width="4">
                                    <path fill="#ff2f2fff" stroke="#000" d="M24 44C35.0457 44 44 35.0457 44 24C44 12.9543 35.0457 4 24 4C12.9543 4 4 12.9543 4 24C4 35.0457 12.9543 44 24 44Z" />
                                    <path stroke="#fff" stroke-linecap="round" d="M29.6567 18.3432L18.343 29.6569" />
                                    <path stroke="#fff" stroke-linecap="round" d="M18.3433 18.3432L29.657 29.6569" />
                                </g>
                            </svg>
                        </button>
                        <img src="${e.target.result}" alt="">
                    </div>    
                `);
            }
            reader.readAsDataURL(file);
        }
    });

    setTimeout(() => {
        countIMGS()
    }, 10);
});
//---------------------------------------------------------------------------------------
function removeImgEdit(id){
    deletedFiles.push(id)
    $('#edit_reseved #preview .main_img[data-id="'+id+'"]').remove()
    
    countIMGSEdit()
}
//---------------------------------------------------------------------------------------
function countIMGSEdit(){
    let count = $('#edit_reseved #preview .main_img').length;

    if(count < 1){
        $('#edit_reseved .file_holder').removeClass('d-none')
    }else{
        $('#edit_reseved .file_holder').addClass('d-none')
    }
}
//---------------------------------------------------------------------------------------