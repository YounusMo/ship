let page = 1;
let id = null
let reportid = null
let table = $('.page_name').val().trim();
//---------------------------------------------------------
function load(){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('currency', $('.inp[data-name="currency"]').val());
    formData.append('from', $('.from').val());
    formData.append('to', $('.to').val());
    

    tableLoader('show','.main-table');

    $.ajax({
        url :'/client/transactions/load_transactions?page='+page,
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            tableLoader('hide','.main-table',e,100,()=>{
                $('.table_counter').text($('.count').val())
                afterLoad()

                sys_selector('.branch_selector')

            });
        }
    })
}
//---------------------------------------------------------------------------------------
$('.inp[data-name="currency"] , .from , .to').on('change',function(){
    load()
})
//---------------------------------------------------------------------------------------
function changeCur(cur){
    $('.inp[data-name="currency"]').val(cur)
    $('.trans').removeClass('d-none')
    load()
}
//---------------------------------------------------------------------------------------
function closeTrans(){
    $('.trans').addClass('d-none')
}
//---------------------------------------------------------------------------------------
function detectOS() {
  const ua = navigator.userAgent;

  if (/windows/i.test(ua)) return "Windows";
  if (/android/i.test(ua)) return "Android";
  if (/iphone|ipad|ipod/i.test(ua)) return "iOS";
  if (/mac os/i.test(ua)) return "MacOS";
  if (/linux/i.test(ua)) return "Linux";

  return "Unknown";
}
//---------------------------------------------------------------------------------------
function all_report_currency(){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('client_id', user_id);
    formData.append('currency', $('.inp[data-name="currency"]').val());
    formData.append('from', $('.from').val());
    formData.append('to', $('.to').val());
    
    $.ajax({
        url :'/client/transactions/print_reports',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            $('#report_content').html(e)
            
            let os = detectOS();

            if(os === 'iOS'){
                const content = document.getElementById('report_content').innerHTML;
                const win = window.open('', '_blank');
                win.document.write(content);
                win.document.close();
                win.focus();
                win.print();
                win.close();
            }else{
                printJS({printable:'report_content',type:'html'})
            }

                
            // printJS({
            //     printable: document.getElementById('report_content').innerHTML,
            //     type: 'raw-html'
            // });
        }
    })
}
//---------------------------------------------------------------------------------------