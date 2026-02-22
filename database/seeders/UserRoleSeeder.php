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
    $role = Role::firstOrCreate(['name' => 'manager']);

    // Buat permission-nya
    $permission = Permission::firstOrCreate(['name' => 'edit-stock']);

    // Hubungkan permission ke role
    $role->givePermissionTo($permission);

    $user = User::where('email', 'rifai@gmail.com')->first();
    if ($user) {
      $user->assignRole($role);
    }
  }
}