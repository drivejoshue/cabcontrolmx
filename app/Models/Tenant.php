<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $table = 'tenants';
    
  
    protected $fillable = [
        'name',
        'slug',
        'notification_email',
        'public_phone',
        'public_city',
        'public_active',
        'public_notes',

        'timezone',
        'country_code',
        'utc_offset_minutes',
        'latitud',
        'longitud',
        'coverage_radius_km',

        'allow_marketplace',

        // operación / partners
        'operating_mode',
        'partner_billing_wallet',
        'partner_require_assignment',
        'partner_min_active_vehicles',
        'partner_max_vehicles_per_partner',

        // billing
        'billing_mode',
        'commission_percent',

        'onboarding_done_at',
    ];

    protected $casts = [
        'public_active' => 'boolean',
        'allow_marketplace' => 'boolean',
        'partner_require_assignment' => 'boolean',

        'utc_offset_minutes' => 'integer',
        'partner_min_active_vehicles' => 'integer',
        'partner_max_vehicles_per_partner' => 'integer',

        'latitud' => 'float',
        'longitud' => 'float',
        'coverage_radius_km' => 'float',
        'commission_percent' => 'float',

        'onboarding_done_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // =======================
    // Helpers de operación
    // =======================
    public function supportsPartners(): bool
    {
        return in_array($this->operating_mode, ['partner_network','hybrid','whitelabel'], true);
    }

    public function isTraditional(): bool
    {
        return $this->operating_mode === 'traditional';
    }

    public function partnerWalletMode(): string
    {
        return $this->partner_billing_wallet ?: 'tenant_wallet';
    }
    
    /**
     * Relación con el perfil de facturación
     */
    public function billingProfile()
    {
        return $this->hasOne(TenantBillingProfile::class);
    }

    /**
     * Relación con facturas
     */
    public function invoices()
    {
        return $this->hasMany(TenantInvoice::class);
    }

    /**
     * Relación con vehículos activos
     */
    public function activeVehicles()
    {
        return $this->hasMany(Vehicle::class)
            ->where('active', 1);
    }

    /**
     * Relación con usuarios (admins del tenant)
     */
    public function admins()
    {
        return $this->hasMany(User::class)
            ->where('is_admin', 1)
            ->where('is_sysadmin', 0);
    }

    /**
     * Resumen de facturación
     */
    public function billingSummary(): array
    {
        $profile  = $this->billingProfile;
        $vehicles = $this->activeVehicles()->count();

        if (!$profile) {
            return [
                'has_profile' => false,
                'reason'      => 'Sin perfil de facturación configurado',
                'billing_mode' => $this->billing_mode,
                'commission_percent' => $this->commission_percent
            ];
        }

        return [
            'has_profile'       => true,
            'plan_code'         => $profile->plan_code,
            'billing_model'     => $profile->billing_model, // per_vehicle | commission
            'status'            => $profile->status,        // trial|active|paused|canceled
            'trial_ends_at'     => $profile->trial_ends_at,
            'trial_vehicles'    => $profile->trial_vehicles,
            'base_monthly_fee'  => $profile->base_monthly_fee,
            'included_vehicles' => $profile->included_vehicles,
            'price_per_vehicle' => $profile->price_per_vehicle,
            'max_vehicles'      => $profile->max_vehicles,
            'invoice_day'       => $profile->invoice_day,
            'next_invoice_date' => $profile->next_invoice_date,
            'last_invoice_date' => $profile->last_invoice_date,
            'vehicles_count'    => $vehicles,
            'tenant_billing_mode' => $this->billing_mode,
            'tenant_commission_percent' => $this->commission_percent
        ];
    }

    /**
     * Verificar si el onboarding está completo
     */
    public function isOnboarded(): bool
    {
        // Si no existe el campo onboarding_done_at, usar lógica alternativa
        if (!isset($this->onboarding_done_at)) {
            return $this->latitud !== null && 
                   $this->longitud !== null && 
                   $this->coverage_radius_km !== null;
        }
        
        return $this->onboarding_done_at !== null;
    }

    /**
     * Marcar onboarding como completado
     */
    public function completeOnboarding(): void
    {
        $this->onboarding_done_at = now();
        $this->save();
    }

    /**
     * Obtener modo de facturación legible
     */
    public function getBillingModeLabelAttribute(): string
    {
        return match($this->billing_mode) {
            'per_vehicle' => 'Por vehículo',
            'commission' => 'Por comisión',
            default => $this->billing_mode
        };
    }

    /**
     * Scope para tenants con marketplace habilitado
     */
    public function scopeWithMarketplace($query)
    {
        return $query->where('allow_marketplace', true);
    }

    /**
     * Scope para tenants por modo de facturación
     */
    public function scopeBillingMode($query, $mode)
    {
        return $query->where('billing_mode', $mode);
    }

    /**
     * Verificar si el tenant tiene ubicación configurada
     */
    public function hasLocation(): bool
    {
        return $this->latitud !== null && 
               $this->longitud !== null && 
               $this->coverage_radius_km !== null;
    }

    /**
     * Obtener información de ubicación
     */
    public function getLocationInfo(): ?array
    {
        if (!$this->hasLocation()) {
            return null;
        }

        return [
            'latitud' => (float) $this->latitud,
            'longitud' => (float) $this->longitud,
            'coverage_radius_km' => (float) $this->coverage_radius_km,
            'timezone' => $this->timezone ?? 'America/Mexico_City',
            'utc_offset_minutes' => $this->utc_offset_minutes
        ];
    }
}