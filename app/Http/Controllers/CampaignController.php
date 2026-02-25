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
                $query->where(function ($q) use ($search) {
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
     * GET /campaigns/{id} - Get single campaign
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

            return response()->json([
                'success' => true,
                'data' => $campaign
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching campaign: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch campaign'
            ], 500);
        }
    }

    /**
     * Helper function untuk cek permission dengan berbagai format
     */
    private function checkPermission($user, $action, $module)
    {
        // Admin selalu punya akses
        if ($user->role === 'admin') {
            Log::info("✅ Admin has access to {$action} {$module}");
            return true;
        }

        // HAPUS BLOK INI - JANGAN BERI AKSES OTOMATIS KE MANAGER
        // if ($user->role === 'manager' && $module === 'campaigns') {
        //     Log::info("✅ Manager has access to {$action} {$module}");
        //     return true;
        // }

        // Format permission yang akan dicek
        $formats = [
            "{$action} {$module}",  // "edit campaigns"
            "{$module}.{$action}",  // "campaigns.edit"
            "{$action}_{$module}",  // "edit_campaigns"
            $action                  // "edit"
        ];

        Log::info("🔍 Checking permissions for {$action} {$module}", [
            'formats' => $formats,
            'user_permissions' => $user->getAllPermissions()->pluck('name')
        ]);

        foreach ($formats as $format) {
            try {
                if ($user->hasPermissionTo($format)) {
                    Log::info("✅ User has permission: {$format}");
                    return true;
                }
            } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
                // Permission tidak ada di database, lanjut ke format berikutnya
                Log::info("Permission {$format} does not exist in database, trying next format");
                continue;
            } catch (\Exception $e) {
                // Error lain, log dan lanjutkan
                Log::warning("Error checking permission {$format}: " . $e->getMessage());
                continue;
            }
        }

        Log::warning("❌ User lacks permission for {$action} {$module}");
        return false;
    }

    /**
     * POST /campaigns - Create new campaign
     */
    public function store(Request $request)
    {
        Log::info('========== CAMPAIGN STORE DEBUG ==========');
        Log::info('All request:', $request->all());
        Log::info('Has file: ' . ($request->hasFile('image') ? 'YES' : 'NO'));

        try {
            // Dapatkan user dari middleware
            $user = $request->auth_user;
            
            if (!$user) {
                Log::error('No user found in request');
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized - User not authenticated'
                ], 401);
            }

            Log::info('User role: ' . $user->role);
            Log::info('User ID: ' . $user->id);

            // CEK PERMISSION CREATE CAMPAIGN
            if (!$this->checkPermission($user, 'create', 'campaigns')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized - insufficient permission to create campaigns'
                ], 403);
            }

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

                // Simpan di public/images/campaigns
                $destinationPath = base_path('public/images/campaigns');

                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0777, true);
                    Log::info('Created folder: ' . $destinationPath);
                }

                // Pindahkan file
                $image->move($destinationPath, $filename);

                // Buat URL
                $imagePath = url('images/campaigns/' . $filename);
                Log::info('Image saved to: ' . $destinationPath . '/' . $filename);
                Log::info('Image URL: ' . $imagePath);
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

            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /campaigns/{id} - Update campaign
     */
    public function update(Request $request, $id)
    {
        Log::info('========== UPDATE CAMPAIGN ==========');
        Log::info('Campaign ID: ' . $id);
        Log::info('All request:', $request->all());
        Log::info('Has file: ' . ($request->hasFile('image') ? 'YES' : 'NO'));

        try {
            // Dapatkan user dari middleware
            $user = $request->auth_user;
            
            if (!$user) {
                Log::error('No user found in request');
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized - User not authenticated'
                ], 401);
            }

            Log::info('User role: ' . $user->role);
            Log::info('User ID: ' . $user->id);

            // CEK PERMISSION EDIT CAMPAIGN
            if (!$this->checkPermission($user, 'edit', 'campaigns')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized - insufficient permission to edit campaigns'
                ], 403);
            }

            $campaign = Campaign::find($id);

            if (!$campaign) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campaign not found'
                ], 404);
            }

            // Validasi
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'goal' => 'required|numeric|min:1',
                'category' => 'required|string',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
                'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update data
            $campaign->name = $request->name;
            $campaign->description = $request->description ?? $campaign->description;
            $campaign->category = $request->category;
            $campaign->goal = $request->goal;
            $campaign->start_date = $request->start_date;
            $campaign->end_date = $request->end_date;

            // Handle image upload
            if ($request->hasFile('image')) {
                // Delete old image if exists
                if ($campaign->image) {
                    $oldFilename = basename($campaign->image);
                    $oldPath = base_path('public/images/campaigns/' . $oldFilename);
                    if (file_exists($oldPath)) {
                        unlink($oldPath);
                        Log::info('Deleted old image: ' . $oldPath);
                    }
                }

                $image = $request->file('image');
                $filename = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();

                // Simpan di public/images/campaigns
                $destinationPath = base_path('public/images/campaigns');

                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0777, true);
                }

                // Pindahkan file
                $image->move($destinationPath, $filename);

                // Buat URL
                $campaign->image = url('images/campaigns/' . $filename);

                Log::info('New image uploaded: ' . $campaign->image);
            }

            // Handle image removal
            if ($request->has('remove_image') && $request->remove_image == '1') {
                if ($campaign->image) {
                    $oldFilename = basename($campaign->image);
                    $oldPath = base_path('public/images/campaigns/' . $oldFilename);
                    if (file_exists($oldPath)) {
                        unlink($oldPath);
                        Log::info('Removed image: ' . $oldPath);
                    }
                    $campaign->image = null;
                }
            }

            $campaign->save();

            return response()->json([
                'success' => true,
                'message' => 'Campaign updated successfully',
                'data' => $campaign
            ]);
        } catch (\Exception $e) {
            Log::error('Update error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Update failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /campaigns/{id} - Delete campaign
     */
    public function destroy($id)
    {
        try {
            // Dapatkan user dari middleware
            $user = request()->auth_user;
            
            if (!$user) {
                Log::error('No user found in request');
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized - User not authenticated'
                ], 401);
            }

            Log::info('User role: ' . $user->role);
            Log::info('User ID: ' . $user->id);

            // CEK PERMISSION DELETE CAMPAIGN
            if (!$this->checkPermission($user, 'delete', 'campaigns')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized - insufficient permission to delete campaigns'
                ], 403);
            }

            $campaign = Campaign::find($id);

            if (!$campaign) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campaign not found'
                ], 404);
            }

            // Delete image if exists
            if ($campaign->image) {
                $filename = basename($campaign->image);
                $imagePath = base_path('public/images/campaigns/' . $filename);
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                    Log::info('Deleted image: ' . $imagePath);
                }
            }

            $campaign->delete();

            return response()->json([
                'success' => true,
                'message' => 'Campaign deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting campaign: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete campaign'
            ], 500);
        }
    }

    /**
     * GET /public/campaigns - List all campaigns for public (no auth required)
     */
    public function publicIndex(Request $request)
    {
        try {
            Log::info('Fetching public campaigns with params:', $request->all());

            $query = Campaign::query();

            // Hanya tampilkan campaign yang Active
            $query->where('status', 'Active');

            // Search filter
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%")
                        ->orWhere('category', 'LIKE', "%{$search}%");
                });
            }

            // Filter by category
            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            // Pagination
            $perPage = $request->get('per_page', 9);
            $campaigns = $query->orderBy('created_at', 'desc')->paginate($perPage);

            Log::info('Found ' . $campaigns->total() . ' public campaigns');

            return response()->json([
                'success' => true,
                'data' => $campaigns->items(),
                'current_page' => $campaigns->currentPage(),
                'last_page' => $campaigns->lastPage(),
                'per_page' => $campaigns->perPage(),
                'total' => $campaigns->total()
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching public campaigns: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch campaigns'
            ], 500);
        }
    }
}