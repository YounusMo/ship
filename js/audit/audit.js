//---------------------------------------------------------
// audit log viewer — read-only, admin-only
//---------------------------------------------------------
let page = 1;

function load(){
    var formData = new FormData();

    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    $('.filter').each(function(){
        formData.append($(this).data('name'), $(this).val());
    });

    tableLoader('show','.main-table');

    let width = $('.content').width() - 5;

    $.ajax({
        url :'/audit/load?page=' + page,
        type:'POST',
        contentType: false,
        processData: false,
        async:true,
        data:formData,
        success:e=>{
            tableLoader('hide','.main-table',e,100,()=>{
                $('.card-body').css('width', width + 'px');

                // backfill the action / target_table dropdowns from the
                // server-rendered hidden <select>s so users only see values
                // that actually exist in the log.
                $('[data-fill]').each(function(){
                    let name = $(this).data('fill');
                    let current = $('.filter[data-name="' + name + '"]').val();
                    let $target = $('.filter[data-name="' + name + '"]');
                    if ($target.length && $target.find('option').length <= 1) {
                        $(this).find('option').each(function(){
                            $target.append('<option value="' + $(this).val() + '">' + $(this).text() + '</option>');
                        });
                        if (current) $target.val(current);
                    }
                });

                $('.page-link').on('click', function(e){
                    e.preventDefault();
                    page = $(this).text().trim();
                    load();
                });
            });
        }
    });
}

$(function(){
    $('.filter').on('change', function(){
        page = 1;
        load();
    });
    load();
});
