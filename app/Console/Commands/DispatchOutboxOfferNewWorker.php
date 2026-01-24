<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DispatchOutboxOfferNewPump;

class DispatchOutboxOfferNewWorker extends Command
{
    protected $signature = 'dispatch:outbox-offernew {--tenant=} {--sleep=200} {--limit=200}';
    protected $description = 'Procesa dispatch_outbox SOLO offer.new y emite OfferBroadcaster::emitNew.';

    public function handle(): int
    {
        $tenant = $this->option('tenant') ? (int)$this->option('tenant') : null;
        $sleepMs = max(50, (int)$this->option('sleep'));
        $limit = max(1, (int)$this->option('limit'));

        while (true) {
            $n = DispatchOutboxOfferNewPump::pump($tenant, $limit);

            if ($n === 0) usleep($sleepMs * 1000);
            else usleep(50 * 1000);
        }

        // return 0;
    }
}
