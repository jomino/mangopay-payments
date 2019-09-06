$(document).ready(function(){
    
    if($.fn.datetimepicker){
        $('.input-group.date').datetimepicker({
            locale: 'fr',
            format: 'L'
        });
    }

});