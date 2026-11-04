<?php
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', app()->environment('production') ? null : 'admin@example.com');
        $password = env('ADMIN_PASSWORD');

        if (blank($email) || blank($password)) {
            if (app()->environment('production')) {
                throw new \RuntimeException('Set ADMIN_EMAIL and ADMIN_PASSWORD before seeding the production admin account.');
            }

            // Local/demo only. Production seeding must always use explicit credentials.
            $email = filled($email) ? $email : 'admin@example.com';
            $password = filled($password) ? $password : 'password';
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Admin',
                'password' => Hash::make($password),
                'role' => 'admin',
            ],
        );
    }
}
