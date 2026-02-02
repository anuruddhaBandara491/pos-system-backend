<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create test branches
        $branches = [
            [
                'name' => 'Main Branch',
                'code' => 'BRN001',
                'address' => '123 Main Street',
                'city' => 'New York',
                'state' => 'NY',
                'phone' => '+1-212-555-0001',
                'email' => 'main@pos.local',
                'is_active' => true,
            ],
            [
                'name' => 'Downtown Branch',
                'code' => 'BRN002',
                'address' => '456 Downtown Ave',
                'city' => 'New York',
                'state' => 'NY',
                'phone' => '+1-212-555-0002',
                'email' => 'downtown@pos.local',
                'is_active' => true,
            ],
            [
                'name' => 'Uptown Branch',
                'code' => 'BRN003',
                'address' => '789 Uptown Blvd',
                'city' => 'New York',
                'state' => 'NY',
                'phone' => '+1-212-555-0003',
                'email' => 'uptown@pos.local',
                'is_active' => true,
            ],
        ];

        foreach ($branches as $branchData) {
            Branch::firstOrCreate(['code' => $branchData['code']], $branchData);
        }

        // Assign test users to branches
        $users = User::where('email', '!=', 'test@example.com')->get();
        $branchIds = Branch::pluck('id')->toArray();

        if (! empty($branchIds)) {
            foreach ($users as $index => $user) {
                $branchId = $branchIds[$index % count($branchIds)];
                $user->update(['branch_id' => $branchId]);
            }
        }
    }
}
