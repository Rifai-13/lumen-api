<?php
// database/seeders/AssignRoleToUserSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Log;

class AssignRoleToUserSeeder extends Seeder
{
    public function run(): void
    {
        // Cari user berdasarkan email yang sudah ada di database
        $user = User::where('email', 'rifai13@gmail.com')->first();
        
        if (!$user) {
            $this->command->error('User with email rifai13@gmail.com not found!');
            return;
        }

        // Pastikan role admin sudah ada
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        
        // Assign role admin ke user
        if (!$user->hasRole('admin')) {
            $user->assignRole('admin');
            $this->command->info('Role admin berhasil diberikan ke user: ' . $user->email);
        } else {
            $this->command->info('User sudah memiliki role admin');
        }

        // Debug: tampilkan role dan permissions user
        $this->command->info('User: ' . $user->email);
        $this->command->info('Roles: ' . implode(', ', $user->getRoleNames()->toArray()));
        $this->command->info('Permissions: ' . implode(', ', $user->getAllPermissions()->pluck('name')->toArray()));
        
        // Log untuk debugging
        Log::info('Role assigned via seeder', [
            'user_id' => $user->id,
            'email' => $user->email,
            'roles' => $user->getRoleNames()->toArray()
        ]);
    }
}