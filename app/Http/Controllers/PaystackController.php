<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Cart;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackController extends Controller
{
    protected $paystackSecretKey;
    protected $paystackPublicKey;

    public function __construct()
    {
        $this->paystackSecretKey = config('services.paystack.secret_key');
        $this->paystackPublicKey = config('services.paystack.public_key');
    }

    /**
     * Initialize Paystack transaction
     */
    public function initializeTransaction($orderId)
    {
        // Validate that an order exists
        $order = Order::with(['cart_info.product'])->find($orderId);
        
        if (!$order) {
            return redirect()->route('checkout')->with('error', 'Order not found');
        }

        // Get Paystack public key
        $paystackPublicKey = $this->paystackPublicKey;

        // Amount should be in kobo (subunit)
        $amountInKobo = $order->total_amount * 100;

        return view('frontend.pages.paystack-payment', compact('order', 'paystackPublicKey', 'amountInKobo'));
    }

    /**
     * Get payment initialization data
     */
    public function getPaymentData(Request $request, $orderId)
    {
        $order = Order::with(['cart_info.product'])->find($orderId);
        
        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        // Amount should be in kobo (subunit)
        $amountInKobo = $order->total_amount * 100;

        // Prepare data for Paystack API
        $data = [
            'email' => $order->email,
            'amount' => $amountInKobo,
            'reference' => $order->order_number,
            'callback_url' => route('paystack.callback'),
            'metadata' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'user_id' => auth()->user()->id,
                'first_name' => $order->first_name,
                'last_name' => $order->last_name,
                'phone' => $order->phone
            ]
        ];

        try {
            // Initialize transaction with Paystack
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->paystackSecretKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.paystack.co/transaction/initialize', $data);

            if ($response->successful()) {
                $responseData = $response->json();
                return response()->json([
                    'status' => true,
                    'access_code' => $responseData['data']['access_code'],
                    'authorization_url' => $responseData['data']['authorization_url'],
                    'message' => 'Transaction initialized successfully'
                ]);
            } else {
                Log::error('Paystack initialization failed: ' . $response->body());
                return response()->json([
                    'status' => false,
                    'error' => 'Failed to initialize transaction'
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Paystack error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'error' => 'An error occurred while initializing payment'
            ], 500);
        }
    }

    /**
     * Handle payment callback from Paystack
     */
    public function callback(Request $request)
    {
        $reference = $request->reference;

        try {
            // Verify the transaction with Paystack
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->paystackSecretKey,
            ])->get("https://api.paystack.co/transaction/verify/{$reference}");

            if ($response->successful()) {
                $data = $response->json();

                if ($data['data']['status'] === 'success') {
                    // Payment successful, update order
                    $order = Order::where('order_number', $reference)->first();

                    if ($order) {
                        $order->update([
                            'payment_status' => 'paid',
                            'status' => 'process'
                        ]);

                        // Clear cart and coupon session
                        session()->forget('cart');
                        session()->forget('coupon');

                        request()->session()->flash('success', 'Payment successful! Your order has been placed.');
                        return redirect()->route('home');
                    }
                } else {
                    request()->session()->flash('error', 'Payment verification failed. Please try again.');
                    return redirect()->route('checkout');
                }
            } else {
                Log::error('Paystack verification failed: ' . $response->body());
                request()->session()->flash('error', 'Unable to verify payment. Please contact support.');
                return redirect()->route('checkout');
            }
        } catch (\Exception $e) {
            Log::error('Paystack callback error: ' . $e->getMessage());
            request()->session()->flash('error', 'An error occurred while processing your payment.');
            return redirect()->route('checkout');
        }
    }

    /**
     * Webhook handler for Paystack charge.success events
     */
    public function webhook(Request $request)
    {
        // Verify the webhook signature
        $signature = $request->header('x-paystack-signature');
        $body = $request->getContent();

        $hash = hash_hmac('sha512', $body, $this->paystackSecretKey);

        if ($hash !== $signature) {
            Log::warning('Paystack webhook signature verification failed');
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        // Process the webhook
        $event = $request->json();

        if ($event['event'] === 'charge.success') {
            $paymentData = $event['data'];

            // Verify payment status and amount
            if ($paymentData['status'] === 'success') {
                // Find the order by reference
                $order = Order::where('order_number', $paymentData['reference'])->first();

                if ($order) {
                    // Verify the amount matches
                    $expectedAmount = $order->total_amount * 100; // Convert to kobo

                    if ($paymentData['amount'] === $expectedAmount) {
                        // Update order status
                        $order->update([
                            'payment_status' => 'paid',
                            'status' => 'process'
                        ]);

                        Log::info('Order ' . $order->order_number . ' payment confirmed via webhook');
                    } else {
                        Log::warning('Amount mismatch for order ' . $order->order_number);
                    }
                } else {
                    Log::warning('Order not found for reference: ' . $paymentData['reference']);
                }
            }
        }

        // Always return 200 OK to acknowledge receipt of webhook
        return response()->json(['message' => 'Webhook received'], 200);
    }
}
