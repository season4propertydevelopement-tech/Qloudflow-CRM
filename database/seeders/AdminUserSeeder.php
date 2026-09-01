<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'season4property@gmail.com'],
            [
                'name' => 'season4propertyadmin',
                'password' => Hash::make('bestproperty4u'),
                'email_verified_at' => now(),
            ]
        );
    }
}
