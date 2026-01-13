<?php
use Illuminate\Support\Facades\Route;

$u = auth()->user();
$tenantId = $u?->tenant_id;

// Roles
$isSysAdmin        = (bool)($u?->is_sysadmin ?? false);
$isTenantAdmin     = (bool)($tenantId && ($u->is_admin ?? false));
$isDispatcher      = (bool)($tenantId && ($u->is_dispatcher ?? false));
$isDispatcherOnly  = $isDispatcher && !$isTenantAdmin && !$isSysAdmin;

/**
 * Activo para item simple:
 *  - acepta un string o array de patterns
 */
$isActive = function ($patterns): bool {
    $patterns = is_array($patterns) ? $patterns : [$patterns];
    foreach ($patterns as $p) {
        if (request()->routeIs($p)) return true;
    }
    return false;
};

// Branding
$brandName  = 'Orbana Core';
$brandSub   = 'Dispatch & Operación';
$logoCircle = asset('images/logonf.png');

// Home
$homeUrl = $isTenantAdmin
  ? (Route::has('admin.dashboard') ? route('admin.dashboard') : url('/admin'))
  : (Route::has('dispatch') ? route('dispatch') : url('/dispatch'));
?>

<aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark">
  <div class="container-fluid">

    {{-- Brand --}}
    <h1 class="navbar-brand navbar-brand-autodark">
      <a class="d-flex align-items-center gap-2 text-decoration-none" href="{{ $homeUrl }}">
        <img src="{{ $logoCircle }}" alt="Orbana" width="28" height="28" class="orb-brand-circle">
        <span class="d-flex flex-column lh-sm">
          <span class="orb-brand-title">{{ $brandName }}</span>
          <small class="orb-brand-sub">{{ $brandSub }}</small>
        </span>
      </a>
    </h1>

    {{-- Toggler mobile --}}
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu"
            aria-controls="sidebar-menu" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="sidebar-menu">
      <ul class="navbar-nav pt-lg-3">

        {{-- =======================
             DISPATCHER ONLY
        ======================= --}}
        @if($isDispatcherOnly)

          <li class="nav-item">
            <div class="nav-link text-uppercase text-muted fw-semibold small">Dispatch</div>
          </li>

          <li class="nav-item {{ $isActive('dispatch') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('dispatch') }}"
               aria-current="{{ $isActive('dispatch') ? 'page' : 'false' }}">
              <span class="nav-link-icon d-md-none d-lg-inline-block">
                <i class="ti ti-route"></i>
              </span>
              <span class="nav-link-title">Abrir Dispatch</span>
            </a>
          </li>

          {{-- (Opcional) si luego creas un perfil del dispatcher --}}
          {{-- <li class="nav-item {{ $isActive('admin.profile.*') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('admin.profile.edit') }}">
              <span class="nav-link-icon d-md-none d-lg-inline-block">
                <i class="ti ti-user"></i>
              </span>
              <span class="nav-link-title">Mi perfil</span>
            </a>
          </li> --}}

        @else

        {{-- =======================
             CORE (ADMIN)
        ======================= --}}
        <li class="nav-item">
          <div class="nav-link text-uppercase text-muted fw-semibold small">Core</div>
        </li>

        <li class="nav-item {{ $isActive('admin.dashboard') ? 'active' : '' }}">
          <a class="nav-link" href="{{ Route::has('admin.dashboard') ? route('admin.dashboard') : url('/admin') }}"
             aria-current="{{ $isActive('admin.dashboard') ? 'page' : 'false' }}">
            <span class="nav-link-icon d-md-none d-lg-inline-block">
              <i class="ti ti-home"></i>
            </span>
            <span class="nav-link-title">Dashboard</span>
          </a>
        </li>

        {{-- Acceso al Dispatch “real” (public /dispatch) --}}
        <li class="nav-item {{ $isActive('dispatch') ? 'active' : '' }}">
          <a class="nav-link" href="{{ route('dispatch') }}"
             aria-current="{{ $isActive('dispatch') ? 'page' : 'false' }}">
            <span class="nav-link-icon d-md-none d-lg-inline-block">
              <i class="ti ti-route"></i>
            </span>
            <span class="nav-link-title">Dispatch</span>
          </a>
        </li>

        {{-- Si sigues usando /admin/dispatch como vista alternativa, déjala, pero no la llames “Mapa (Dispatch)” para no confundir --}}
        {{-- <li class="nav-item {{ $isActive('admin.dispatch') ? 'active' : '' }}">
          <a class="nav-link" href="{{ route('admin.dispatch') }}">
            <span class="nav-link-icon d-md-none d-lg-inline-block">
              <i class="ti ti-map"></i>
            </span>
            <span class="nav-link-title">Mapa (Admin)</span>
          </a>
        </li> --}}

        {{-- =======================
             OPERACIÓN (solo tenant admin)
        ======================= --}}
        @if($isTenantAdmin)
          <li class="nav-item mt-3">
            <div class="nav-link text-uppercase text-muted fw-semibold small">Operación</div>
          </li>

          <li class="nav-item {{ $isActive('sectores.*') ? 'active' : '' }}">
            <a class="nav-link" href="{{ Route::has('sectores.index') ? route('sectores.index') : url('/admin/sectores') }}">
              <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-grid-dots"></i></span>
              <span class="nav-link-title">Sectores</span>
            </a>
          </li>

          <li class="nav-item {{ $isActive('taxistands.*') ? 'active' : '' }}">
            <a class="nav-link" href="{{ Route::has('taxistands.index') ? route('taxistands.index') : url('/admin/taxistands') }}">
              <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-flag-3"></i></span>
              <span class="nav-link-title">Paraderos</span>
            </a>
          </li>

          <li class="nav-item {{ $isActive('drivers.*') ? 'active' : '' }}">
            <a class="nav-link" href="{{ Route::has('drivers.index') ? route('drivers.index') : url('/admin/drivers') }}">
              <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-user-check"></i></span>
              <span class="nav-link-title">Conductores</span>
            </a>
          </li>

          <li class="nav-item {{ $isActive('vehicles.*') ? 'active' : '' }}">
            <a class="nav-link" href="{{ Route::has('vehicles.index') ? route('vehicles.index') : url('/admin/vehicles') }}">
              <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-car"></i></span>
              <span class="nav-link-title">Vehículos</span>
            </a>
          </li>
        


          {{-- CONFIG --}}
          <li class="nav-item mt-3">
            <div class="nav-link text-uppercase text-muted fw-semibold small">Configuración</div>
          </li>

          <li class="nav-item {{ $isActive('admin.tenant.edit') ? 'active' : '' }}">
            <a class="nav-link" href="{{ Route::has('admin.tenant.edit') ? route('admin.tenant.edit') : url('/mi-central') }}">
              <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-briefcase"></i></span>
              <span class="nav-link-title">Mi central</span>
            </a>
          </li>

          <li class="nav-item {{ $isActive(['admin.billing.*','admin.billing.plan']) ? 'active' : '' }}">
            <a class="nav-link" href="{{ Route::has('admin.billing.plan') ? route('admin.billing.plan') : url('/admin/billing/plan') }}">
              <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-credit-card"></i></span>
              <span class="nav-link-title">Plan y facturación</span>
            </a>
          </li>

        <!--   <li class="nav-item {{ $isActive('admin.dispatch_settings.*') ? 'active' : '' }}">
            <a class="nav-link" href="{{ Route::has('admin.dispatch_settings.edit') ? route('admin.dispatch_settings.edit') : url('/admin/dispatch-settings') }}">
              <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-adjustments-horizontal"></i></span>
              <span class="nav-link-title">Dispatch Settings</span>
            </a>
          </li> -->

          <li class="nav-item {{ $isActive('admin.users.*') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('admin.users.index') }}">
              <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-users"></i></span>
              <span class="nav-link-title">Usuarios</span>
            </a>
          </li>

          <li class="nav-item {{ $isActive('admin.fare_policies.*') ? 'active' : '' }}">
            <a class="nav-link" href="{{ Route::has('admin.fare_policies.index') ? route('admin.fare_policies.index') : url('/admin/fare-policies') }}">
              <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-currency-dollar"></i></span>
              <span class="nav-link-title">Tarifas</span>
            </a>
          </li>

          {{-- COBROS --}}
          <li class="nav-item mt-3">
            <div class="nav-link text-uppercase text-muted fw-semibold small">Cobros</div>
          </li>

          <li class="nav-item {{ $isActive('admin.taxi_fees') ? 'active' : '' }}">
            <a class="nav-link" href="{{ Route::has('admin.taxi_fees') ? route('admin.taxi_fees') : url('/admin/cobros/cuotas-taxi') }}">
              <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-currency-dollar"></i></span>
              <span class="nav-link-title">Cuotas por taxi</span>
            </a>
          </li>

          <li class="nav-item {{ $isActive('admin.taxi_charges') ? 'active' : '' }}">
            <a class="nav-link" href="{{ Route::has('admin.taxi_charges') ? route('admin.taxi_charges') : url('/admin/cobros/taxi') }}">
              <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-receipt"></i></span>
              <span class="nav-link-title">Cobros (semanal)</span>
            </a>
          </li>

          <li class="nav-item {{ $isActive('admin.reports.incomes.taxi_income*') ? 'active' : '' }}">
  <a class="nav-link" href="{{ route('admin.reports.incomes.taxi_income') }}"
     aria-current="{{ $isActive('admin.reports.incomes.taxi_income*') ? 'page' : 'false' }}">
    <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-cash"></i></span>
    <span class="nav-link-title">Ingresos Taxis</span>
  </a>
