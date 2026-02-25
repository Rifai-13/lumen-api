<?php
// database/seeders/RoleSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Buat permissions
        $permissions = [
            // Campaign permissions
            'view campaigns',
            'create campaigns',
            'edit campaigns',
            'delete campaigns',
            'campaigns.view',
            'campaigns.create',
            'campaigns.edit',
            'campaigns.delete',
            
            // Donation permissions
            'view donations',
            'create donations',
            'edit donations',
            'delete donations',
            'donations.view',
            'donations.create',
            'donations.edit',
            'donations.delete',
            
            // User permissions
            'view users',
            'create users',
            'edit users',
            'delete users',
            'manage users',
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            
            // Report permissions
            'view reports',
            'export reports',
            'reports.view',
            'reports.export',
            
            // Setting permissions
            'view settings',
            'edit settings',
            'settings.view',
            'settings.edit',
            'manage_settings',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'api'
            ]);
        }

        // Buat roles
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'api']);
        $staffRole = Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'api']);

        // Assign permissions ke roles
        $adminRole->syncPermissions(Permission::all());

        $managerRole->syncPermissions([
            'view campaigns',
            'create campaigns',
            'edit campaigns',
            'delete campaigns',
            'campaigns.view',
            'campaigns.create',
            'campaigns.edit',
            'campaigns.delete',
            'view donations',
            'view reports',
            'export reports',
        ]);

        $staffRole->syncPermissions([
            'view campaigns',
            'campaigns.view',
            'view donations',
        ]);

        // Assign role ke user SUPER ADMIN - PASTIKAN EMAILNYA BENAR
        $superAdmin = User::where('email', 'rifai13@gmail.com')->first();
        if ($superAdmin) {
            // Hanya assign role admin jika belum punya
            if (!$superAdmin->hasRole('admin')) {
                $superAdmin->assignRole('admin');
                echo "✅ Super Admin assigned admin role\n";
            } else {
                echo "✅ Super Admin already has admin role\n";
            }
        } else {
            echo "❌ Super Admin not found!\n";
        }
    }
}