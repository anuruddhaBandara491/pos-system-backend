<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = Branch::all();

        $products = [
            [
                'sku' => '8001234567890',
                'name' => 'Cola 330ml',
                'description' => 'Refreshing cola drink',
                'price' => 2.50,
                'cost' => 1.00,
                'stock_qty' => 100,
                'reorder_level' => 20,
                'category' => 'Beverages',
            ],
            [
                'sku' => '8001234567891',
                'name' => 'Water 1L',
                'description' => 'Purified drinking water',
                'price' => 1.50,
                'cost' => 0.50,
                'stock_qty' => 150,
                'reorder_level' => 30,
                'category' => 'Beverages',
            ],
            [
                'sku' => '8001234567892',
                'name' => 'Bread Loaf',
                'description' => 'Fresh white bread',
                'price' => 3.99,
                'cost' => 1.50,
                'stock_qty' => 50,
                'reorder_level' => 10,
                'category' => 'Bakery',
            ],
            [
                'sku' => '8001234567893',
                'name' => 'Milk 500ml',
                'description' => 'Fresh milk',
                'price' => 2.99,
                'cost' => 1.20,
                'stock_qty' => 80,
                'reorder_level' => 15,
                'category' => 'Dairy',
            ],
            [
                'sku' => '8001234567894',
                'name' => 'Chips 100g',
                'description' => 'Crispy potato chips',
                'price' => 1.99,
                'cost' => 0.75,
                'stock_qty' => 120,
                'reorder_level' => 25,
                'category' => 'Snacks',
            ],
            [
                'sku' => '8001234567895',
                'name' => 'Chocolate Bar',
                'description' => 'Dark chocolate 100g',
                'price' => 2.49,
                'cost' => 0.99,
                'stock_qty' => 90,
                'reorder_level' => 20,
                'category' => 'Snacks',
            ],
            [
                'sku' => '8001234567896',
                'name' => 'Orange Juice 1L',
                'description' => 'Fresh orange juice',
                'price' => 4.99,
                'cost' => 2.00,
                'stock_qty' => 60,
                'reorder_level' => 12,
                'category' => 'Beverages',
            ],
            [
                'sku' => '8001234567897',
                'name' => 'Rice 2kg',
                'description' => 'Premium white rice',
                'price' => 8.99,
                'cost' => 4.50,
                'stock_qty' => 40,
                'reorder_level' => 10,
                'category' => 'Groceries',
            ],
        ];

        foreach ($branches as $branch) {
            foreach ($products as $productData) {
                Product::firstOrCreate(
                    ['sku' => $productData['sku'], 'branch_id' => $branch->id],
                    array_merge($productData, ['branch_id' => $branch->id])
                );
            }
        }
    }
}
