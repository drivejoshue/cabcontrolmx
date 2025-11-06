<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Events\DriverEvent;
use App\Models\User;
use App\Models\Driver;

class TestDriverEvent extends Command
{
    protected $signature = 'test:driver-event';
    protected $description = 'Test DriverEvent with proper authentication';

    public function handle()
    {
        $this->info('🧪 Testing DriverEvent with authentication...');

        // 1. Crear o obtener usuario y driver
        $user = User::firstOrCreate(
            ['email' => 'driver@test.com'],
            [
                'name' => 'Test Driver', 
                'password' => bcrypt('password')
            ]
        );

        $driver = Driver::firstOrCreate(
            [
                'user_id' => $user->id,
                'tenant_id' => 1
            ],
            [
                'name' => 'Test Driver',
                'status' => 'busy'
            ]
        );

        $this->info("✅ User ID: {$user->id}, Driver ID: {$driver->id}");

        // 2. Autenticar
        auth()->login($user);
        $this->info('✅ Authenticated as: ' . auth()->user()->name);

        // 3. Verificar que podemos acceder al canal
        $this->info("✅ Testing access to channel: tenant.1.driver.{$driver->id}");

        // 4. Enviar evento
        $this->info('🚀 Sending DriverEvent...');
        
        event(new DriverEvent(
            tenantId: 1,
            driverId: $driver->id,
            type: 'offers.new',
            payload: [
                'ride_id' => 123,
                'offer_id' => 456,
                'message' => 'Test from authenticated command',
                'timestamp' => now()->toDateTimeString(),
                'test' => true
            ]
        ));

        $this->info('✅ Event dispatched! Check Reverb terminal and Laravel logs.');

        // 5. Verificar en logs
        $this->info('📋 Check storage/logs/laravel.log for detailed logs');

        return Command::SUCCESS;
    }
}