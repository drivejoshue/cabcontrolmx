<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Watchdog para conductores.
 *
 * - Marca como "offline" a los drivers que dejaron de mandar ping
 *   después de cierto tiempo.
 *
 * IMPORTANTE:
 * - NO autocierra turnos. El turno solo lo abre/cierra el driver.
 */
#[AsCommand(
    name: 'drivers:watchdog',
    description: 'Marca drivers offline por inactividad (sin cerrar turnos)'
)]
class DriverWatchdog extends Command
{
    public function handle(): int
    {
        // Segundos de inactividad para pasar a offline
        $offlineAfterSeconds = (int)($this->option('offline-after-seconds') ?? 1800);

        $now           = now();
        $cutOffOffline = now()->subSeconds($offlineAfterSeconds);

        // Marcar OFFLINE por inactividad (sin tocar turnos)
        $aff1 = DB::table('drivers')
            ->where(function ($q) {
                $q->whereNull('last_seen_at')
                  ->orWhere('status', '!=', 'offline');
            })
            ->where(function ($q) use ($cutOffOffline) {
                $q->whereNull('last_seen_at')
                  ->orWhere('last_seen_at', '<', $cutOffOffline);
            })
            ->update([
                'status'     => 'offline',
                'updated_at' => $now,
            ]);

        $this->info("Watchdog -> offline:$aff1 (sin autocierre de turnos)");

        return self::SUCCESS;
    }

    protected function configure(): void
    {
        $this->addOption(
            'offline-after-seconds',
            null,
            null,
            'Segundos de inactividad para marcar driver como offline (default 1800)'
        );
    }
}
