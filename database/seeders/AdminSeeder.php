<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Seed the initial admin account from environment credentials (Spec 01, rule 35).
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => config('admin.initial_email')],
            [
                'name' => config('admin.initial_name'),
                'password' => Hash::make(config('admin.initial_password')),
                'is_active' => true,
            ],
        )->assignRole(UserRole::Admin->value);
    }
}
