<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\OfferBroadcaster;

/**
 * Command para expirar ofertas de viaje.
 *
 * - Busca todas las ride_offers en estado "offered" cuya fecha de expiración
 *   (expires_at) ya pasó.
 * - Las marca como "expired" y registra responded_at/updated_at.
 * - Emite un evento vía OfferBroadcaster para que el frontend / apps se
 *   enteren del cambio de estado en tiempo real.
 */
class ExpireOffers extends Command
{
    /**
     * Nombre del comando para Artisan.
     *
     * Se ejecuta como:
     *
     *   php artisan offers:expire
     */
    protected $signature = 'offers:expire';

    /**
     * Descripción que aparece en:
     *
     *   php artisan list
     */
    protected $description = 'Marca como expiradas las ofertas vencidas y emite evento';

    /**
     * Método principal que corre cuando se ejecuta el command.
     */
    public function handle()
    {
        // ============================
        // 1) Tiempo actual (corte)
        // ============================
        //
        // Usaremos $now tanto para comparar con expires_at como
        // para los campos responded_at / updated_at al expirar ofertas.
        $now = now();

        // =========================================================
        // 2) Seleccionar ofertas que ya vencieron y siguen en "offered"
        // =========================================================
        //
        // Lógica:
        // - Solo consideramos filas de ride_offers con:
        //      status = 'offered'
        //      expires_at NO es null
        //      expires_at < $now (ya están vencidas)
        // - Limitamos el batch a 500 registros para no hacer un update masivo
        //   demasiado grande en una sola corrida.
        //
        // - Recuperamos solo las columnas necesarias para:
        //      id         → para hacer el update
        //      tenant_id  → para el broadcaster
        //      driver_id  → para el broadcaster
        //      ride_id    → para el broadcaster
      $rows = DB::table('ride_offers')
    ->where('status', 'offered')
    ->whereNotNull('expires_at')
    ->where('expires_at', '<', $now)
    ->limit(500)
    ->get(['id', 'tenant_id', 'driver_id', 'ride_id']);

foreach ($rows as $r) {
    $updated = DB::table('ride_offers')
        ->where('id', $r->id)
        ->where('status', 'offered') // anti-race
        ->update([
            'status'       => 'expired',
            'response'     => DB::raw("COALESCE(response,'expired')"),
            'responded_at' => DB::raw("COALESCE(responded_at,'$now')"),
            'updated_at'   => $now,
        ]);

    if ($updated) {
        OfferBroadcaster::emitStatus(
            (int) $r->tenant_id,
            (int) $r->driver_id,
            (int) $r->ride_id,
            (int) $r->id,
            'expired'
        );
    }
}


        // ============================
        // 4) Log de resumen en consola
        // ============================
        //
        // Muestra cuántas ofertas fueron expiradas en este batch.
        $this->info('Expiradas: ' . $rows->count());

        // Códigos de retorno estándar de comandos.
        return 0;
    }
}
