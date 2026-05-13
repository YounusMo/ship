$('form').on('submit',function(){
    $('.loading_btn').removeClass('d-none')
    $('.login_btn').attr('disabled',true)
})

//-----------------------------------------
if($('.err_').val() === 'yes'){
     $('.loading_x').addClass('d-none')
                    
    $('.brand-img').addClass('active_img')
    $('form').css('height','unset')
    $('form').addClass('active_frm')
}else{
    setTimeout(() => {
        $('.loading_x').addClass('d-none')
                    
        $('.brand-img').addClass('active_img')
        $('form').css('height','unset')
        $('form').addClass('active_frm')
    }, 1000);
}
//-----------------------------------------