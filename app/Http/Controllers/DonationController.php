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

            // Hitung statistik - SESUAIKAN DENGAN STRUKTUR TABEL
            $totalDonations = Donation::count();
            $totalAmount = Donation::sum('amount') ?? 0;
            
            // PERBAIKAN 1: Hitung donor unik berdasarkan email (karena tidak ada user_id)
            $totalDonors = Donation::distinct('email')->count('email');
            
            $averageDonation = $totalDonations > 0 ? round($totalAmount / $totalDonations, 2) : 0;

            // Donasi hari ini
            $todayDonations = Donation::whereDate('created_at', Carbon::today())->count();
            $todayAmount = Donation::whereDate('created_at', Carbon::today())->sum('amount') ?? 0;

            // Donasi 7 hari terakhir
            $weeklyDonations = Donation::select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(amount) as total')
                )
                ->where('created_at', '>=', Carbon::now()->subDays(7))
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->get();

            // PERBAIKAN 2: Top campaigns - gunakan kolom 'campaign' (string) bukan campaign_id
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

            // PERBAIKAN 3: Recent donations - tidak pakai relasi user karena tidak ada user_id
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
                    'total_donations' => $totalDonations,
                    'total_amount' => $totalAmount,
                    'total_donors' => $totalDonors,
                    'average_donation' => $averageDonation,
                    'today_donations' => $todayDonations,
                    'today_amount' => $todayAmount,
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
     * Get all donations
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

            // PERBAIKAN 4: Query donations tanpa relasi user
            $query = Donation::query();

            // Filter berdasarkan role - staff hanya bisa lihat donasi dengan emailnya
            $userRole = $user->role ?? 'staff';
            if ($userRole === 'staff') {
                $query->where('email', $user->email);
            }

            // Filter berdasarkan campaign (string)
            if ($request->has('campaign')) {
                $query->where('campaign', 'like', '%' . $request->campaign . '%');
            }

            if ($request->has('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $donations = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $donations->items(),
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
                'message' => 'Failed to fetch donations'
            ], 500);
        }
    }
}