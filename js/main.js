//--------------------------------------------------------------
let showDeleted = 'false'
let editMode = false;
let langContent = [];
let user_type  = $('.user_type').val()
let user_id  = $('.user_id').val()
let assets_url = $('.assets_url').val();
//--------------------------------------------------------------
function numberFormat(number) {
    const truncated = Math.floor(number * 100) / 100;
    return truncated.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}
//--------------------------------------------------------------
$('.toggle_menu').on('click',function(){
    $('.sidebar').toggleClass('closed')
})
//--------------------------------------------------------------
function get_cur(cur, what) {
    const currencies = [
        {
            code: 'usd',
            text: langContent['USD'],
            symbol: '$',
        },
        {
            code: 'den',
            text: langContent['LYD'],
            symbol: 'د.ل',
        },
        {
            code: 'eur',
            text: langContent['Euro'],
            symbol: '€',
            color: 'red'
        },
        {
            code: 'cny',
            text: langContent['RMB'],
            symbol: '¥',
            color: 'red'
        },
    ];

    // ابحث عن العملة بحسب الكود
    const currency = currencies.find(c => c.code === cur);

    // إذا وجدنا العملة وأيضاً الخاصية موجودة، نرجعها
    if (currency && currency[what] !== undefined) {
        return currency[what];
    }

    // إذا لم نجد شيء نرجع null أو قيمة افتراضية
    return null;
}
//--------------------------------------------------------------
function save_exchange(){
    
    var formData = new FormData();
    const token = $('meta[name="csrf-token"]').attr('content');

    let cny = $('.inp_exc[data-name="currency_cny"]').val()
    let eur = $('.inp_exc[data-name="currency_eur"]').val()
    let den = $('.inp_exc[data-name="currency_den"]').val()

    if(!cny || !eur || !den){
        showErr(langContent['Insert required data']) 
        return
    }

    formData.append('_token', token);
    formData.append('currency_cny',cny );
    formData.append('currency_eur',eur );
    formData.append('currency_den',den );
    
    $.ajax({
        url :'/settings/update_exchange',
        type:'POST',
        contentType: false,
        processData: false,
        async:false,
        data:formData,
        success:e=>{
            makeAlert(langContent['Completed successfully'],'success')
            $('#exchange').modal('hide')
        }
    })

}
//--------------------------------------------------------------
function loadLang(){
    $.ajax({
        url:'/get_lang',
        type:'GET',
        async:false,
        contentType: false,
        processData: false,
        success:e=>{
            langContent = JSON.parse(e)
        }
    })

}
//-------------------------------------------------------
loadLang()
//--------------------------------------------------------------
function customLoader(type , where = '' , response = '' , time = 1500 , callback){
    let tr = ``;

    for(let i = 0 ; i < 8 ; i++){
        tr += `
            <div class="placeholder long mb-3 py-3"></div>
        `
    }
 
    if(type === 'show'){
        $(where).html(tr)
    }

    if(type === 'hide'){
        setTimeout(() => {
            $('.placeholder_custom').remove()
            $(where).html(response)


            if(callback){
                callback();
            }
            
            $('.placeholder_custom').removeClass('placeholder_custom')
        }, time);
    }
 
}
//--------------------------------------------------------------
function afterLoad(){

    let width = $('.content').width() - 5

    $('.card-body').css('width',width+'px')
    

    $('#chk_all').on('click',function(){
        if($(this).is(':checked')){
            $('.chk_item').prop('checked',true)
        }else{
            $('.chk_item').prop('checked',false)
        }
    })

    $('.page-link').on('click',function(e){
        e.preventDefault()
        page = $(this).text().trim()
        let shipping_pages = ['sea','sky'];
        
        if(shipping_pages.includes($('.page_name').val())){
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

            page = 1
        }else{
            load()
        }

    })
    
    var $chkboxes = $('table input[type="checkbox"]');
    var lastChecked = null;
    
    $chkboxes.click(function(e) {
        if (!lastChecked) {
            lastChecked = this;
            return;
        }
    
        if (e.shiftKey) {
            var start = $chkboxes.index(this);
            var end = $chkboxes.index(lastChecked);
            $chkboxes.slice(Math.min(start,end), Math.max(start,end)+ 1).prop('checked', lastChecked.checked);
        }
        lastChecked = this;
    });
}
//--------------------------------------------------------------
function showLoader(opacity = false , where =''){
    $('.loader_sys').addClass('active_loader')

    
    if(opacity && where){
        if(where === 'card'){
            where = 'card'
        }
        if(where === 'modal'){
            where = 'modal-content'
        }
        $('.'+where).append(`
            <div class='opacity_loader'></div>
        `)

        setTimeout(() => {
            $('.opacity_loader').css('visibility','visible')
            $('.opacity_loader').css('opacity',1)
        }, 100);
    }
   
}
//-------------------------------------------------------
function makeAlert(title,type){
    
    if(type === 'success'){
        hideLoader(title,1)

        $('.btn-primary:not(.table .btn-primary)').attr('disabled',true)

        setTimeout(() => {
            $('.btn-primary').attr('disabled',false)
        }, 1000);
        
    }else if(type === 'err'){
        showErr(title,1)
    }else if(type === 'warning'){
        Swal.fire(
            '',
            title,
            'warning'
        )
    }
}
//-------------------------------------------------------
/**
 * Detect a 423 period_closed response and offer admins an audited override.
 * Returns true if the response was handled (period_closed) so callers can stop.
 *   xhr      — jqXHR from $.ajax error callback
 *   retryFn  — fn(extraHeaders) that re-fires the request
 */
