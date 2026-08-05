<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Initial admin account (Spec 01)
    |--------------------------------------------------------------------------
    |
    | Credentials used by the AdminSeeder to create the first administrator.
    | Set them in the environment (see .env.example).
    |
    */

    'initial_name' => env('ADMIN_NAME', 'Admin'),
    'initial_email' => env('ADMIN_EMAIL'),
    'initial_password' => env('ADMIN_PASSWORD'),
];
