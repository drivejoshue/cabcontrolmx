<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\BillingPlanSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BillingPlanSeeder::class,
             ProviderProfileSeeder::class,
             \Database\Seeders\AppRemoteConfigSeeder::class,
        ]);
    }
}
