<?php
// app/Http/Controllers/CampaignController.php

namespace App\Http\Controllers;

use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;

class CampaignController extends Controller
{
    /**
     * Get all campaigns
     */
    public function index(Request $request)
    {
        try {
            $query = Campaign::query();

            // Filter by status
            if ($request->has('status') && $request->status != 'all') {
                $query->where('status', $request->status);
            }

            // Filter by category
            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            // Search by name
            if ($request->has('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            // Sort
            $sortField = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortField, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 10);
            $campaigns = $query->paginate($perPage);

            // PERBAIKAN: Manual transform data
            $items = $campaigns->items();
            foreach ($items as $campaign) {
                $campaign->progress = $campaign->progress;
                $campaign->remaining = $campaign->remaining;
            }

            // Buat response manual
            $response = [
                'current_page' => $campaigns->currentPage(),
                'data' => $items,
                'first_page_url' => $campaigns->url(1),
                'from' => $campaigns->firstItem(),
                'last_page' => $campaigns->lastPage(),
                'last_page_url' => $campaigns->url($campaigns->lastPage()),
                'next_page_url' => $campaigns->nextPageUrl(),
                'path' => $campaigns->path(),
                'per_page' => $campaigns->perPage(),
                'prev_page_url' => $campaigns->previousPageUrl(),
                'to' => $campaigns->lastItem(),
                'total' => $campaigns->total()
            ];

            return response()->json([
                'success' => true,
                'data' => $response
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch campaigns: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single campaign
     */
    public function show($id)
    {
        try {
            $campaign = Campaign::find($id);

            if (!$campaign) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campaign not found'
                ], 404);
            }

            // Convert to array and add computed properties
            $campaignData = $campaign->toArray();
            $campaignData['progress'] = $campaign->progress;
            $campaignData['remaining'] = $campaign->remaining;

            return response()->json([
                'success' => true,
                'data' => $campaignData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch campaign: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new campaign
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|string|max:100',
            'goal' => 'required|numeric|min:1',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'image' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
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
                'image' => $request->image
            ]);

            // Convert to array and add computed properties
            $campaignData = $campaign->toArray();
            $campaignData['progress'] = $campaign->progress;
            $campaignData['remaining'] = $campaign->remaining;

            return response()->json([
                'success' => true,
                'message' => 'Campaign created successfully',
                'data' => $campaignData
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create campaign: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update campaign
     */
    public function update(Request $request, $id)
    {
        $campaign = Campaign::find($id);

        if (!$campaign) {
            return response()->json([
                'success' => false,
                'message' => 'Campaign not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'category' => 'sometimes|string|max:100',
            'goal' => 'sometimes|numeric|min:1',
            'status' => 'sometimes|in:Active,Inactive',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'image' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $campaign->fill($request->only([
                'name', 'description', 'category', 'goal', 
                'status', 'start_date', 'end_date', 'image'
            ]));

            if ($request->has('name')) {
                $campaign->slug = Str::slug($request->name) . '-' . uniqid();
            }

            $campaign->save();

            // Convert to array and add computed properties
            $campaignData = $campaign->toArray();
            $campaignData['progress'] = $campaign->progress;
            $campaignData['remaining'] = $campaign->remaining;

            return response()->json([
                'success' => true,
                'message' => 'Campaign updated successfully',
                'data' => $campaignData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update campaign: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete campaign
     */
    public function destroy($id)
    {
        try {
            $campaign = Campaign::find($id);

            if (!$campaign) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campaign not found'
                ], 404);
            }

            $campaign->delete();

            return response()->json([
                'success' => true,
                'message' => 'Campaign deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete campaign: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get campaign statistics
     */
    public function getStats()
    {
        try {
            $totalCampaigns = Campaign::count();
            $activeCampaigns = Campaign::where('status', 'Active')->count();
            $totalRaised = Campaign::sum('raised');
            $totalDonors = Campaign::sum('donors');
            $totalGoal = Campaign::sum('goal');

            // Ambil top performing campaign
            $topPerforming = Campaign::where('status', 'Active')
                ->orderByRaw('(raised / goal) desc')
                ->first();

            // Ambil most donors campaign
            $mostDonors = Campaign::where('status', 'Active')
                ->orderBy('donors', 'desc')
                ->first();

            // By category
            $byCategory = Campaign::selectRaw('category, count(*) as count, sum(raised) as total_raised')
                ->groupBy('category')
                ->get();

            $stats = [
                'total_campaigns' => $totalCampaigns,
                'active_campaigns' => $activeCampaigns,
                'total_raised' => $totalRaised,
                'total_donors' => $totalDonors,
                'total_goal' => $totalGoal,
                'average_progress' => $totalGoal > 0 ? round(($totalRaised / $totalGoal) * 100) : 0,
                'by_category' => $byCategory,
                'top_performing' => $topPerforming ? [
                    'id' => $topPerforming->id,
                    'name' => $topPerforming->name,
                    'raised' => $topPerforming->raised,
                    'goal' => $topPerforming->goal,
                    'donors' => $topPerforming->donors,
                    'progress' => $topPerforming->progress,
                    'remaining' => $topPerforming->remaining
                ] : null,
                'most_donors' => $mostDonors ? [
                    'id' => $mostDonors->id,
                    'name' => $mostDonors->name,
                    'raised' => $mostDonors->raised,
                    'goal' => $mostDonors->goal,
                    'donors' => $mostDonors->donors,
                    'progress' => $mostDonors->progress,
                    'remaining' => $mostDonors->remaining
                ] : null
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch campaign stats: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle campaign status
     */
    public function toggleStatus($id)
    {
        try {
            $campaign = Campaign::find($id);

            if (!$campaign) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campaign not found'
                ], 404);
            }

            $campaign->status = $campaign->status === 'Active' ? 'Inactive' : 'Active';
            $campaign->save();

            // Convert to array and add computed properties
            $campaignData = $campaign->toArray();
            $campaignData['progress'] = $campaign->progress;
            $campaignData['remaining'] = $campaign->remaining;

            return response()->json([
                'success' => true,
                'message' => 'Campaign status updated',
                'data' => $campaignData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get categories list
     */
    public function getCategories()
    {
        try {
            $categories = Campaign::select('category')
                ->distinct()
                ->pluck('category');

            return response()->json([
                'success' => true,
                'data' => $categories
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch categories: ' . $e->getMessage()
            ], 500);
        }
    }
}