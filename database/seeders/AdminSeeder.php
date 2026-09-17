<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminSeeder extends Seeder
{
    /**
     * Seed the initial admin account from environment credentials (Spec 01, rule 35).
     */
    public function run(): void
    {
        // HIG-27: sin email el `updateOrCreate(['email' => null])` moría con una
        // violación de NOT NULL que no decía qué faltaba —y dejaba el despliegue
        // sin ningún usuario con el cual entrar al panel—.
        $email = config('admin.initial_email');

        if (! is_string($email) || trim($email) === '') {
            throw new RuntimeException('Falta configurar la variable ADMIN_EMAIL para sembrar el admin inicial.');
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => config('admin.initial_name'),
                'password' => Hash::make((string) config('admin.initial_password')),
                'is_active' => true,
            ],
        )->assignRole(UserRole::Admin->value);
    }
}
