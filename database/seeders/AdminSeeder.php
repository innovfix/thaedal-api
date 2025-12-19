<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Super Admin
        Admin::create([
            'name' => 'Super Admin',
            'email' => 'admin@thaedal.com',
            'password' => Hash::make('admin123'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        // Create Regular Admin
        Admin::create([
            'name' => 'Admin User',
            'email' => 'admin2@thaedal.com',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Create Moderator
        Admin::create([
            'name' => 'Moderator',
            'email' => 'moderator@thaedal.com',
            'password' => Hash::make('moderator123'),
            'role' => 'moderator',
            'is_active' => true,
        ]);
    }
}


