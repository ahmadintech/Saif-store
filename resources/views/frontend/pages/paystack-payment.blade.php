@extends('frontend.layouts.master')

@section('title','Paystack Payment')

@section('main-content')

    <!-- Breadcrumbs -->
    <div class="breadcrumbs">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="bread-inner">
                        <ul class="bread-list">
                            <li><a href="{{route('home')}}">Home<i class="ti-arrow-right"></i></a></li>
                            <li class="active"><a href="javascript:void(0)">Payment</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- End Breadcrumbs -->

    <!-- Start Payment Section -->
    <section class="shop checkout section">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 col-12" style="margin: 0 auto;">
                    <div class="checkout-form">
                        <h2>Complete Your Payment</h2>
                        <p>Your order number: <strong>{{ $order->order_number }}</strong></p>
                        
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                            <h4>Order Summary</h4>
                            <table class="table">
                                <tr>
                                    <th>Item</th>
                                    <th>Quantity</th>
                                    <th>Amount</th>
                                </tr>
                                @forelse($order->cart_info as $item)
                                    <tr>
                                        <td>{{ $item->product->title }}</td>
                                        <td>{{ $item->quantity }}</td>
                                        <td>₦{{ number_format($item->amount, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3">No items in this order</td>
                                    </tr>
                                @endforelse
                                <tr style="border-top: 2px solid #ddd; font-weight: bold;">
                                    <td colspan="2">Total Amount:</td>
                                    <td>₦{{ number_format($order->total_amount, 2) }}</td>
                                </tr>
                            </table>
                        </div>

                        <div style="margin: 30px 0;">
                            <button type="button" id="paystack-btn" class="btn" style="width: 100%; padding: 15px; font-size: 16px;">
                                Pay with Paystack (₦{{ number_format($order->total_amount, 2) }})
                            </button>
                        </div>

                        <div style="text-align: center; color: #666; margin-top: 20px;">
                            <p><small>Your payment is secured and encrypted. This page will redirect you after successful payment.</small></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- End Payment Section -->

@endsection

@push('scripts')
<script>
document.getElementById('paystack-btn').addEventListener('click', function(e) {
    e.preventDefault();
    
    const btn = this;
    const originalText = btn.innerHTML;
    
    // Disable button and show spinner
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Initializing...';
    
    // Get payment initialization data from backend
    fetch('{{ route("paystack.payment-data", $order->id) }}', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        }
    })
    .then(async response => {
        if (!response.ok) {
            const errorText = await response.text();
            console.error('Server error details:', errorText);
            let errorMessage = 'Server responded with status ' + response.status;
            try {
                const errorData = JSON.parse(errorText);
                errorMessage = errorData.error || errorMessage;
            } catch(e) {}
            throw new Error(errorMessage);
        }
        return response.json();
    })
    .then(data => {
        if (data.status && data.authorization_url) {
            console.log('Payment initialized successfully. Redirecting...');
            // Redirect to Paystack's authorization URL
            window.location.href = data.authorization_url;
        } else {
            throw new Error(data.error || 'Failed to initialize payment. Please try again.');
        }
    })
    .catch(error => {
        console.error('Paystack Initialization Error:', error);
        alert(error.message || 'An error occurred while processing payment. Please try again.');
        
        // Reset button on error
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
});
</script>
@endpush
