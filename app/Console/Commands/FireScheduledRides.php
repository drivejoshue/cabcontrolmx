<?php
// app/Console/Commands/FireScheduledRides.php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Services\ScheduledRidesService;

class FireScheduledRides extends Command
{
    protected $signature = 'rides:fire-scheduled {tenantId=1} {--ahead=0}';
    protected $description = 'Dispara rides programados vencidos (y opcionalmente dentro de X segundos)';

    public function handle(){
        $n = ScheduledRidesService::fireDue(
            (int)$this->argument('tenantId'),
            (int)$this->option('ahead')
        );
        $this->info("Fired $n scheduled rides.");
        return self::SUCCESS;
    }
}
