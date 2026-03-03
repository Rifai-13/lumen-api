<?php
// database/seeders/PermissionSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Daftar module yang ada
        $modules = [
            'campaigns',
            'donations',
            'reports',
            'users',
        ];

        // Daftar actions
        $actions = ['view', 'create', 'edit', 'delete'];

        // Buat semua kombinasi permission dengan 3 FORMAT
        foreach ($modules as $module) {
            foreach ($actions as $action) {
                // Format 1: dot (campaigns.create) - UNTUK FRONTEND
                Permission::firstOrCreate([
                    'name' => $module . '.' . $action,
                    'guard_name' => 'api'
                ]);
                
                // Format 2: space (create campaigns) - UNTUK BACKEND
                Permission::firstOrCreate([
                    'name' => $action . ' ' . $module,
                    'guard_name' => 'api'
                ]);
                
                // Format 3: underscore (create_campaigns) - FORMAT ALTERNATIF
                Permission::firstOrCreate([
                    'name' => $action . '_' . $module,
                    'guard_name' => 'api'
                ]);
            }
        }

        // Tambahkan permission khusus
        $extraPermissions = [
            'export_data',
            'import_data',
            'view_stats',
            // 'manage_settings',
            'manage_users'
        ];

        foreach ($extraPermissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'api'
            ]);
        }

        // Assign semua permissions ke role admin
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $adminRole->syncPermissions(Permission::all());
        }

        $this->command->info('✅ All permissions created successfully!');
    }
}