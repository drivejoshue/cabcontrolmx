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

    
    <h1 class="navbar-brand navbar-brand-autodark">
      <a class="d-flex align-items-center gap-2 text-decoration-none" href="<?php echo e($homeUrl); ?>">
        <img src="<?php echo e($logoCircle); ?>" alt="Orbana" width="28" height="28" class="orb-brand-circle">
        <span class="d-flex flex-column lh-sm">
          <span class="orb-brand-title"><?php echo e($brandName); ?></span>
          <small class="orb-brand-sub"><?php echo e($brandSub); ?></small>
        </span>
      </a>
    </h1>

    
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu"
            aria-controls="sidebar-menu" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="sidebar-menu">
      <ul class="navbar-nav pt-lg-3">

        
        <?php if($isDispatcherOnly): ?>

          <li class="nav-item">
            <div class="nav-link text-uppercase text-muted fw-semibold small">Dispatch</div>
          </li>

          <li class="nav-item <?php echo e($isActive('dispatch') ? 'active' : ''); ?>">
            <a class="nav-link" href="<?php echo e(route('dispatch')); ?>"
               aria-current="<?php echo e($isActive('dispatch') ? 'page' : 'false'); ?>">
              <span class="nav-link-icon d-md-none d-lg-inline-block">
                <i class="ti ti-route"></i>
              </span>
              <span class="nav-link-title">Abrir Dispatch</span>
            </a>
          </li>

          
          

        <?php else: ?>

        
        <li class="nav-item">
          <div class="nav-link text-uppercase text-muted fw-semibold small">Core</div>
        </li>

        <li class="nav-item <?php echo e($isActive('admin.dashboard') ? 'active' : ''); ?>">
          <a class="nav-link" href="<?php echo e(Route::has('admin.dashboard') ? route('admin.dashboard') : url('/admin')); ?>"
             aria-current="<?php echo e($isActive('admin.dashboard') ? 'page' : 'false'); ?>">
            <span class="nav-link-icon d-md-none d-lg-inline-block">
              <i class="ti ti-home"></i>
            </span>
            <span class="nav-link-title">Dashboard</span>
          </a>
        </li>

        
        <li class="nav-item <?php echo e($isActive('dispatch') ? 'active' : ''); ?>">
          <a class="nav-link" href="<?php echo e(route('dispatch')); ?>"
             aria-current="<?php echo e($isActive('dispatch') ? 'page' : 'false'); ?>">
            <span class="nav-link-icon d-md-none d-lg-inline-block">
              <i class="ti ti-route"></i>
            </span>
            <span class="nav-link-title">Dispatch</span>
          </a>
        </li>

        
        

        
        <?php if($isTenantAdmin): ?>
          <li class="nav-item mt-3">
            <div class="nav-link text-uppercase text-muted fw-semibold small">Operación</div>
          </li>

          <li class="nav-item <?php echo e($isActive('sectores.*') ? 'active' : ''); ?>">
            <a class="nav-link" href="<?php echo e(Route::has('sectores.index') ? route('sectores.index') : url('/admin/sectores')); ?>">
              <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-grid-dots"></i></span>
              <span class="nav-link-title">Sectores</span>
            </a>
          </li>

          <li class="nav-item <?php echo e($isActive('taxistands.*') ? 'active' : ''); ?>">
            <a class="nav-link" href="<?php echo e(Route::has('taxistands.index') ? route('taxistands.index') : url('/admin/taxistands')); ?>">
              <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-flag-3"></i></span>
              <span class="nav-link-title">Paraderos</span>
            </a>
          </li>

          <li class="nav-item <?php echo e($isActive('drivers.*') ? 'active' : ''); ?>">
            <a class="nav-link" href="<?php echo e(Route::has('drivers.index') ? route('drivers.index') : url('/admin/drivers')); ?>">
              <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-user-check"></i></span>
              <span class="nav-link-title">Conductores</span>
            </a>
          </li>

          <li class="nav-item <?php echo e($isActive('vehicles.*') ? 'active' : ''); ?>">
            <a class="nav-link" href="<?php echo e(Route::has('vehicles.index') ? route('vehicles.index') : url('/admin/vehicles')); ?>">
              <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-car"></i></span>
              <span class="nav-link-title">Vehículos</span>
            </a>
          </li>
          <li class="sidebar-item <?php echo e($isActive('qr-points*')); ?>">
          <a class="sidebar-link" href="<?php echo e(route('admin.qr-points.index')); ?>">
            <i class="align-middle" data-feather="qr-code"></i>
            <span class="align-middle">QR Points</span>
          </a>
        </li>


          
          <li class="nav-item mt-3">
            <div class="nav-link text-uppercase text-muted fw-semibold small">Configuración</div>
          </li>

          <li class="nav-item <?php echo e($isActive('admin.tenant.edit') ? 'active' : ''); ?>">
            <a class="nav-link" href="<?php echo e(Route::has('admin.tenant.edit') ? route('admin.tenant.edit') : url('/mi-central')); ?>">
              <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-briefcase"></i></span>
              <span class="nav-link-title">Mi central</span>
            </a>
          </li>

          <li class="nav-item <?php echo e($isActive(['admin.billing.*','admin.billing.plan']) ? 'active' : ''); ?>">
            <a class="nav-link" href="<?php echo e(Route::has('admin.billing.plan') ? route('admin.billing.plan') : url('/admin/billing/plan')); ?>">
              <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-credit-card"></i></span>
              <span class="nav-link-title">Plan y facturación</span>
            </a>
          </li>

          <li class="nav-item <?php echo e($isActive('admin.dispatch_settings.*') ? 'active' : ''); ?>">
            <a class="nav-link" href="<?php echo e(Route::has('admin.dispatch_settings.edit') ? route('admin.dispatch_settings.edit') : url('/admin/dispatch-settings')); ?>">
              <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-adjustments-horizontal"></i></span>
              <span class="nav-link-title">Dispatch Settings</span>
            </a>
          </li>

          <li class="nav-item <?php echo e($isActive('admin.users.*') ? 'active' : ''); ?>">
            <a class="nav-link" href="<?php echo e(route('admin.users.index')); ?>">
              <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-users"></i></span>
              <span class="nav-link-title">Usuarios</span>
            </a>
          </li>

          <li class="nav-item <?php echo e($isActive('admin.fare_policies.*') ? 'active' : ''); ?>">
            <a class="nav-link" href="<?php echo e(Route::has('admin.fare_policies.index') ? route('admin.fare_policies.index') : url('/admin/fare-policies')); ?>">
              <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-currency-dollar"></i></span>
              <span class="nav-link-title">Tarifas</span>
            </a>
          </li>

          
          <li class="nav-item mt-3">
            <div class="nav-link text-uppercase text-muted fw-semibold small">Cobros</div>
          </li>

          <li class="nav-item <?php echo e($isActive('admin.taxi_fees') ? 'active' : ''); ?>">
            <a class="nav-link" href="<?php echo e(Route::has('admin.taxi_fees') ? route('admin.taxi_fees') : url('/admin/cobros/cuotas-taxi')); ?>">
              <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-currency-dollar"></i></span>
              <span class="nav-link-title">Cuotas por taxi</span>
            </a>
          </li>

          <li class="nav-item <?php echo e($isActive('admin.taxi_charges') ? 'active' : ''); ?>">
            <a class="nav-link" href="<?php echo e(Route::has('admin.taxi_charges') ? route('admin.taxi_charges') : url('/admin/cobros/taxi')); ?>">
              <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-receipt"></i></span>
              <span class="nav-link-title">Cobros (semanal)</span>
            </a>
          </li>

          
          <li class="nav-item mt-3">
            <div class="nav-link text-uppercase text-muted fw-semibold small">Business Intelligence</div>
          </li>

          <li class="nav-item <?php echo e($isActive('admin.bi.demand') ? 'active' : ''); ?>">
            <a class="nav-link" href="<?php echo e(route('admin.bi.demand')); ?>">
              <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-flame"></i></span>
              <span class="nav-link-title">Mapa de demanda</span>
            </a>
          </li>

          
          <li class="nav-item mt-3">
            <div class="nav-link text-uppercase text-muted fw-semibold small">Reportes</div>
          </li>

          <li class="nav-item <?php echo e($isActive('admin.reports.clients*') ? 'active' : ''); ?>">
            <a class="nav-link" href="<?php echo e(Route::has('admin.reports.clients') ? route('admin.reports.clients') : url('/admin/reportes/clientes')); ?>">
              <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-users"></i></span>
              <span class="nav-link-title">Clientes</span>
            </a>
          </li>

          <li class="nav-item <?php echo e($isActive('admin.reports.rides*') ? 'active' : ''); ?>">
            <a class="nav-link" href="<?php echo e(Route::has('admin.reports.rides') ? route('admin.reports.rides') : url('/admin/reportes/viajes')); ?>">
              <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-activity"></i></span>
              <span class="nav-link-title">Viajes</span>
            </a>
          </li>

          <li class="nav-item <?php echo e($isActive('admin.reports.drivers') ? 'active' : ''); ?>">
            <a class="nav-link" href="<?php echo e(Route::has('admin.reports.drivers') ? route('admin.reports.drivers') : url('/admin/reportes/conductores')); ?>">
              <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-award"></i></span>
              <span class="nav-link-title">Conductores</span>
            </a>
          </li>

          <li class="nav-item <?php echo e($isActive('ratings.*') ? 'active' : ''); ?>">
            <a class="nav-link" href="<?php echo e(Route::has('ratings.index') ? route('ratings.index') : url('/ratings/reports')); ?>">
              <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-star"></i></span>
              <span class="nav-link-title">Calificaciones</span>
            </a>
          </li>

        <?php endif; ?> 
        <?php endif; ?> 

      </ul>
    </div>
  </div>
</aside>

<?php $__env->startPush('styles'); ?>
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
<?php $__env->stopPush(); ?>
<?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/partials/sidebar_tabler.blade.php ENDPATH**/ ?>