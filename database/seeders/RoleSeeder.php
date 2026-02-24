<?php
// database/seeders/RoleSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Buat permissions
        $permissions = [
            'view products',
            'create products',
            'edit products',
            'delete products',
            'view donations',
            'create donations',
            'edit donations',
            'delete donations',
            'view users',
            'create users',
            'edit users',
            'delete users',
            'manage user roles',
            'assign permissions',
            'view reports',
            'export reports',
            'view statistics',
            'manage settings',
            'view logs',
            'manage roles',
            'manage permissions'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'api'
            ]);
        }

        // Role Admin - semua permissions
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api'])
            ->syncPermissions(Permission::all());

        // Role Manager
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'api'])
            ->syncPermissions([
                'view products',
                'create products',
                'edit products',
                'delete products',
                'view donations',
                'create donations',
                'edit donations',
                'view reports',
                'export reports',
                'view statistics'
            ]);

        // Role Staff
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'api'])
            ->syncPermissions([
                'view products',
                'view donations',
                'create donations'
            ]);
    }
}