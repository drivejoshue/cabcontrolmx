<?php

// app/Listeners/ActivateTenantOnVerified.php
namespace App\Listeners;

use Illuminate\Auth\Events\Verified;
use App\Models\Tenant;

class ActivateTenantOnVerified
{
    public function handle(Verified $event): void
    {
        $user = $event->user;

        if (!$user || !$user->tenant_id) return;

        Tenant::where('id', $user->tenant_id)
            ->where('public_active', 0)
            ->update(['public_active' => 1]);
    }
}
