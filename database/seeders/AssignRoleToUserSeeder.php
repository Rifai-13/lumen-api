<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;

class AssignRoleToUserSeeder extends Seeder
{
    public function run()
    {
        // Cari user rifai@gmail.com
        $user = User::where('email', 'rifai@gmail.com')->first();
        
        if ($user) {
            // Assign role manager
            $user->assignRole('manager');
            $this->command->info('Role manager assigned to rifai@gmail.com');
        } else {
            $this->command->error('User not found!');
        }
        
        // Assign role staff ke user lain
        $staffUser = User::where('email', 'rifai13@gmail.com')->first();
        if ($staffUser) {
            $staffUser->assignRole('staff');
            $this->command->info('Role staff assigned to rifai13@gmail.com');
        }
    }
}