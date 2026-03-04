<?php
// app/Http/Controllers/DonationController.php

namespace App\Http\Controllers;

use App\Models\Donation;
use App\Models\Campaign;
use App\Models\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DonationController extends Controller
{
    /**
     * Get donation statistics
     */
    public function getStats(Request $request)
    {
        try {
            // Dapatkan user dari middleware (AuthenticateWithSession)
            $user = $request->auth_user;

            if (!$user) {
                $token = $request->bearerToken();
                if ($token) {
                    $session = Session::with('user')
                        ->where('id', $token)
                        ->where('expires_at', '>', Carbon::now())
                        ->first();

                    if ($session && $session->user) {
                        $user = $session->user;
                    }
                }
            }

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Hitung statistik dengan penanganan null
            $totalDonations = Donation::count();
            $totalAmount = Donation::sum('amount') ?? 0;
            $totalDonors = Donation::distinct('email')->count('email');

            // Hitung success rate
            $successCount = Donation::where('status', 'success')->count();
            $successRate = $totalDonations > 0
                ? round(($successCount / $totalDonations) * 100, 1)
                : 0;

            // Hitung pending dan failed
            $pendingCount = Donation::where('status', 'pending')->count();
            $failedCount = Donation::where('status', 'failed')->count();

            // Average donation
            $averageDonation = $totalDonations > 0
                ? round($totalAmount / $totalDonations, 2)
                : 0;

            // Donasi hari ini
            $todayDonations = Donation::whereDate('created_at', Carbon::today())->count();
            $todayAmount = Donation::whereDate('created_at', Carbon::today())->sum('amount') ?? 0;

            // Donasi minggu ini
            $weekStart = Carbon::now()->startOfWeek();
            $weekEnd = Carbon::now()->endOfWeek();
            $weeklyAmount = Donation::whereBetween('created_at', [$weekStart, $weekEnd])->sum('amount') ?? 0;

            // Donasi bulan ini
            $monthlyAmount = Donation::whereMonth('created_at', Carbon::now()->month)
                ->whereYear('created_at', Carbon::now()->year)
                ->sum('amount') ?? 0;

            // Hitung growth (compare dengan bulan lalu)
            $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
            $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

            $lastMonthAmount = Donation::whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->sum('amount') ?? 0;
            $lastMonthDonors = Donation::whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
                ->distinct('email')
                ->count('email');

            // Revenue growth
            $revenueGrowth = $lastMonthAmount > 0
                ? round((($totalAmount - $lastMonthAmount) / $lastMonthAmount) * 100, 1)
                : 0;

            // Donor growth
            $donorGrowth = $lastMonthDonors > 0
                ? round((($totalDonors - $lastMonthDonors) / $lastMonthDonors) * 100, 1)
                : 0;

            // Weekly donations chart data
            $weeklyDonations = Donation::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total')
            )
                ->where('created_at', '>=', Carbon::now()->subDays(7))
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->get();

            // Top campaigns
            $topCampaigns = Donation::select(
                'campaign',
                DB::raw('COUNT(*) as donation_count'),
                DB::raw('SUM(amount) as total_amount')
            )
                ->groupBy('campaign')
                ->orderBy('total_amount', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    return [
                        'campaign_name' => $item->campaign,
                        'donation_count' => $item->donation_count,
                        'total_amount' => $item->total_amount
                    ];
                });

            // Recent donations
            $recentDonations = Donation::orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($donation) {
                    return [
                        'id' => $donation->id,
                        'amount' => $donation->amount,
                        'donor_name' => $donation->full_name,
                        'donor_email' => $donation->email,
                        'campaign_name' => $donation->campaign,
                        'created_at' => $donation->created_at->format('Y-m-d H:i:s'),
                        'status' => $donation->status
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'total_revenue' => (float) $totalAmount,
                    'total_donors' => (int) $totalDonors,
                    'success_rate' => (float) $successRate,
                    'pending_count' => (int) $pendingCount,
                    'failed_count' => (int) $failedCount,
                    'average_donation' => (float) $averageDonation,
                    'today_amount' => (float) $todayAmount,
                    'weekly_amount' => (float) $weeklyAmount,
                    'monthly_amount' => (float) $monthlyAmount,
                    'revenue_growth' => (float) $revenueGrowth,
                    'donor_growth' => (float) $donorGrowth,
                    'weekly_donations' => $weeklyDonations,
                    'top_campaigns' => $topCampaigns,
                    'recent_donations' => $recentDonations
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Donation stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch donation stats: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all donations with pagination
     */

    public function index(Request $request)
    {
        try {
            $user = $request->auth_user;

            if (!$user) {
                $token = $request->bearerToken();
                if ($token) {
                    $session = Session::with('user')
                        ->where('id', $token)
                        ->where('expires_at', '>', Carbon::now())
                        ->first();

                    if ($session && $session->user) {
                        $user = $session->user;
                    }
                }
            }

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Query donations - TANPA FILTER DEFAULT
            $query = Donation::query();

            // HANYA TERAPKAN FILTER JIKA ADA PARAMETER
            // Filter berdasarkan role - staff hanya bisa lihat donasi dengan emailnya
            if ($user->role === 'staff') {
                $query->where('email', $user->email);
            }

            // Filter berdasarkan campaign (hanya jika ada parameter)
            if ($request->has('campaign') && !empty($request->campaign) && $request->campaign !== 'All Campaigns') {
                $query->where('campaign', 'like', '%' . $request->campaign . '%');
            }

            // Filter berdasarkan status (hanya jika ada parameter)
            if ($request->has('status') && !empty($request->status) && $request->status !== 'All Status' && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            // Filter tanggal (hanya jika ada parameter)
            if ($request->has('start_date') && !empty($request->start_date)) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }

            if ($request->has('end_date') && !empty($request->end_date)) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }

            // Search (hanya jika ada parameter)
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('full_name', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%")
                        ->orWhere('transaction_id', 'LIKE', "%{$search}%")
                        ->orWhere('phone', 'LIKE', "%{$search}%");
                });
            }

            // LOG QUERY UNTUK DEBUG
            $sql = $query->toSql();
            $bindings = $query->getBindings();
            Log::info('Donation query: ' . $sql);
            Log::info('Bindings: ' . json_encode($bindings));

            // Pagination
            $perPage = $request->get('per_page', 15);
            $donations = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // LOG JUMLAH DATA
            Log::info('Total donations found: ' . $donations->total());

            // Transform data
            $transformedDonations = collect($donations->items())->map(function ($donation) {
                return [
                    'id' => $donation->id,
                    'transaction_id' => $donation->transaction_id,
                    'full_name' => $donation->full_name,
                    'email' => $donation->email,
                    'phone' => $donation->phone,
                    'amount' => (float) $donation->amount,
                    'campaign' => $donation->campaign,
                    'payment_method' => $donation->payment_method,
                    'payment_provider' => $donation->payment_provider,
                    'status' => $donation->status,
                    'notes' => $donation->notes,
                    'created_at' => $donation->created_at->toISOString(),
                    'updated_at' => $donation->updated_at->toISOString()
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $transformedDonations,
                'pagination' => [
                    'current_page' => $donations->currentPage(),
                    'last_page' => $donations->lastPage(),
                    'per_page' => $donations->perPage(),
                    'total' => $donations->total()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Donation index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch donations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new donation (for admin/manual entry)
     */
    public function store(Request $request)
    {
        try {
            $user = $request->auth_user;

            // Jika route ini butuh Auth, aktifkan. Tapi kalau ini dipakai user publik, abaikan auth check ini.
            // if (!$user) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Unauthorized'
            //     ], 401);
            // }

            // Validasi
            $validated = $request->validate([
                'full_name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'phone' => 'required|string|max:20',
                'amount' => 'required|numeric|min:1000',
                'campaign' => 'required|string|max:255',
                'payment_method' => 'required|string|max:50',
                'payment_provider' => 'required|string|max:50',
                'status' => 'sometimes|in:pending,success,failed',
                'notes' => 'nullable|string',
                // 🔥 TAMBAH VALIDASI UNTUK TRANSACTION ID
                'transaction_id' => 'nullable|string|max:255' 
            ]);

            // 🔥 PERBAIKAN: Ambil ID dari request, jika kosong baru bikin TRX baru
            $transactionId = $request->input('transaction_id') ?? ('TRX' . time() . rand(100, 999));

            // Create donation
            $donation = Donation::create([
                'full_name' => $validated['full_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'amount' => $validated['amount'],
                'campaign' => $validated['campaign'],
                'payment_method' => $validated['payment_method'],
                'payment_provider' => $validated['payment_provider'],
                'notes' => $validated['notes'] ?? null,
                'status' => $validated['status'] ?? 'pending',
                'transaction_id' => $transactionId // Simpan ID Xendit ke sini!
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Donation created successfully',
                'data' => $donation
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Donation store error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create donation: ' . $e->getMessage()
            ], 500);
        }
    }
}