<?php
// app/Http/Controllers/UserController.php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class UserController extends Controller
{
    /**
     * GET /users - List all users with pagination
     */
    public function index(Request $request)
    {
        try {
            // Log untuk debugging
            Log::info('========== USER INDEX ==========');
            Log::info('User: ' . ($request->auth_user ? $request->auth_user->email : 'No user'));
            Log::info('Request params:', $request->all());

            // Cek apakah user punya role admin
            $user = $request->auth_user;
            if (!$user) {
                Log::error('No user found in request');
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Query users
            $query = User::query();

            // Search filter
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%")
                        ->orWhere('position', 'LIKE', "%{$search}%");
                });
            }

            // Role filter
            if ($request->has('role') && !empty($request->role)) {
                $query->whereHas('roles', function ($q) use ($request) {
                    $q->where('name', $request->role);
                });
            }

            // Pagination
            $perPage = $request->get('per_page', 10);
            $users = $query->with('roles')->orderBy('created_at', 'desc')->paginate($perPage);

            Log::info('Total users found: ' . $users->total());

            // Format response
            $formattedUsers = $users->getCollection()->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'position' => $user->position ?? 'No Position',
                    'role' => $user->roles->first()->name ?? 'staff',
                    'role_id' => $user->roles->first()->id ?? null,
                    'avatar' => $user->avatar,
                    'created_at' => $user->created_at ? $user->created_at->format('Y-m-d H:i:s') : null,
                    'updated_at' => $user->updated_at ? $user->updated_at->format('Y-m-d H:i:s') : null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedUsers,
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total()
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching users: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /users - Create new user
     */
    public function store(Request $request)
    {
        try {
            $this->validate($request, [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users',
                'password' => 'required|string|min:6',
                'position' => 'nullable|string|max:255',
                'role' => 'required|string|exists:roles,name'
            ]);

            // Create user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'position' => $request->position,
                'api_token' => Str::random(60)
            ]);

            // Assign role
            $user->assignRole($request->role);

            Log::info('User created from dashboard:', [
                'user_id' => $user->id,
                'role' => $request->role,
                'created_by' => $request->auth_user->id ?? null
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'position' => $user->position,
                    'role' => $request->role
                ]
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creating user: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /users/{id} - Get single user details
     */
    public function show($id)
    {
        try {
            $user = User::with('roles', 'permissions')->find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Get user permissions
            $permissions = $user->getAllPermissions()->pluck('name');

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'position' => $user->position,
                    'role' => $user->roles->first()->name ?? 'staff',
                    'role_id' => $user->roles->first()->id ?? null,
                    'permissions' => $permissions,
                    'avatar' => $user->avatar,
                    'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $user->updated_at->format('Y-m-d H:i:s')
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching user: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user'
            ], 500);
        }
    }

    /**
     * PUT /users/{id} - Update user
     */
    public function update(Request $request, $id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $rules = [
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $id,
                'position' => 'nullable|string|max:255',
                'role' => 'sometimes|string|exists:roles,name'
            ];

            // Only validate password if provided
            if ($request->has('password') && !empty($request->password)) {
                $rules['password'] = 'string|min:6';
            }

            $this->validate($request, $rules);

            // Update user data
            $userData = [
                'name' => $request->name ?? $user->name,
                'email' => $request->email ?? $user->email,
                'position' => $request->position ?? $user->position,
            ];

            if ($request->has('password') && !empty($request->password)) {
                $userData['password'] = Hash::make($request->password);
            }

            $user->update($userData);

            // Update role if provided
            if ($request->has('role')) {
                $user->syncRoles([$request->role]);
            }

            Log::info('User updated from dashboard:', [
                'user_id' => $user->id,
                'updated_by' => $request->auth_user->id ?? null
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'position' => $user->position,
                    'role' => $request->role ?? $user->roles->first()->name ?? 'staff'
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating user: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /users/{id} - Delete user
     */
    public function destroy(Request $request, $id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Prevent deleting yourself
            if ($request->auth_user && $request->auth_user->id == $id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete your own account'
                ], 400);
            }

            // Delete user sessions
            Session::where('user_id', $id)->delete();

            // Delete user
            $user->delete();

            Log::info('User deleted from dashboard:', [
                'user_id' => $id,
                'deleted_by' => $request->auth_user->id ?? null
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting user: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user'
            ], 500);
        }
    }

    /**
     * GET /roles - List all roles (for dropdown)
     */
    public function getRoles()
    {
        try {
            $roles = Role::all()->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions_count' => $role->permissions->count()
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $roles
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching roles: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch roles'
            ], 500);
        }
    }

    /**
     * GET /permissions - List all permissions
     */
    public function getPermissions()
    {
        try {
            $permissions = Permission::all()->map(function ($permission) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'guard_name' => $permission->guard_name,
                    'module' => explode('.', $permission->name)[0] ?? explode(' ', $permission->name)[1] ?? 'other',
                    'action' => explode('.', $permission->name)[1] ?? explode(' ', $permission->name)[0] ?? 'unknown'
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $permissions
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching permissions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch permissions'
            ], 500);
        }
    }

    /**
     * POST /users/{id}/assign-role - Assign role to user
     */
    public function assignRole(Request $request, $id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $this->validate($request, [
                'role' => 'required|string|exists:roles,name'
            ]);

            $user->syncRoles([$request->role]);

            Log::info('Role assigned from dashboard:', [
                'user_id' => $id,
                'role' => $request->role,
                'assigned_by' => $request->auth_user->id ?? null
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Role assigned successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error assigning role: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign role'
            ], 500);
        }
    }

    /**
     * POST /users/{id}/assign-permissions - Assign permissions to user
     */
    public function assignPermissions(Request $request, $id)
    {
        try {
            Log::info('========== ASSIGN PERMISSIONS ==========');
            Log::info('User ID: ' . $id);
            Log::info('Request permissions:', $request->permissions ?? []);

            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $this->validate($request, [
                'permissions' => 'required|array',
                'permissions.*' => 'string'
            ]);

            $permissions = $request->permissions;

            // Konversi format titik ke spasi jika perlu
            $convertedPermissions = [];
            foreach ($permissions as $perm) {
                // Cek apakah permission ada di database
                $dbPermission = Permission::where('name', $perm)->first();

                if ($dbPermission) {
                    $convertedPermissions[] = $dbPermission->name;
                } else {
                    // Coba konversi dari format titik ke spasi
                    $parts = explode('.', $perm);
                    if (count($parts) == 2) {
                        $alternativeName = $parts[1] . ' ' . $parts[0];
                        $dbPermission = Permission::where('name', $alternativeName)->first();
                        if ($dbPermission) {
                            $convertedPermissions[] = $dbPermission->name;
                        }
                    }
                }
            }

            Log::info('Converted permissions:', $convertedPermissions);

            // Sync permissions
            $user->syncPermissions($convertedPermissions);

            return response()->json([
                'success' => true,
                'message' => 'Permissions assigned successfully',
                'data' => [
                    'permissions' => $user->getAllPermissions()->pluck('name')
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error assigning permissions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign permissions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /users/{id}/permissions - Get user permissions
     */
    public function getUserPermissions($id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Dapatkan semua permissions user
            $userPermissions = $user->getAllPermissions()->pluck('name')->toArray();

            Log::info('User permissions:', $userPermissions);

            // Format permissions sesuai module
            $modules = ['campaigns', 'donations', 'donors', 'events', 'reports', 'users', 'settings'];
            $formattedPermissions = [];

            foreach ($modules as $module) {
                $formattedPermissions[$module] = [
                    'create' => in_array($module . '.create', $userPermissions) || in_array('create ' . $module, $userPermissions) || in_array('create_' . $module, $userPermissions),
                    'edit' => in_array($module . '.edit', $userPermissions) || in_array('edit ' . $module, $userPermissions) || in_array('edit_' . $module, $userPermissions),
                    'delete' => in_array($module . '.delete', $userPermissions) || in_array('delete ' . $module, $userPermissions) || in_array('delete_' . $module, $userPermissions),
                    'view' => in_array($module . '.view', $userPermissions) || in_array('view ' . $module, $userPermissions) || in_array('view_' . $module, $userPermissions),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $formattedPermissions
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching user permissions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user permissions'
            ], 500);
        }
    }
}