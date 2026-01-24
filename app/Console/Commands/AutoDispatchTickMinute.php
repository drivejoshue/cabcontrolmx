<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\AutoDispatchService;
use App\Services\DispatchSettingsService;

class AutoDispatchTickMinute extends Command
{
    protected $signature = 'orbanamx:autodispatch-tick {--tenant= : Forzar tenant_id} {--limit=50 : Máx rides por corrida}';
    protected $description = 'Relanza waves cada minuto para rides pending/requested que estén sin driver y sin offers vivas.';

   public function handle(): int
{
    $onlyTenant = $this->option('tenant') ? (int)$this->option('tenant') : null;

    $tenantsQ = DB::table('tenants')->select('id');
    if ($onlyTenant) $tenantsQ->where('id', $onlyTenant);

    foreach ($tenantsQ->get() as $t) {
        $tenantId = (int)$t->id;

        // settings enabled
        $cfg = DispatchSettingsService::forTenant($tenantId);
        if (!($cfg->enabled ?? true)) continue;

        DB::statement('CALL sp_dispatch_track_tick_v1(?)', [$tenantId]);
    }

    $this->info('OK tick');
    return 0;
}

}
