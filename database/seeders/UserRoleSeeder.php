<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class UserRoleSeeder extends Seeder
{
    public function run()
    {
        // Pastikan role manager sudah ada
        $role = Role::firstOrCreate(['name' => 'manager']);

        // Buat permission jika belum ada
        $permissions = [
            'edit products',
            'create products',
            'delete products',
            'view reports'
        ];

        foreach ($permissions as $permName) {
            $permission = Permission::firstOrCreate(['name' => $permName]);
            // Hubungkan permission ke role
            if (!$role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }

        // Cari user
        $user = User::where('email', 'rifai@gmail.com')->first();
        if ($user) {
            // Hapus role lama jika ada
            $user->syncRoles([]); // Hapus semua role
            $user->assignRole('manager'); // Assign role manager
            
            // Debug: cek role user
            echo "User: " . $user->email . "\n";
            echo "Roles: " . implode(', ', $user->getRoleNames()->toArray()) . "\n";
            echo "Permissions: " . implode(', ', $user->getAllPermissions()->pluck('name')->toArray()) . "\n";
        } else {
            echo "User with email rifai@gmail.com not found!\n";
        }
    }
}