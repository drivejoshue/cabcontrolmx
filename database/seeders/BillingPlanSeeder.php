<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BillingPlanSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('billing_plans')->updateOrInsert(
            ['code' => 'PV_STARTER'],
            [
                'name' => 'Per Vehicle Starter',
                'billing_model' => 'per_vehicle',
                'currency' => 'MXN',
                'base_monthly_fee' => 0,
                'included_vehicles' => 5,   // si tus “incluidos” son 5
                'price_per_vehicle' => 299, // aquí tu precio
                'active' => 1,
                'effective_from' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
