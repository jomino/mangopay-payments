$(document).ready(function(){

    var cardExpirationDate = '';
    var $cardExpirationDate = $('input[name=cardExpirationDate]');
    
    if($.fn.selectpicker){
        $('#month-selector').on('changed.bs.select', function (e) {
            cardExpirationDate = $(this).val() + cardExpirationDate.substring(2,4);
            $cardExpirationDate.val(cardExpirationDate);
        });
        $('#year-selector').on('changed.bs.select', function (e) {
            cardExpirationDate = cardExpirationDate.substring(0,2) + $(this).val();
            $cardExpirationDate.val(cardExpirationDate);
        });
    }

});