$(document).ready(function(){
    
    var $main_el = $('.container.main');
    var $loading_el = $('.container.loader');
    var $form_els = $('form');

    var defaultLoaderOptions = {
        size: 35,
        maxSize: 35,
        imageColor: '#9FADBD'
    };

    var overlayLoader = function(show,options){
        if($.LoadingOverlay){
            $loading_el.LoadingOverlay( show, $.extend( options || {}, defaultLoaderOptions));
        }
    };

    $form_els.on('submit', function (e) {
        $('[type=submit]',$(this)).attr('disabled','disabled');
    });

    if($loading_el.length){
        window.setTimeout(function(){
            overlayLoader('hide');
            $loading_el.remove();
            $main_el.removeClass('hidden');
        },2000);
        overlayLoader('show');
    }else{
        $main_el.removeClass('hidden');
    }

});