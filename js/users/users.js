//---------------------------------------------------------
let page = 1;
let id   = null;
//---------------------------------------------------------
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

            showLoader()
            $.ajax({
                url :'/users/delete',
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
        showErr(langContent['Select first']);
    }
    
    
})
//---------------------------------------------------------
function load(){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('search', $('.search').val());

    tableLoader('show','.main-table');

    let width = $('.content').width() - 5

    $.ajax({
        url :'/users/load?page='+page,
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            tableLoader('hide','.main-table',e,100,()=>{
                $('.card-body').css('width',width+'px')

                $('#chk_all').on('click',function(){
                    if($(this).is(':checked')){
                        $('.chk_item').prop('checked',true)
                    }else{
                        $('.chk_item').prop('checked',false)
                    }
                })

                $('.edit').on('click',function(){
                    edit($(this).data('id'))
                })

                $('.change_pass').on('click',function(){
                    id = $(this).data('id')
                    $('#change_pass input').val('')
                    $('#change_pass').modal('show')
                })
                
                $('.page-link').on('click',function(e){
                    e.preventDefault()
                    page = $(this).text().trim()
                    load()
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
            });
        }
    })
}
//---------------------------------------------------------
$(window).resize(function() {
    let width = $('.content').width() - 5
    $('.card-body').css('width',width+'px')
});
//---------------------------------------------------------
load()
//---------------------------------------------------------
sys_selector('.branch_selector')
//---------------------------------------------------------
$('.search').on('keyup change',function(e){
    if(e.keyCode == 13){
        load()
    }
})
//---------------------------------------------------------
$('.new').on('click',function(){
    id = null
    
    $('#new_rec').modal('show')
    $('#new_rec .inp').val('')

    
    $('#new_rec input[data-name="code"]').parent().removeClass('d-none')

    reset_sys_selector();

    $('#new_rec .lets_create').removeClass('d-none')
    $('#new_rec .lets_save').addClass('d-none')

    $('#new_rec input[data-name="pass1"]').parent().removeClass('d-none')
    $('#new_rec input[data-name="pass2"]').parent().removeClass('d-none')

    $('#new_rec select').each(function(){
        let name = $(this).data('name')
        let first = $(`#new_rec select[data-name="${name}"] option`).first().val();
        $(this).val(first)
    })
    
    $('.branch_selector').addClass('d-none')
})
//---------------------------------------------------------
$('.lets_create').on('click',function(){
    create()
})
//---------------------------------------------------------
$('.inp[data-name="type"]').on('change',function(){
    let val = $(this).val()

    reset_sys_selector();

    if(val === 'branch_admin'){
        $('.branch_selector').removeClass('d-none')
    }else{
        $('.branch_selector').addClass('d-none')
    }
})
//---------------------------------------------------------
function passValidator(pass1,pass2){
    if(pass1 === pass2){
        if(pass1.length > 5 && pass2.length > 5){
            return true;
        }else{
            showErr(langContent['Password must be more than 5 characters'])
        }
    }else{
        showErr(langContent['Passwords do not match'])
        return false;
    }
}
//---------------------------------------------------------
function create(){

    let pass1 = $('.inp[data-name="pass1"]').val();
    let pass2 = $('.inp[data-name="pass2"]').val();
    let type  = $('.inp[data-name="type"]').val();
    
    let name   = $('.inp[data-name="name"]').val().trim();
    let email  = $('.inp[data-name="email"]').val().trim();
    let branch = $('.inp[data-name="branch"]').val().trim();

    let okPass = passValidator(pass1,pass2)
    
    let ok = true

    if(!name  || !type){
        ok = false;
    }
    
    let names  = [];
    let values = [];

    if(type === 'branch_admin' && branch === ''){
        ok = false;
    }

    $('#new_rec .req').each(function(){
        if($(this).val()){
            names.push($(this).data('name'))
            values.push($(this).val())
        }
    })


    if(ok){
        if(okPass){
            $('.lets_create').attr('disabled',true)
            
            var formData = new FormData();
            formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
            formData.append('code',$('#new_rec .inp[data-name="code"]').val());
            formData.append('name',$('#new_rec .inp[data-name="name"]').val());
            formData.append('pass1',pass1);
            formData.append('branch',branch);
            formData.append('type',type);
            formData.append('email',$('#new_rec .inp[data-name="email"]').val());

            showLoader(true,'modal')

            setTimeout(() => {
                $.ajax({
                    url :'/users/create',
                    type:'POST',
                    contentType: false,
                    processData: false,
                    async:true,
                    data:formData,
                    success:e=>{
                        $('.lets_create').attr('disabled',false)
                        if(e === 'exist'){
                            showErr(langContent['Already exist'])
                            return;
                        }
                        hideLoader(langContent['Created successfully'])
                        $('#new_rec').modal('hide')
                        load()
                    },
                    error:e=>{
                        hideLoader()
                        showErr(langContent['Somthing went wrong'])
                        $('.lets_create').attr('disabled',false)
                    }
                })
            }, 500);
        }
    }else{
       showErr(langContent['Insert required data']) 
    }
    
    
}
//---------------------------------------------------------
$('.save_pass').on('click',function(){
    let pass  = $('#change_pass .pass').val();
    let cpass = $('#change_pass .c_pass').val();
    ok = passValidator(pass,cpass)

    if(ok){
       
        let formData = new FormData();

        formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
        
        formData.append('password',pass);
        formData.append('id',id);
        
        showLoader(true,'modal')

        $.ajax({
            url :'/users/change_pass',
            type:'POST',
            contentType: false,
            processData: false,
            async:true,
            data:formData,
            success:e=>{
                makeAlert(langContent['Completed successfully'],'success') 
                $('#change_pass').modal('hide')
                load()
                hideLoader()
            },
            error:e=>{
                console.log(e)
            }
        }) 
        
    }
})
//---------------------------------------------------------
function edit(id_){
    let formData = new FormData();
    id = id_
    showLoader()
    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    
    formData.append('id',id);

    $.ajax({
        url :'/users/get',
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            console.log(e)
            $('#new_rec .lets_create').addClass('d-none')
            $('#new_rec input[data-name="pass1"]').parent().addClass('d-none')
            $('#new_rec input[data-name="pass2"]').parent().addClass('d-none')
            $('#new_rec input[data-name="code"]').parent().addClass('d-none')

            if(e[0].type === 'branch_admin'){
                $('.branch_selector').removeClass('d-none')
                $('#new_rec .inp[data-name="branch"]').val(e[0].type)
                $('#new_rec .branch_selector button').text(e[1])
            }else{
                $('.branch_selector').addClass('d-none')
            }

            $('#new_rec .lets_save').removeClass('d-none')
            $('#new_rec input[data-name="name"]').val(e[0].name)
            $('#new_rec input[data-name="code"]').val(e[0].code)
            $('#new_rec input[data-name="email"]').val(e[0].email)
            $('#new_rec select[data-name="type"]').val(e[0].type)

            $('#new_rec').modal('show')

            hideLoader()
        },
        error:e=>{
            console.log(e)
        }
    }) 
}
//---------------------------------------------------------
$('.lets_save').on('click',function(){
    let name = $('.inp[data-name="name"]').val().trim();
    let type = $('.inp[data-name="type"]').val().trim();
    let email= $('.inp[data-name="email"]').val().trim();
    let branch= $('.inp[data-name="branch"]').val().trim();

    let ok = true

    if(!name || !type){
        ok = false;
    }

    
    if(type === 'branch_admin' && branch === ''){
        ok = false;
    }


    if(ok){
        let formData = new FormData();

        formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
        
        formData.append('name',name);
        formData.append('email',email);
        formData.append('type',type);
        formData.append('branch',branch);
        formData.append('id',id);

        showLoader(true,'modal')
    
        $.ajax({
            url :'/users/save',
            type:'POST',
            contentType: false,
            processData: false,
            async:true,
            data:formData,
            success:e=>{
                if(e !== 'exist'){
                    makeAlert(langContent['Saved successfully'],'success')
                    load();
                }else{
                    makeAlert(langContent['Already exist'],'err')
                }
                hideLoader()

                $('#new_rec').modal('hide')
            },
            error:e=>{
                console.log(e)
            }
        }) 
    }else{
        showErr(langContent['Insert required data']) 
    }
   
})
//---------------------------------------------------------