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
    });

    // Capture Guest / Customer Data (including billing address)
    // Debounced: waits 600 ms after the last keystroke/blur before sending,
    // so rapid field-switching never fires multiple simultaneous requests.
    var acFields = [
        '#billing_email',
        '#billing_first_name',
        '#billing_phone',
        '#billing_address_1',
        '#billing_address_2',
        '#billing_city',
        '#billing_state',
        '#billing_postcode',
        '#billing_country'
    ];

    var acDebounceTimer = null;

    $(acFields.join(', ')).on('blur change', function(){
        clearTimeout(acDebounceTimer);
        acDebounceTimer = setTimeout(function () {
            var phone = $('#billing_phone').val();
            var email = $('#billing_email').val();

            // Only send if we have at least a phone or email
            if (phone.length === 0 && email.length === 0) {
                return;
            }

            $.ajax({
                url: ac_ajax_object.ajax_url,
                type: 'POST',
                data: {
                    action:            'ac_capture_guest',
                    email:             email,
                    name:              $('#billing_first_name').val(),
                    phone:             phone,
                    billing_address_1: $('#billing_address_1').val(),
                    billing_address_2: $('#billing_address_2').val(),
                    billing_city:      $('#billing_city').val(),
                    billing_state:     $('#billing_state').val(),
                    billing_postcode:  $('#billing_postcode').val(),
                    billing_country:   $('#billing_country').val(),
                    security:          ac_ajax_object.nonce
                },
                success: function(response){
                    console.log('Guest data saved:', response);
                }
            });
        }, 600); // 600 ms debounce
    });

});

