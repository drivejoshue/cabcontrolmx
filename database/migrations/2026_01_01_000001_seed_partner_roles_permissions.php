<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('roles') || !DB::getSchemaBuilder()->hasTable('permissions')) return;

        // Roles base
        $roles = [
            ['name' => 'partner', 'guard_name' => 'web'],
            ['name' => 'partner_owner', 'guard_name' => 'web'],
            ['name' => 'partner_staff', 'guard_name' => 'web'],
        ];

        foreach ($roles as $r) {
            DB::table('roles')->updateOrInsert(
                ['name' => $r['name'], 'guard_name' => $r['guard_name']],
                ['updated_at' => now(), 'created_at' => now()]
            );
        }

        // Permisos base (ajústalos a tu estilo)
        $perms = [
            'partners.view',
            'partners.manage',
            'partners.members.manage',
            'partners.drivers.view',
            'partners.drivers.manage',
            'partners.vehicles.view',
            'partners.vehicles.manage',
            'partners.wallet.view',
            'partners.wallet.topup',
            'partners.reports.view',
            'partners.charges.view',
            'partners.charges.manage',
        ];

        foreach ($perms as $p) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $p, 'guard_name' => 'web'],
                ['updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        // No borramos roles/permisos por seguridad.
    }
};
