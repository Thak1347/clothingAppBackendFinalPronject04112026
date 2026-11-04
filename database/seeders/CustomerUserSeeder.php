<?php
namespace Database\Seeders;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Seeder;

class CustomerUserSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()
            ->count(3)
            ->has(
                Address::factory()->default(),
                'addresses'
            )
            ->create();
    }
}