function handlePeriodClosed(xhr, retryFn) {
    if (!xhr || xhr.status !== 423) return false;
    let body = null;
    try { body = xhr.responseJSON || JSON.parse(xhr.responseText); } catch (e) {}
    if (!body || body.type !== 'period_closed') return false;

    const userType = $('.user_type').val();
    if (userType !== 'admin') {
        hideLoader();
        showErr(body.message || 'Accounting period is closed.');
        return true;
    }

    hideLoader();
    setTimeout(() => {
        const ok = confirm((body.message || 'Period is closed.') + '\n\nOverride and post anyway? (admin only, logged)');
        if (ok && typeof retryFn === 'function') {
            retryFn({ 'X-Override-Closed-Period': 'yes' });
        }
    }, 100);
    return true;
}

function showErr(txt,timer){
    setTimeout(() => {
        $('.loader_sys').removeClass('active_loader')
        $('.loader_sys_err').addClass('active_loader')

        $('.opacity_loader').remove()


        $('.opacity_loader').css('visibility','hidden')
        $('.opacity_loader').css('opacity',0)
        
        $('.err_txt').text(txt)
        setTimeout(() => {
            $('.loader_sys_err').removeClass('active_loader')
        }, 2500);
    }, timer);
}
//-------------------------------------------------------
function hideLoader(txt = '',timer = 0){
    setTimeout(() => {
        $('.loader_sys').removeClass('active_loader')
       
        $('.opacity_loader').remove()


        $('.opacity_loader').css('visibility','hidden')
        $('.opacity_loader').css('opacity',0)
        
        if(txt){
            $('.loader_sys_success').addClass('active_loader')
            $('.success_txt').text(txt)
            
            setTimeout(() => {
                $('.loader_sys_success').removeClass('active_loader')
            }, 2000);
        }
      
    }, timer);
}
//--------------------------------------------------------------
function tableLoader(type , where = '' , response = '' , time = 1500 , callback){
    let tr = ``;

    for(let i = 0 ; i < 10 ; i++){
        tr += `
            <tr>
                <td><div class="placeholder long"></div></td>
                <td><div class="placeholder medium"></div></td>
                <td><div class="placeholder short"></div></td>
                <td><div class="placeholder medium"></div></td>
                <td><div class="placeholder long"></div></td>
            </tr>
        `
    }
    let loader = `
        <table class="table-loader">
            <thead>
            <tr>
                <th><div class="placeholder long"></div></th>
                <th><div class="placeholder medium"></div></th>
                <th><div class="placeholder short"></div></th>
                <th><div class="placeholder medium"></div></th>
                <th><div class="placeholder long"></div></th>
            </tr>
            </thead>
            <tbody>
                ${tr}
            </tbody>
        </table>
    `

    if(type === 'show'){
        $(where).html(loader)
    }

    if(type === 'hide'){
        setTimeout(() => {
            $('.table-loader').remove()
            $(where).html(response)


            if(callback){
                callback();
            }
            
            $('.placeholder').removeClass('placeholder')
        }, time);
    }
}
//--------------------------------------------------------------
let loadedElements = [];
//--------------------------------------------------------------
$('.delete').on('click',function(){
    
    let ids = [];

    $('.chk_item').each(function(){
        if($(this).is(':checked')){
            ids.push($(this).val())
        }
    })

    if(ids.length > 0 ){
        ask(langContent['Do you want to delete ?'],()=>{
            var formData = new FormData();

            formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
            formData.append('ids', JSON.stringify(ids));
            formData.append('table', $(this).data('table'));

            showLoader()
            $.ajax({
                url :'/del_recs',
                type:'POST',
                contentType: false,
                processData: false,
                async:true,
                data:formData,
                success:e=>{
                    console.log(e)
                    hideLoader(langContent['Deleted Successfully'])
                    load();
                }
            })

            },()=>false
        )
    }else{
        showErr(langContent['Select first'])
    }
    
    
})
//--------------------------------------------------------------
$('.delete_permanent').on('click',function(){
    
    let ids = [];

    $('.chk_item').each(function(){
        if($(this).is(':checked')){
            ids.push($(this).val())
        }
    })

    if(ids.length > 0 ){
        ask(langContent['Do you want to delete ?'],()=>{
            var formData = new FormData();

            formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
            formData.append('ids', JSON.stringify(ids));
            formData.append('table', $(this).data('table'));

            showLoader()
            $.ajax({
                url :'/del_recs_permanent',
                type:'POST',
                contentType: false,
                processData: false,
                async:true,
                data:formData,
                success:e=>{
                    console.log(e)
                    hideLoader(langContent['Deleted Successfully'])
                    if( $(this).data('table') === 'carousel' ){
                        setTimeout(() => {
                            window.location.reload();
                        }, 400);
                    }else{
                        load();
                    }
                }
            })

            },()=>false
        )
    }else{
        showErr(langContent['Select first'])
    }
    
    
})
//--------------------------------------------------------------
$('.restore').on('click',function(){
    
    let ids = [];

    $('.chk_item').each(function(){
        if($(this).is(':checked')){
            ids.push($(this).val())
        }
    })

    if(ids.length > 0 ){
        ask('Do you want to restore ?',()=>{
            var formData = new FormData();

            formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
            formData.append('ids', JSON.stringify(ids));
            formData.append('table', $(this).data('table'));

            showLoader()
            $.ajax({
                url :'/restore_recs',
                type:'POST',
                contentType: false,
                processData: false,
                async:true,
                data:formData,
                success:e=>{
                    console.log(e)
                    hideLoader('Restored Successfully')
                    load();
                }
            })

            },()=>false
        )
    }else{
        showErr(langContent['Select first'])
    }
    
    
})
//--------------------------------------------------------------
function ajaxElementLoader(element,callback){
    var formData = new FormData();
    
    if(!loadedElements.includes(element)){
        formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
        formData.append('element',element);
        $('.ajax_elements').empty()
        $.ajax({
            url :'/load_ajax_element',
            type:'POST',
            contentType: false,
            processData: false,
            async:true,
            data:formData,
            success:e=>{
                // loadedElements.push(element)
                $('.ajax_elements').append(e)
                callback()
            }
        })
    }else{
        callback()
    }
}
//--------------------------------------------------------------
$(function() {
    $('.lazy').Lazy(); // يبدأ التحميل عند الظهور
});
//--------------------------------------------------------------
function search(value,item){
    var value = value.toLowerCase();
    $(item).filter(function() {
     $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
    });
}
//--------------------------------------------------------------
function ask(title,yes,no,translate = true,input = false){
    showPopup = true
    if(input){
        Swal.fire({
            title:title,
            text:  '',
            input: "text",
            icon: "",
            iconHtml: "",
            inputAttributes: {
              autocapitalize: "off"
            },
            showCancelButton: true,
            confirmButtonText: "Ok",
            showLoaderOnConfirm: true,
            allowOutsideClick: () => !Swal.isLoading()
          }).then((result) => {
            if (result.isConfirmed) {
                yes(result)
            }else{
                no()
            }
          });
    }else{
        Swal.fire({
            title:title,
            showCancelButton: true,
            icon: 'question',
            iconHtml: '?',
            cancelButtonText: langContent['No'],
            confirmButtonText: langContent['Yes'],
        }).then((result) => {
            /* Read more about isConfirmed, isDenied below */
            if (result.isConfirmed) {
                yes()
            }else{
                no()
            }
        })
    }
}
//--------------------------------------------------------------
function imgErr(img ,src) {
    img.src = src
    console.clear()
}
//--------------------------------------------------------------
$('.numeric').on('change keyup',function(){
    let val = $(this).val()
    val = val.replace(/[^0-9]/g, '');
    $(this).val(val);
})
//--------------------------------------------------------------
function transactionNumber(action, id) {
    const userId = user_id; // Replace with your actual user ID retrieval logic
    const now = new Date();

    const datePart = now.toISOString().slice(0, 10).replace(/-/g, ''); // 'YYYYMMDD'
    const timePart = now.toTimeString().slice(0, 8).replace(/:/g, ''); // 'HHMMSS'
    const randomPart = Math.floor(Math.random() * 9999) + 1; // Random number between 1 and 9999
    const uniquePart = Date.now().toString(36) + Math.random().toString(36).substring(2, 8); // Unique ID

    const rand = `${action}_${id}_${userId}_${datePart}_${timePart}${randomPart}${uniquePart}`;
    return rand;
}
//--------------------------------------------------------------
$('.money').on('change keyup',function(){
    let val = $(this).val()
    val = parseInt(val.replace(/[^0-9]/g, '')) || 0;
    console.log(val)
    $(this).val(val);
});
//--------------------------------------------------------------
function moneyType(input) {
    let val = input.value;
    val = parseInt(val.replace(/[^0-9]/g, '')) || 0;
    input.value = val;
}
//--------------------------------------------------------------