<?php

// ============================
// 4) SEEDER
// database/seeders/AppRemoteConfigSeeder.php
// ============================

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AppRemoteConfigSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('app_remote_config')->updateOrInsert(
            ['app' => 'passenger'],
            [
                // Passenger beta actual: versionCode=14
                'min_version_code'    => 14,
                'latest_version_code' => 14,
                'force_update'        => false,
                'message'             => 'Hay una actualización disponible.',
                'play_url'            => 'https://play.google.com/store/apps/details?id=com.orbana.passenger',
                'updated_at'          => now(),
                'created_at'          => now(),
            ]
        );

        DB::table('app_remote_config')->updateOrInsert(
            ['app' => 'driver'],
            [
                // Driver actual: versionCode=8
                'min_version_code'    => 8,
                'latest_version_code' => 8,
                'force_update'        => false,
                'message'             => 'Hay una actualización disponible.',
                'play_url'            => 'https://play.google.com/store/apps/details?id=com.orbana.driver',
                'updated_at'          => now(),
                'created_at'          => now(),
            ]
        );
    }
}
