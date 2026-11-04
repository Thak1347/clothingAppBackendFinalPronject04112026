<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,       // 1 admin
            CustomerUserSeeder::class,    // 3 customers
            CategorySeeder::class,        // 4 categories
            ProductSeeder::class,         // 20 products x 3 variants
            DemoOrderSeeder::class,       // sample orders + payments + receipts + notifications
        ]);
    }
}
