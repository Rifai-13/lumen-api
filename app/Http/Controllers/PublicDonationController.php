<?php
// app/Http/Controllers/PublicDonationController.php

namespace App\Http\Controllers;

use App\Models\Donation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PublicDonationController extends Controller
{
    /**
     * Store a new donation from public (no auth required)
     */
    public function store(Request $request)
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'full_name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'phone' => 'required|string|max:20',
                'amount' => 'required|numeric|min:10000',
                'campaign' => 'required|string|max:255',
                'payment_method' => 'required|string|max:50',
                'payment_provider' => 'required|string|max:50',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Generate transaction ID
            $transactionId = 'TRX' . time() . rand(100, 999);

            // Create donation
            $donation = Donation::create([
                'full_name' => $request->full_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'amount' => $request->amount,
                'campaign' => $request->campaign,
                'payment_method' => $request->payment_method,
                'payment_provider' => $request->payment_provider,
                'notes' => $request->notes,
                'status' => 'pending', // Default status
                'transaction_id' => $transactionId
            ]);

            // Return payment instructions based on payment method
            $paymentInstructions = $this->getPaymentInstructions($request->payment_method, $request->payment_provider);

            return response()->json([
                'success' => true,
                'message' => 'Donation created successfully',
                'donation' => $donation,
                'payment_instructions' => $paymentInstructions
            ], 201);

        } catch (\Exception $e) {
            Log::error('Public donation error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create donation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active campaigns (public)
     */
    public function getCampaigns()
    {
        // Return list of active campaigns
        $campaigns = [
            ['id' => 'education', 'name' => 'Education', 'description' => 'Support education for children'],
            ['id' => 'healthcare', 'name' => 'Healthcare', 'description' => 'Medical assistance for those in need'],
            ['id' => 'disaster', 'name' => 'Disaster Relief', 'description' => 'Emergency response for disasters'],
            ['id' => 'environment', 'name' => 'Environment', 'description' => 'Protect our environment']
        ];

        return response()->json([
            'success' => true,
            'data' => $campaigns
        ]);
    }

    /**
     * Get payment instructions based on method
     */
    private function getPaymentInstructions($method, $provider)
    {
        $instructions = [
            'bank_transfer' => [
                'steps' => [
                    'Transfer to bank account: 123-456-7890',
                    'Account holder: Yayasan HopeWorks',
                    "Bank: " . strtoupper($provider),
                    'Confirm your payment via WhatsApp'
                ]
            ],
            'ewallet' => [
                'steps' => [
                    'Open your ' . ucfirst($provider) . ' app',
                    'Scan QR code or pay to: 08123456789',
                    'Confirm your payment'
                ]
            ]
        ];

        return $instructions[$method] ?? null;
    }
}