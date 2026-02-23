<?php
// database/seeders/DatabaseSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Panggil RoleSeeder dulu untuk membuat roles dan permissions
        $this->call(RoleSeeder::class);

        // HANYA 1 USER ADMIN UTAMA
        $admin = User::firstOrCreate(
            ['email' => 'rifai13@gmail.com'], // Email admin
            [
                'name' => 'Super Admin',
                'password' => Hash::make('rifai123'), // Password admin
                'api_token' => Str::random(60)
            ]
        )->syncRoles(['admin']);
    }
}