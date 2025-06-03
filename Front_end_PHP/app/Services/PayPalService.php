<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalService
{
    private $clientId;
    private $clientSecret;
    private $baseUrl;
    private $mode;

    public function __construct()
    {
        $this->clientId = env('PAYPAL_CLIENT_ID');
        $this->clientSecret = env('PAYPAL_CLIENT_SECRET');
        $this->mode = env('PAYPAL_MODE', 'sandbox');
        $this->baseUrl = $this->mode === 'sandbox' 
            ? env('PAYPAL_SANDBOX_BASE_URL', 'https://api-m.sandbox.paypal.com')
            : env('PAYPAL_LIVE_BASE_URL', 'https://api-m.paypal.com');
    }

    /**
     * Get access token from PayPal
     */
    private function getAccessToken()
    {
        try {
            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->asForm()
                ->post($this->baseUrl . '/v1/oauth2/token', [
                    'grant_type' => 'client_credentials'
                ]);

            if ($response->successful()) {
                return $response->json()['access_token'];
            }

            Log::error('PayPal Access Token Error: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('PayPal Access Token Exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create PayPal order
     */
    public function createOrder($amount, $currency = 'USD', $description = 'Order Payment')
    {
        $accessToken = $this->getAccessToken();
        
        if (!$accessToken) {
            return ['success' => false, 'message' => 'Unable to get PayPal access token'];
        }

        try {
            $orderData = [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'amount' => [
                            'currency_code' => $currency,
                            'value' => number_format($amount, 2, '.', '')
                        ],
                        'description' => $description
                    ]
                ],
                'application_context' => [
                    'return_url' => route('paypal.success'),
                    'cancel_url' => route('paypal.cancel'),
                    'brand_name' => 'Clexomart',
                    'user_action' => 'PAY_NOW'
                ]
            ];

            $response = Http::withToken($accessToken)
                ->post($this->baseUrl . '/v2/checkout/orders', $orderData);

            if ($response->successful()) {
                $orderResponse = $response->json();
                return [
                    'success' => true,
                    'order_id' => $orderResponse['id'],
                    'approval_url' => collect($orderResponse['links'])
                        ->firstWhere('rel', 'approve')['href'] ?? null
                ];
            }

            Log::error('PayPal Create Order Error: ' . $response->body());
            return ['success' => false, 'message' => 'Unable to create PayPal order'];
        } catch (\Exception $e) {
            Log::error('PayPal Create Order Exception: ' . $e->getMessage());
            return ['success' => false, 'message' => 'PayPal order creation failed'];
        }
    }

    /**
     * Capture PayPal order
     */
    public function captureOrder($orderId)
    {
        $accessToken = $this->getAccessToken();
        
        if (!$accessToken) {
            return ['success' => false, 'message' => 'Unable to get PayPal access token'];
        }

        try {
            $response = Http::withToken($accessToken)
                ->post($this->baseUrl . "/v2/checkout/orders/{$orderId}/capture");

            if ($response->successful()) {
                $captureResponse = $response->json();
                return [
                    'success' => true,
                    'capture_id' => $captureResponse['purchase_units'][0]['payments']['captures'][0]['id'] ?? null,
                    'status' => $captureResponse['status'],
                    'amount' => $captureResponse['purchase_units'][0]['payments']['captures'][0]['amount'] ?? null
                ];
            }

            Log::error('PayPal Capture Order Error: ' . $response->body());
            return ['success' => false, 'message' => 'Unable to capture PayPal payment'];
        } catch (\Exception $e) {
            Log::error('PayPal Capture Order Exception: ' . $e->getMessage());
            return ['success' => false, 'message' => 'PayPal payment capture failed'];
        }
    }

    /**
     * Get order details
     */
    public function getOrderDetails($orderId)
    {
        $accessToken = $this->getAccessToken();
        
        if (!$accessToken) {
            return ['success' => false, 'message' => 'Unable to get PayPal access token'];
        }

        try {
            $response = Http::withToken($accessToken)
                ->get($this->baseUrl . "/v2/checkout/orders/{$orderId}");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'order' => $response->json()
                ];
            }

            return ['success' => false, 'message' => 'Unable to get PayPal order details'];
        } catch (\Exception $e) {
            Log::error('PayPal Get Order Exception: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to get PayPal order details'];
        }
    }
} 