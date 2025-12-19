<?php
use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\TenantBillingProfile;
use Carbon\Carbon;

class TenantBillingSeeder extends Seeder
{
    public function run()
    {
        // Ajusta al ID de tu central actual
        $tenant = Tenant::find(1);
        if (!$tenant) {
            return;
        }

        TenantBillingProfile::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan_code'         => 'PV_STARTER',
                'billing_model'     => 'per_vehicle',
                'status'            => 'trial',     // o 'active' si prefieres
                'trial_ends_at'     => Carbon::now()->addMonths(2)->toDateString(), // ampliado para pruebas
                'trial_vehicles'    => 5,
                'base_monthly_fee'  => 300.00,
                'included_vehicles' => 5,
                'price_per_vehicle' => 50.00,
                'max_vehicles'      => 50,
                'invoice_day'       => 1,
                'next_invoice_date' => Carbon::now()->firstOfMonth()->addMonth()->toDateString(),
                'last_invoice_date' => null,
                'commission_percent'=> null,
                'commission_min_fee'=> 0,
                'notes'             => 'Perfil de prueba creado manualmente desde SysAdmin.',
            ]
        );
    }
}
