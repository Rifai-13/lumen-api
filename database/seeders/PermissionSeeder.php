<?php
// database/seeders/PermissionSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            'campaigns',
            'donations',
            'donors',
            'events',
            'reports',
            'users',
            'settings'
        ];

        $actions = ['view', 'create', 'edit', 'delete'];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                // Format dengan titik (untuk frontend)
                Permission::firstOrCreate([
                    'name' => $module . '.' . $action,
                    'guard_name' => 'api'
                ]);
                
                // Format dengan spasi (untuk backend)
                Permission::firstOrCreate([
                    'name' => $action . ' ' . $module,
                    'guard_name' => 'api'
                ]);
            }
        }

        $this->command->info('Permissions created successfully!');
    }
}