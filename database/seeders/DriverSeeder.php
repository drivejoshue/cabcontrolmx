<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Driver;

class DriverSeeder extends Seeder
{
    public function run(): void
    {
        Driver::updateOrCreate(
            ['email' => 'driver1@demo.local'],
            ['tenant_id'=>1, 'name'=>'Conductor 1', 'phone'=>'555-0001', 'status'=>'idle',
             'last_lat'=>19.1738, 'last_lng'=>-96.1342, 'last_seen_at'=>now()]
        );

        Driver::updateOrCreate(
            ['email' => 'driver2@demo.local'],
            ['tenant_id'=>1, 'name'=>'Conductor 2', 'phone'=>'555-0002', 'status'=>'offline']
        );
    }
}
