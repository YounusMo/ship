let page = 1;
let id = null
let table_type =  $('.start_table').val()
let table = $('.page_name').val().trim();
//---------------------------------------------------------------------------------------
$('.search').on('keyup',function(e){
    if(e.keyCode == 13){
        switch (table_type) {
            case 'reseved':
                loadReceived()    
            break;
            case 'inside':
                loadinside()    
            break;
            case 'outside':
                loadoutside()    
            break;
            case 'containers':
                loadcontainers()    
            break;
            case 'canceled_containers':
                loadCanceledcontainers()    
            break;
            case 'canceled':
                loadcanceled()    
            break;
        }
    }
})
//---------------------------------------------------------------------------------------
$('.sys-nav-tab').on('click',function(){
    let tab = $(this).data('tab')

    $('.sys-nav-tab').removeClass('active')
    $(this).addClass('active')

    $('.table_counter').text(0)

    switch (tab) {
        case 'received':
            loadReceived()    
            $('.show_received').removeClass('d-none')
            $('.new_container,.new_custom_container,.insert_to_exist').addClass('d-none')
            table_type = 'reseved'
        break;
        case 'inside':
            $('.show_received,.new_container,.new_custom_container,.insert_to_exist').addClass('d-none')
            loadinside()    
            table_type = 'inside'
        break;
        case 'outside':
            $('.show_received').addClass('d-none')
            $('.new_custom_container').addClass('d-none')
            $('.new_container').removeClass('d-none')
            $('.insert_to_exist').removeClass('d-none')
            loadoutside()    
            table_type = 'outside'
        break;
        case 'containers':
            $('.show_received').addClass('d-none')
            $('.new_container,.insert_to_exist').addClass('d-none')
            $('.new_custom_container').removeClass('d-none')
            loadcontainers()    
            table_type = 'containers'
        break;
        case 'canceled_containers':
            $('.show_received').addClass('d-none')
            $('.new_container,.insert_to_exist').addClass('d-none')
            $('.new_custom_container').addClass('d-none')
            loadCanceledcontainers()    
            table_type = 'canceled_containers'
        break;
        case 'canceled':
            $('.show_received,.insert_to_exist').addClass('d-none')
            $('.new_container').addClass('d-none')
            $('.new_custom_container').addClass('d-none')
            loadcanceled()    
            table_type = 'canceled'
        break;
    }
})
//---------------------------------------------------------------------------------------