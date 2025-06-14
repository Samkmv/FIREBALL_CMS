$(function(){

    let addToCart = $('.add-to-cart');

    // Remove from Cart
    $('body').on('click', '.btn-remove', function(e){
        e.preventDefault();
        let btn = $(this);
        let btnText = btn.find('.text');
        let loader = btn.find('.loader');
        let productId = btn.data('id');

        $.ajax({
            url: baseUrl + '/remove-from-cart',
            method: 'GET',
            data: {
                'product_id': productId,
            },
            beforeSend: function () {
                // btn.prop('disabled', true);
                $('.btn-remove').prop('disabled', true);
                btnText.addClass('d-none');
                loader.removeClass('d-none');
            },
            success: function (result) {
                toastr.success(result.data);
                $('.product-id-' + productId).find(addToCart).removeClass('btn-secondary').addClass('btn-dark').text('В корзину');
                $('#shoppingCart .offcanvas-body').html(result.mini_cart);
                $('#countCart').text(result.cart_qty);
                console.log(result);
            },
            error: function (request) {
                toastr.error(request.responseText);
                console.log(request);
            }
        });
    });

    // Add to Cart
    $(addToCart).on('click', function(e){
        e.preventDefault();
        let btn = $(this);
        let btnText = btn.find('.text');
        let loader = btn.find('.loader');
        let productId = btn.data('id');

        $.ajax({
            url: baseUrl + '/add-to-cart',
            method: 'GET',
            data: {
                'product_id': productId,
            },
            beforeSend: function () {
                // btn.prop('disabled', true);
                addToCart.prop('disabled', true);
                btnText.addClass('d-none');
                loader.removeClass('d-none');
            },
            success: function (result) {
                toastr.success(result.data);
                $('.product-id-' + productId).find(addToCart).removeClass('btn-dark').addClass('btn-secondary').text('В корзине');
                $('#shoppingCart .offcanvas-body').html(result.mini_cart);
                $('#countCart').text(result.cart_qty);
                console.log(result);
            },
            error: function (request) {
                toastr.error(request.responseText);
                console.log(request);
            },
            complete: function () {
                setTimeout(function () {
                    // btn.prop('disabled', false);
                    addToCart.prop('disabled', false);
                    btnText.removeClass('d-none');
                    loader.addClass('d-none');
                }, 500);
            },
        });
    });

});

toastr.options = {
    "closeButton": false,
    "debug": false,
    "newestOnTop": false,
    "progressBar": false,
    "positionClass": "toast-bottom-right",
    "preventDuplicates": false,
    "onclick": null,
    "showDuration": "300",
    "hideDuration": "1000",
    "timeOut": "5000",
    "extendedTimeOut": "1000",
    "showEasing": "swing",
    "hideEasing": "linear",
    "showMethod": "fadeIn",
    "hideMethod": "fadeOut"
}