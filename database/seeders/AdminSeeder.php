<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Admin; // Assuming you have an Admin model
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Check if an admin already exists in the admins table
        if (Admin::count() == 0) {
            // Create an admin user
            Admin::create([
                'name' => 'Super Admin', // You can change this to any name
                'email' => 'admin@ecc.com', // Set the admin email
                'password' => Hash::make('adminpassword123'), // Set the admin password (make sure to hash it)
                'email_verified' => true, // Assuming email is verified
                // Add any other necessary fields for your admin table here (e.g., country_code, phone, etc.)
            ]);
        }
    }
}
