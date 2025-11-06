<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Events\DriverEvent;

class TestBroadcasting extends Command
{
    protected $signature = 'test:broadcasting {driver=1} {tenant=1}';
    protected $description = 'Test broadcasting system';

    public function handle()
    {
        $driverId = $this->argument('driver');
        $tenantId = $this->argument('tenant');

        $this->info("Testing broadcast to tenant {$tenantId}, driver {$driverId}");

        // Test 1: Simple event
        event(new DriverEvent(
            tenantId: $tenantId,
            driverId: $driverId,
            type: 'test.event',
            payload: [
                'message' => 'Test from command',
                'timestamp' => now()->toDateTimeString()
            ]
        ));

        $this->info('Event dispatched!');

        // Test 2: Check configuration
        $this->info("\nConfiguration check:");
        $this->info("BROADCAST_DRIVER: " . config('broadcasting.default'));
        $this->info("REVERB_APP_ID: " . config('broadcasting.connections.reverb.app_id'));
        $this->info("REVERB_HOST: " . config('broadcasting.connections.reverb.options.host'));
        $this->info("REVERB_PORT: " . config('broadcasting.connections.reverb.options.port'));

        return Command::SUCCESS;
    }
}