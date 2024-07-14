jQuery(document).ready(function($) {
    // Function to handle form submission
    function handlePartialPaymentFormSubmission() {
        // Prevent default form submission
        event.preventDefault();

        // Get REST API endpoint and nonce from restApiSettings
        const ajaxUrl = restApiSettings.restUrl;
        const nonce = restApiSettings.nonce;

        // Get partial payment amount and order ID from form fields
        const partialPaymentAmount = $('#partial_payment_amount').val();
        const orderId = $('#partial_payment_id').val();
        $('#update_partial_payment_button').prop('disabled', true).text('Please wait...');

        // Clear any previous messages from local storage
        localStorage.removeItem('partialPaymentMessage');

        // AJAX request configuration
        $.ajax({
            type: 'POST',
            url: ajaxUrl,
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', nonce); // Set nonce header for security
            },
            data: {
                'order_id': orderId,
                'partial_amount': partialPaymentAmount
            },
            success: function(response) {
                // Check response for success
                if (response.result === 'success') {
                    // Store success message temporarily in local storage
                    localStorage.setItem('partialPaymentMessage', JSON.stringify({ type: 'success', message: 'Partial payment updated successfully!' }));
                } else {
                    // Store error message temporarily in local storage
                    localStorage.setItem('partialPaymentMessage', JSON.stringify({ type: 'error', message: 'Error: ' + response.message }));
                }

                // Reload the page
                location.reload();
            },
            error: function(error) {
                // Store error message temporarily in local storage
                localStorage.setItem('partialPaymentMessage', JSON.stringify({ type: 'error', message: 'Error: Failed to update partial payment.' }));

                // Reload the page
                location.reload();
            }
        });
    }

    // Bind form submission handler
    $('form.woocommerce-form-partial-payment').on('submit', handlePartialPaymentFormSubmission);

    // Check for stored message on page load and display it
    const storedMessage = JSON.parse(localStorage.getItem('partialPaymentMessage'));
    if (storedMessage && storedMessage.message) {
        const messageClass = storedMessage.type === 'success' ? 'woocommerce-message' : 'woocommerce-error';
        $('.woocommerce-notices-wrapper').html('<div class="' + messageClass + '">' + storedMessage.message + '</div>');

        // Clear stored message after displaying it
        localStorage.removeItem('partialPaymentMessage');
    }
});
