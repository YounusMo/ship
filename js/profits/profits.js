let page = 1;
let id = null
let table = $('.page_name').val().trim();
//---------------------------------------------------------
function load(){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    
    formData.append('from', $('.from').val());
    formData.append('to', $('.to').val());
    
    tableLoader('show','.main-table');

    $.ajax({
        url :'/profits/load',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            tableLoader('hide','.main-table',e,100,()=>{
                afterLoad()

                $('.toggler').on('click',function(){
                    let id = $(this).data('tab')

                    $(`.tab[data-tab='${id}']`).toggleClass('d-none')
                    $(`.main_tr[data-tab='${id}'] td , .main_tr[data-tab='${id}'] th`).toggleClass('border-hide')

                    $(`.toggler[data-tab='${id}'] .left`).toggleClass('d-none')
                    $(`.toggler[data-tab='${id}'] .down`).toggleClass('d-none')
                })
            });
        }
    })
}
//---------------------------------------------------------------------------------------
load()
//---------------------------------------------------------------------------------------
$('.from, .to').on('change',function(){
    load()
})
//---------------------------------------------------------------------------------------
$('.print').on('click',function(){
    
    $('#printable svg').hide();
    $(`#printable .tab`).css('display',"none")
    $(`#printable .center`).css('text-align',"center")

    printJS({printable:'printable',type:'html'})

    setTimeout(() => {
        $(`.hidd`).css('display',"table-cell")
        $('#printable svg').show();
        $(`.tab`).css('display',"")
    }, 1000);
})
//---------------------------------------------------------------------------------------
$('.print_all').on('click',function(){
    
    $('#printable svg').hide();
    $(`#printable .tab`).css('padding-right',"20px")
    $(`#printable .center`).css('text-align',"center")

    printJS({printable:'printable',type:'html'})

    setTimeout(() => {
        // $(`.hidd`).css('display',"table-cell")
        $('#printable svg').show();
        // $(`.tab`).css('display',"")
    }, 1000);
})
//---------------------------------------------------------------------------------------