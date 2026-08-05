<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    /**
     * Seed the application's roles (Spec 01, rule 34).
     */
    public function run(): void
    {
        foreach (['admin', 'vendedor', 'deposito'] as $name) {
            Role::findOrCreate($name);
        }
    }
}
