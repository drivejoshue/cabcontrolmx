<?php

namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Services\ScheduledRidesService;

class PurgeOldDriverMessages extends Command
{
    protected $signature = 'chat:purge-old {--days=60}';
    protected $description = 'Mueve mensajes viejos de driver a la tabla de archivo y limpia la tabla principal';

    public function handle()
    {
        $days = (int) $this->option('days');

        $cutoff = now()->subDays($days);

        $this->info("Archivando mensajes anteriores a {$cutoff}...");

        DB::beginTransaction();
        try {
            // 1) Seleccionamos en bloques para no tronar RAM
            $query = DriverMessage::where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit(5000);

            $totalArchived = 0;

            do {
                $batch = $query->get();
                if ($batch->isEmpty()) {
                    break;
                }

                // Inserta en tabla de archivo
                $archiveRows = $batch->map(function ($m) {
                    return [
                        'id'                 => $m->id,
                        'tenant_id'          => $m->tenant_id,
                        'ride_id'            => $m->ride_id,
                        'driver_id'          => $m->driver_id,
                        'passenger_id'       => $m->passenger_id,
                        'sender_type'        => $m->sender_type,
                        'sender_user_id'     => $m->sender_user_id,
                        'kind'               => $m->kind,
                        'template_key'       => $m->template_key,
                        'message'            => $m->message,
                        'meta'               => $m->meta,
                        'seen_by_driver_at'  => $m->seen_by_driver_at,
                        'seen_by_dispatch_at'=> $m->seen_by_dispatch_at,
                        'created_at'         => $m->created_at,
                        'updated_at'         => $m->updated_at,
                        'archived_at'        => now(),
                    ];
                })->all();

                DB::table('driver_messages_archive')->insert($archiveRows);

                // Borramos del main
                DriverMessage::whereIn('id', $batch->pluck('id'))->delete();

                $totalArchived += count($archiveRows);
            } while (true);

            DB::commit();
            $this->info("Archivados {$totalArchived} mensajes.");
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Error archivando mensajes: ' . $e->getMessage());
            report($e);
        }

        return Command::SUCCESS;
    }
}