</li>


         


          {{-- REPORTES --}}
          <li class="nav-item mt-3">
            <div class="nav-link text-uppercase text-muted fw-semibold small">Reportes</div>
          </li>

          <li class="nav-item {{ $isActive('admin.reports.clients*') ? 'active' : '' }}">
            <a class="nav-link" href="{{ Route::has('admin.reports.clients') ? route('admin.reports.clients') : url('/admin/reportes/clientes') }}">
              <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-users"></i></span>
              <span class="nav-link-title">Clientes</span>
            </a>
          </li>

          <li class="nav-item {{ $isActive('admin.reports.rides*') ? 'active' : '' }}">
            <a class="nav-link" href="{{ Route::has('admin.reports.rides') ? route('admin.reports.rides') : url('/admin/reportes/viajes') }}">
              <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-activity"></i></span>
              <span class="nav-link-title">Viajes</span>
            </a>
          </li>

          <li class="nav-item {{ $isActive('admin.reports.drivers') ? 'active' : '' }}">
            <a class="nav-link" href="{{ Route::has('admin.reports.drivers') ? route('admin.reports.drivers') : url('/admin/reportes/conductores') }}">
              <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-award"></i></span>
              <span class="nav-link-title">Conductores</span>
            </a>
          </li>

          <li class="nav-item {{ $isActive('ratings.*') ? 'active' : '' }}">
            <a class="nav-link" href="{{ Route::has('ratings.index') ? route('ratings.index') : url('/ratings/reports') }}">
              <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-star"></i></span>
              <span class="nav-link-title">Calificaciones</span>
            </a>
          </li>

                  <li class="nav-item {{ $isActive('admin.ride_issues.*') ? 'active' : '' }}">
          <a class="nav-link" href="{{ route('admin.ride_issues.index') }}"
             aria-current="{{ $isActive('admin.ride_issues.*') ? 'page' : 'false' }}">
            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-alert-triangle"></i></span>
            <span class="nav-link-title">Issues</span>
          </a>
        </li>

        
          <li class="nav-item {{ $isActive('admin.bi.demand') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('admin.bi.demand') }}">
              <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-flame"></i></span>
              <span class="nav-link-title">Mapa de demanda</span>
            </a>
          </li>

          {{-- REPORTES --}}
          <li class="nav-item mt-3">
            <div class="nav-link text-uppercase text-muted fw-semibold small">ORBANA_CORE</div>
          </li>




        @endif {{-- isTenantAdmin --}}
        @endif {{-- dispatcherOnly else --}}

      </ul>
    </div>
  </div>
</aside>

@push('styles')
<style>
  .orb-brand-circle{
    border-radius: 999px;
    object-fit: cover;
    box-shadow: 0 0 0 2px rgba(255,255,255,.08);
  }
  .orb-brand-title{
    font-weight: 800;
    letter-spacing: .02em;
    line-height: 1.05;
  }
  .orb-brand-sub{
    opacity: .65;
    font-weight: 600;
  }
  .navbar-vertical .nav-item.active > .nav-link{
    background: rgba(255,255,255,.06);
    border-radius: .5rem;
  }
</style>
@endpush
