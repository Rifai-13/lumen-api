<?php
// app/Http/Controllers/CampaignController.php

namespace App\Http\Controllers;

use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class CampaignController extends Controller
{
     /**
     * GET /campaigns - List all campaigns
     */
    public function index(Request $request)
    {
        try {
            Log::info('Fetching campaigns with params:', $request->all());
            
            $query = Campaign::query();
            
            // Search filter
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('description', 'LIKE', "%{$search}%")
                      ->orWhere('category', 'LIKE', "%{$search}%");
                });
            }
            
            // Status filter
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }
            
            // Pagination
            $perPage = $request->get('per_page', 10);
            $campaigns = $query->orderBy('created_at', 'desc')->paginate($perPage);
            
            Log::info('Found ' . $campaigns->total() . ' campaigns');
            
            return response()->json([
                'success' => true,
                'data' => $campaigns->items(),
                'current_page' => $campaigns->currentPage(),
                'last_page' => $campaigns->lastPage(),
                'per_page' => $campaigns->perPage(),
                'total' => $campaigns->total()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching campaigns: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch campaigns'
            ], 500);
        }
    }

    /**
     * GET /campaigns/stats - Get campaign statistics
     */
    public function getStats(Request $request)
    {
        try {
            Log::info('Fetching campaign stats');
            
            $totalCampaigns = Campaign::count();
            $activeCampaigns = Campaign::where('status', 'Active')->count();
            $totalRaised = Campaign::sum('raised') ?? 0;
            $totalDonors = Campaign::sum('donors') ?? 0;
            
            // Calculate average progress
            $campaigns = Campaign::select('raised', 'goal')->get();
            $totalProgress = 0;
            $count = 0;
            
            foreach ($campaigns as $campaign) {
                if ($campaign->goal > 0) {
                    $totalProgress += min(($campaign->raised / $campaign->goal) * 100, 100);
                    $count++;
                }
            }
            
            $averageProgress = $count > 0 ? round($totalProgress / $count, 2) : 0;
            
            return response()->json([
                'success' => true,
                'data' => [
                    'total_campaigns' => $totalCampaigns,
                    'active_campaigns' => $activeCampaigns,
                    'total_raised' => (float) $totalRaised,
                    'total_donors' => (int) $totalDonors,
                    'average_progress' => (float) $averageProgress
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch stats'
            ], 500);
        }
    }
    
    public function store(Request $request)
    {
        // Debug semua input
        Log::info('========== CAMPAIGN STORE DEBUG ==========');
        Log::info('All request:', $request->all());
        Log::info('Has file: ' . ($request->hasFile('image') ? 'YES' : 'NO'));
        
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            Log::info('File exists: ' . ($file ? 'YES' : 'NO'));
            Log::info('File is valid: ' . ($file->isValid() ? 'YES' : 'NO'));
            Log::info('File name: ' . $file->getClientOriginalName());
            Log::info('File size: ' . $file->getSize());
            Log::info('File mime: ' . $file->getMimeType());
            Log::info('File error: ' . $file->getError());
            Log::info('File error message: ' . $file->getErrorMessage());
        }

        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'category' => 'required|string|max:100',
                'goal' => 'required|numeric|min:1',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120'
            ]);

            if ($validator->fails()) {
                Log::error('Validation failed:', $validator->errors()->toArray());
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $imagePath = null;
            
            if ($request->hasFile('image') && $request->file('image')->isValid()) {
                $image = $request->file('image');
                
                // Generate unique filename
                $filename = time() . '_' . Str::slug($request->name) . '.' . $image->getClientOriginalExtension();
                
                // Pastikan folder ada
                $folder = storage_path('app/public/campaigns');
                if (!file_exists($folder)) {
                    mkdir($folder, 0777, true);
                    Log::info('Created folder: ' . $folder);
                }
                
                // Simpan file
                $path = $image->move($folder, $filename);
                
                if ($path) {
                    $imagePath = url('storage/campaigns/' . $filename);
                    Log::info('File saved to: ' . $path);
                    Log::info('URL: ' . $imagePath);
                } else {
                    Log::error('Failed to move file');
                }
            }

            $campaign = Campaign::create([
                'name' => $request->name,
                'slug' => Str::slug($request->name) . '-' . uniqid(),
                'description' => $request->description,
                'category' => $request->category,
                'goal' => $request->goal,
                'raised' => 0,
                'donors' => 0,
                'status' => 'Active',
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'image' => $imagePath
            ]);

            Log::info('Campaign created: ' . $campaign->id);

            return response()->json([
                'success' => true,
                'message' => 'Campaign created successfully',
                'data' => $campaign
            ], 201);

        } catch (\Exception $e) {
            Log::error('EXCEPTION: ' . $e->getMessage());
            Log::error('File: ' . $e->getFile());
            Log::error('Line: ' . $e->getLine());
            Log::error('Trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }
}