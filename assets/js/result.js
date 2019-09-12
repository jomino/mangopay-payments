$(document).ready(function(){
    
    var check_url = $('input[name="check-url"]').val();
    var $loading_el = $('.loader-container');
    var $hiden_el = $('.result-text');
    var $print_btn = $('.btn-print');
    var $print_container = $('.print-container');

    var max_retry = 3;

    var defaultLoaderOptions = {
        background : false,
        minSize: false
    };

    var getUrl = function(url,callback){
        window.fetch( url, {
            credentials: 'same-origin'
        }).then(function(response){
            if(response.ok) {
                response.json().then(function(o){
                    callback(o);
                });
            }
        });
    };

    var onPrintFinished = function(){
        $print_container.html('');
    };

    var onPrintLoaded = function(response){
        var html = window.atob(response.html);
        $print_container.html(html);
        if(window.printJS){
            window.printJS({
                printable: 'print-container',
                type: 'html',
                fallbackPrintable: onPrintFinished
            });
        }
    };

    var print = function(){
        if(check_url){
            getUrl('/print/'+check_url,onPrintLoaded);
        }
    };

    $print_btn.on('click',function(){
        print();
    });

    var onChecked = function(response){
        if(response.status && response.status!='UNKNOW'){
            $hiden_el.toggleClass('hidden visible').text(response.status);
            overlayLoader('hide');
            $loading_el.toggleClass('hidden');
        }else{
            if((--max_retry)>=0){
                start();
            }else{
                $hiden_el.toggleClass('hidden visible').text('Un email vous a été envoyé.');
                overlayLoader('hide');
                $loading_el.toggleClass('hidden');
            }
        }
    };

    var overlayLoader = function(show,options){
        if($.LoadingOverlay){
            $loading_el.LoadingOverlay(show,options);
        }
    };

    var launch = function(){
        if(check_url){
            getUrl('/check/' + check_url,onChecked);
        }
    };

    var start = function(){
        window.setTimeout(launch,5000);
    };

    overlayLoader('show',defaultLoaderOptions);
    start();

});