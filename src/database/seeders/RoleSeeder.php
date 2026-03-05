<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $unassigned = Role::firstOrCreate(['name' => 'unassigned'], ['description' => 'No specific role assigned']);
        $admin = Role::firstOrCreate(['name' => 'admin'], ['description' => 'Full access to all resources']);
        $subAdmin = Role::firstOrCreate(['name' => 'sub-admin'], ['description' => 'Limited admin access']);
        $faculty = Role::firstOrCreate(['name' => 'faculty'], ['description' => 'Access to faculty resources']);
        $student = Role::firstOrCreate(['name' => 'student'], ['description' => 'Access to student resources']);

        $user = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin User', 'password' => Hash::make('password')]
        );

        $user->roles()->syncWithoutDetaching($admin);
    }
}
