jQuery(document).ready(function($){

    // Track Add to Cart Event
    $('body').on('added_to_cart', function(event, fragments, cart_hash, $button){
        console.log('Product added to cart, triggering tracking...');
        $.ajax({
            url: ac_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'ac_track_cart',
                product_id: $button.data('product_id'),
                quantity: $button.data('quantity') || 1,
                security: ac_ajax_object.nonce
            },
            success: function(response){
                console.log('Cart tracked:', response);
            }
        });
    });

    // Optional: Restore Link Click Tracking (Guest)
    $('a.ac-restore-link').on('click', function(e){
        var link = $(this).attr('href');
        console.log('Restore link clicked:', link);
        // Optionally, you can send an AJAX hit for tracking
    });

    // Optional: Capture Guest Data
    $('#billing_email, #billing_first_name, #billing_phone').on('blur', function(){
        var email = $('#billing_email').val();
        var name  = $('#billing_first_name').val();
        var phone = $('#billing_phone').val();

        console.log('Guest data captured:', email, name, phone);

        if(phone.length > 0) {
            $.ajax({
                url: ac_ajax_object.ajax_url,
                type: 'POST',
                data: {
                    action: 'ac_capture_guest',
                    email: email,
                    name: name,
                    phone: phone,
                    security: ac_ajax_object.nonce
                },
                success: function(response){
                    console.log("Guest data saved:", response);
                }
            });
        }
    });

});
