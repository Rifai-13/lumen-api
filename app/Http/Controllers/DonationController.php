<?php

namespace App\Http\Controllers;

use App\Models\Donation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class DonationController extends Controller
{
    /**
     * Get all donations (with filters)
     */
    public function index(Request $request)
    {
        try {
            $query = Donation::query();

            // Apply filters
            if ($request->has('campaign')) {
                $query->byCampaign($request->campaign);
            }

            if ($request->has('status')) {
                $query->byStatus($request->status);
            }

            if ($request->has('date_from') && $request->has('date_to')) {
                $query->whereBetween('created_at', [$request->date_from, $request->date_to]);
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $donations = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $donations
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch donations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new donation
     */
    public function store(Request $request)
    {
        // Validation rules
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'amount' => 'required|numeric|min:10000',
            'campaign' => 'required|in:education,healthcare,disaster',
            'payment_method' => 'required|in:bank_transfer,ewallet',
            'payment_provider' => 'required|string|max:50',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Generate unique transaction ID
            $transactionId = 'DON-' . strtoupper(Str::random(10));

            // Create donation
            $donation = Donation::create([
                'full_name' => $request->full_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'amount' => $request->amount,
                'campaign' => $request->campaign,
                'payment_method' => $request->payment_method,
                'payment_provider' => $request->payment_provider,
                'status' => Donation::STATUS_PENDING,
                'transaction_id' => $transactionId,
                'notes' => $request->notes
            ]);

            // Here you would typically integrate with payment gateway
            // For now, we'll just return success with pending status

            return response()->json([
                'success' => true,
                'message' => 'Donation created successfully',
                'data' => [
                    'donation' => $donation,
                    'payment_instructions' => $this->getPaymentInstructions($request->payment_method, $request->payment_provider)
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create donation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single donation by ID
     */
    public function show($id)
    {
        try {
            $donation = Donation::find($id);

            if (!$donation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Donation not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $donation
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch donation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update donation status (webhook callback)
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,success,failed',
            'transaction_id' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $donation = Donation::find($id);

            if (!$donation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Donation not found'
                ], 404);
            }

            $donation->status = $request->status;
            if ($request->transaction_id) {
                $donation->transaction_id = $request->transaction_id;
            }
            $donation->save();

            return response()->json([
                'success' => true,
                'message' => 'Donation status updated successfully',
                'data' => $donation
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update donation status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get donation statistics
     */
    public function getStats(Request $request)
    {
        try {
            $stats = [
                'total_donations' => Donation::count(),
                'total_amount' => Donation::success()->sum('amount'),
                'pending_amount' => Donation::where('status', 'pending')->sum('amount'),
                'successful_donations' => Donation::success()->count(),
                'today_donations' => Donation::today()->count(),
                'today_amount' => Donation::today()->success()->sum('amount'),
                'this_week_amount' => Donation::thisWeek()->success()->sum('amount'),
                'this_month_amount' => Donation::thisMonth()->success()->sum('amount'),
                'by_campaign' => [
                    'education' => Donation::byCampaign('education')->success()->sum('amount'),
                    'healthcare' => Donation::byCampaign('healthcare')->success()->sum('amount'),
                    'disaster' => Donation::byCampaign('disaster')->success()->sum('amount'),
                ],
                'recent_donations' => Donation::with('user')
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch donation statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment instructions based on method and provider
     */
    private function getPaymentInstructions($method, $provider)
    {
        $instructions = [
            'bank_transfer' => [
                'bri' => [
                    'bank' => 'Bank BRI',
                    'account_number' => '1234-5678-9012',
                    'account_name' => 'CharityConnect Foundation',
                    'steps' => [
                        'Transfer to BRI account: 1234-5678-9012',
                        'Account holder: CharityConnect Foundation',
                        'Include transaction ID in the notes',
                        'Payment will be confirmed within 1x24 hours'
                    ]
                ],
                'bni' => [
                    'bank' => 'Bank BNI',
                    'account_number' => '9876-5432-1098',
                    'account_name' => 'CharityConnect Foundation',
                    'steps' => [
                        'Transfer to BNI account: 9876-5432-1098',
                        'Account holder: CharityConnect Foundation',
                        'Include transaction ID in the notes',
                        'Payment will be confirmed within 1x24 hours'
                    ]
                ],
                // Add other banks...
            ],
            'ewallet' => [
                'ovo' => [
                    'provider' => 'OVO',
                    'phone_number' => '0812-3456-7890',
                    'steps' => [
                        'Open OVO application',
                        'Select "Transfer"',
                        'Enter phone number: 0812-3456-7890',
                        'Confirm payment'
                    ]
                ],
                'gopay' => [
                    'provider' => 'GoPay',
                    'phone_number' => '0812-3456-7890',
                    'steps' => [
                        'Open Gojek application',
                        'Select "GoPay"',
                        'Choose "Send"',
                        'Enter phone number: 0812-3456-7890',
                        'Confirm payment'
                    ]
                ]
                // Add other e-wallets...
            ]
        ];

        return $instructions[$method][$provider] ?? null;
    }
}