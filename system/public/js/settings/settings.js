//---------------------------------------------------------
$('.nav-tabs .nav-link').on('click',function(e){
    e.preventDefault();
    let tab = $(this).attr('href')
    $('.nav-link').removeClass('active')
    $(this).addClass('active')

    $('.tab').addClass('d-none')
    $('.tab[data-tab="'+tab+'"]').removeClass('d-none')

})
//---------------------------------------------------------
// $('.submit').on('click',function(){
//     $('form').submit()
// })
//---------------------------------------------------------