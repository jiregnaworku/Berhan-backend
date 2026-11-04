<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::updateOrCreate(
            ['username' => 'ambassador'],
            [
                'name' => 'Ambassador User',
                'email' => 'ambassador@example.com',
                'password' => 'ambassador123',
                'role' => 'ambassador',
            ],
        );

        User::updateOrCreate(
            ['username' => 'service.manager'],
            [
                'name' => 'Service Desk Manager',
                'email' => 'service.manager@example.com',
                'password' => 'service123',
                'role' => 'service-desk-manager',
            ],
        );

        User::updateOrCreate(
            ['username' => 'branch.manager'],
            [
                'name' => 'Branch Manager',
                'email' => 'branch.manager@example.com',
                'password' => 'branch123',
                'role' => 'branch-manager',
            ],
        );
    }
}
