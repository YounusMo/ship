function sys_selector(className = '' , callback = null){
    
    let where = null;

    if(className){
        where = className;
    }else{
        where = editMode ? '#edit' : '#new';
    }

    $(where + ' .sm_search').on('keyup change',function(){
        let dataFor = $(this).data('for')
        search($(this).val(),'.sys_selector[data-name="'+dataFor+'"] li')
    })

    $(where + ' .sys_selector ul li').on('click',function(){
        let dataFor = $(this).data('name')
        let val = $(this).data('val')
        let txt = $(this).data('txt')
        
        $(`${where} .sys_selector[data-name="${dataFor}"] .form-select`).text(txt)
        $(`${where} .sys_selector[data-name="${dataFor}"] .inp`).val(val)
    })

    $(where + ' .sys_selector ul a').on('click',function(e){
        e.preventDefault()
    })

    if(callback){
        callback()
    }
}

function reset_sys_selector(){
    $(`.sys_selector .form-select`).text(langContent['Select'])
    $(`.sys_selector .inp`).val(null)
    $(`.sys_selector .sm_search`).val(null)
}

sys_selector()