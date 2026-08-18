<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>{{ __('Razorpay Checkout') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
</head>

<body style="font-family: system-ui, sans-serif; padding: 40px; text-align: center;">
    <p>{{ __('Opening Razorpay…') }}</p>
    <form id="rzp-callback-form" method="POST" action="{{ $callback }}">
        @csrf
        <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id">
        <input type="hidden" name="razorpay_order_id" id="razorpay_order_id" value="{{ $rzpOrder['id'] ?? '' }}">
        <input type="hidden" name="razorpay_signature" id="razorpay_signature">
    </form>
    <script>
        var options = {
            key: "{{ $key_id }}",
            amount: {{ (int) ($rzpOrder['amount'] ?? 0) }},
            currency: "{{ $rzpOrder['currency'] ?? $order->currency }}",
            name: @json(brand_name()),
            description: "{{ $order->order_number }}",
            order_id: "{{ $rzpOrder['id'] ?? '' }}",
            handler: function(response) {
                document.getElementById('razorpay_payment_id').value = response.razorpay_payment_id;
                document.getElementById('razorpay_order_id').value = response.razorpay_order_id;
                document.getElementById('razorpay_signature').value = response.razorpay_signature;
                document.getElementById('rzp-callback-form').submit();
            },
            modal: {
                ondismiss: function() {
                    window.location = "{{ $callback }}?cancelled=1";
                }
            }
        };
        var rzp = new Razorpay(options);
        rzp.open();
    </script>
</body>

</html>
