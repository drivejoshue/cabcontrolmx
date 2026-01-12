<?php

namespace Database\Seeders;

use App\Models\ProviderProfile;
use Illuminate\Database\Seeder;

class ProviderProfileSeeder extends Seeder
{
    public function run(): void
    {
        if (ProviderProfile::query()->exists()) return;

        ProviderProfile::create([
            'active' => 1,
            'display_name' => 'Orbana Dispatch',
            'contact_name' => 'César Josue Méndez Costeño',
            'email_support' => 'soporte@orbana.mx',
            'email_admin' => 'soporte@orbana.mx',
            'country' => 'México',
        ]);
    }
}
