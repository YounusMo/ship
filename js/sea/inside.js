//---------------------------------------------------------
function loadinside(){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('search', $('.search').val());
    formData.append('showDeleted', showDeleted);
    
    tableLoader('show','.main-table');

    $.ajax({
        url :'/shipping/sea/load_inside?page='+page,
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
function showEject(id_){
    var formData = new FormData();

    id = id_

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('id', id);
    
    showLoader();

    $.ajax({
        url :'/shipping/sea/get_eject_modal',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            hideLoader()
            $('.ajax_elements').html(e);
            $('#eject').modal('show');

            $('.money').on('change keyup',function(){
                let val = $(this).val()
                val = parseInt(val.replace(/[^0-9]/g, '')) || 0;
                $(this).val(val);
            });
        }
    })
}
//---------------------------------------------------------------------------------------
function eject(){
    var formData = new FormData();

    let transaction_number = $('#eject .inp[data-name="transaction_number"]').val()
    let number = $('#eject .inp[data-name="number"]').val()
    let cbm = $('#eject .inp[data-name="cbm"]').val()
    let kg = $('#eject .inp[data-name="kg"]').val()
    let unit = $('#eject .inp[data-name="unit"]').val()
    let currency = $('#eject .inp[data-name="currency"]').val()
    let price = $('#eject .inp[data-name="price"]').val()
    let plus = $('#eject .inp[data-name="plus"]').val()

    if(!number || !cbm || !kg || !unit || !currency || !price){
        showErr(langContent['Insert required data']) 
        return;
    }

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('id', id);
    formData.append('transaction_number', transaction_number);
    formData.append('number', number);
    formData.append('price', price);
    formData.append('plus', plus);
    formData.append('cbm', cbm);
    formData.append('kg', kg);
    formData.append('unit', unit);
    formData.append('currency', currency);
    
    showLoader();

    $.ajax({
        url :'/shipping/sea/eject',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{

            switch (e.err) {
                case 'cbm':
                    showErr(langContent['CBM is not enough'])
                break;
                case 'kg':
                    showErr(langContent['Weight is not enough'])
                break;
                case 'number':
                    showErr(langContent['Number is not enough'])
                break;
                default:
                    makeAlert(langContent['Completed successfully'])
                    $('#eject').modal('hide');
                    loadinside();
                break;
            }
            
            hideLoader()
        },
        error:e=>{
            console.log(e)
            hideLoader()
            showErr('Somthing went wrong')
        }
    })
}
//---------------------------------------------------------------------------------------
