<?php
namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['name' => 'T-Shirts', 'description' => 'Casual tops'],
            ['name' => 'Pants', 'description' => 'Trousers and joggers'],
            ['name' => 'Dresses', 'description' => 'Everyday and formal'],
            ['name' => 'Accessories', 'description' => 'Bags, hats, belts'],
        ];

        foreach ($rows as $row) {
            Category::create([
                'name' => $row['name'],
                'slug' => Str::slug($row['name']),
                'description' => $row['description'],
                'is_active' => true,
            ]);
        }
    }
}
