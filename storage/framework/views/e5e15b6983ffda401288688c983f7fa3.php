<?php
// resources/views/partials/sidebar_adminkit.blade.php

use Illuminate\Support\Facades\Route;

$u = auth()->user();
$tenantId = $u?->tenant_id;

// Regla real: tenant admin = tenant_id + is_admin true (ajusta a tu campo real)
$isTenantAdmin = (bool)($tenantId && ($u->is_admin ?? false));

/**
 * Activo para item simple:
 *  - acepta un string o array de patterns
 */
$isActive = function ($patterns): string {
    $patterns = is_array($patterns) ? $patterns : [$patterns];
    foreach ($patterns as $p) {
        if (request()->routeIs($p)) return 'active';
    }
    return '';
};

// Branding (puedes mover a config/branding.php si quieres)
$brandName = 'Orbana Core';
$logoCircle = asset('images/logonf.png');   // círculo sin texto
$logoInline = asset('images/logo.png');     // logo inline (con texto o sin, pero horizontal)
?>
<nav id="sidebar" class="sidebar js-sidebar">
  <div class="sidebar-content js-simplebar">

    
    <a class="sidebar-brand d-flex align-items-center gap-2 orb-brand"
       href="<?php echo e(Route::has('admin.dashboard') ? route('admin.dashboard') : url('/admin')); ?>">

      
      <img src="<?php echo e($logoCircle); ?>" alt="Orbana" class="orb-brand-circle" width="28" height="28">

      
      <span class="d-flex flex-column lh-sm">
        <span class="orb-brand-title"><?php echo e($brandName); ?></span>
        <small class="orb-brand-sub">Dispatch &amp; Operación</small>
      </span>
    </a>

    <ul class="sidebar-nav">

      
      <li class="sidebar-header">Core</li>

      <li class="sidebar-item <?php echo e($isActive('admin.dashboard')); ?>">
        <a class="sidebar-link"
           href="<?php echo e(Route::has('admin.dashboard') ? route('admin.dashboard') : url('/admin')); ?>"
           aria-current="<?php echo e(request()->routeIs('admin.dashboard') ? 'page' : 'false'); ?>">
          <i class="align-middle" data-feather="home"></i>
          <span class="align-middle">Dashboard</span>
        </a>
      </li>

      <li class="sidebar-item <?php echo e($isActive('admin.dispatch')); ?>">
        <a class="sidebar-link"
           href="<?php echo e(Route::has('admin.dispatch') ? route('admin.dispatch') : url('/admin/dispatch')); ?>"
           aria-current="<?php echo e(request()->routeIs('admin.dispatch') ? 'page' : 'false'); ?>">
          <i class="align-middle" data-feather="map"></i>
          <span class="align-middle">Mapa (Dispatch)</span>
        </a>
      </li>

      
      <?php if($isTenantAdmin): ?>
        <li class="sidebar-header">Operación</li>

        <li class="sidebar-item <?php echo e($isActive('sectores.*')); ?>">
          <a class="sidebar-link"
             href="<?php echo e(Route::has('sectores.index') ? route('sectores.index') : url('/admin/sectores')); ?>">
            <i class="align-middle" data-feather="grid"></i>
            <span class="align-middle">Sectores</span>
          </a>
        </li>

        <li class="sidebar-item <?php echo e($isActive('taxistands.*')); ?>">
          <a class="sidebar-link"
             href="<?php echo e(Route::has('taxistands.index') ? route('taxistands.index') : url('/admin/taxistands')); ?>">
            <i class="align-middle" data-feather="flag"></i>
            <span class="align-middle">Paraderos</span>
          </a>
        </li>

        <li class="sidebar-item <?php echo e($isActive('drivers.*')); ?>">
          <a class="sidebar-link"
             href="<?php echo e(Route::has('drivers.index') ? route('drivers.index') : url('/admin/drivers')); ?>">
            <i class="align-middle" data-feather="user-check"></i>
            <span class="align-middle">Conductores</span>
          </a>
        </li>

        <li class="sidebar-item <?php echo e($isActive('vehicles.*')); ?>">
          <a class="sidebar-link"
             href="<?php echo e(Route::has('vehicles.index') ? route('vehicles.index') : url('/admin/vehicles')); ?>">
            <i class="align-middle" data-feather="truck"></i>
            <span class="align-middle">Vehículos</span>
          </a>
        </li>

        
        <li class="sidebar-header">Configuración</li>

        <li class="sidebar-item <?php echo e($isActive('admin.tenant.edit')); ?>">
          <a class="sidebar-link"
             href="<?php echo e(Route::has('admin.tenant.edit') ? route('admin.tenant.edit') : url('/mi-central')); ?>">
            <i class="align-middle" data-feather="briefcase"></i>
            <span class="align-middle">Mi central</span>
          </a>
        </li>

        <li class="sidebar-item <?php echo e($isActive(['admin.billing.*','admin.billing.plan'])); ?>">
          <a class="sidebar-link"
             href="<?php echo e(Route::has('admin.billing.plan') ? route('admin.billing.plan') : url('/admin/billing/plan')); ?>">
            <i class="align-middle" data-feather="credit-card"></i>
            <span class="align-middle">Plan y facturación</span>
          </a>
        </li>

        <li class="sidebar-item <?php echo e($isActive('admin.dispatch_settings.*')); ?>">
          <a class="sidebar-link"
             href="<?php echo e(Route::has('admin.dispatch_settings.edit') ? route('admin.dispatch_settings.edit') : url('/admin/dispatch-settings')); ?>">
            <i class="align-middle" data-feather="sliders"></i>
            <span class="align-middle">Dispatch Settings</span>
          </a>
        </li>

        <li class="sidebar-item <?php echo e($isActive('admin.fare_policies.*')); ?>">
          <a class="sidebar-link"
             href="<?php echo e(Route::has('admin.fare_policies.index') ? route('admin.fare_policies.index') : url('/admin/fare-policies')); ?>">
            <i class="align-middle" data-feather="dollar-sign"></i>
            <span class="align-middle">Tarifas</span>
          </a>
        </li>

        
        <li class="sidebar-header">Reportes</li>

        <li class="sidebar-item <?php echo e($isActive('admin.reports.clients*')); ?>">
          <a class="sidebar-link"
             href="<?php echo e(Route::has('admin.reports.clients') ? route('admin.reports.clients') : url('/admin/reportes/clientes')); ?>">
            <i class="align-middle" data-feather="users"></i>
            <span class="align-middle">Clientes</span>
          </a>
        </li>

        <li class="sidebar-item <?php echo e($isActive('admin.reports.rides*')); ?>">
          <a class="sidebar-link"
             href="<?php echo e(Route::has('admin.reports.rides') ? route('admin.reports.rides') : url('/admin/reportes/viajes')); ?>">
            <i class="align-middle" data-feather="activity"></i>
            <span class="align-middle">Viajes</span>
          </a>
        </li>

        <li class="sidebar-item <?php echo e($isActive('admin.reports.drivers')); ?>">
          <a class="sidebar-link"
             href="<?php echo e(Route::has('admin.reports.drivers') ? route('admin.reports.drivers') : url('/admin/reportes/conductores')); ?>">
            <i class="align-middle" data-feather="award"></i>
            <span class="align-middle">Conductores</span>
          </a>
        </li>

        <li class="sidebar-item <?php echo e($isActive('ratings.*')); ?>">
          <a class="sidebar-link"
             href="<?php echo e(Route::has('ratings.index') ? route('ratings.index') : url('/ratings/reports')); ?>">
            <i class="align-middle" data-feather="star"></i>
            <span class="align-middle">Calificaciones</span>
          </a>
        </li>

        <li class="sidebar-item <?php echo e($isActive('admin.reports.revenue*')); ?>">
          <a class="sidebar-link"
             href="<?php echo e(Route::has('admin.reports.revenue') ? route('admin.reports.revenue') : url('/admin/reportes/ingresos')); ?>">
            <i class="align-middle" data-feather="bar-chart-2"></i>
            <span class="align-middle">Ingresos</span>
          </a>
        </li>
      <?php endif; ?>

    </ul>
  </div>
</nav>

<?php $__env->startPush('styles'); ?>
<style>
  /* Branding Orbana Core (AdminKit sidebar) */
  .orb-brand { padding-top: 1rem; padding-bottom: 1rem; }
  .orb-brand-circle{
    border-radius: 999px;
    object-fit: cover;
    box-shadow: 0 0 0 2px rgba(255,255,255,.06);
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

  /* Si estás en dark, mantenemos acento Orbana (sin inventar gradientes) */
  html.theme-dark .orb-brand-title { color: #E5E7EB; }
  html.theme-dark .orb-brand-sub   { color: rgba(229,231,235,.72); }
</style>
<?php $__env->stopPush(); ?>
<?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/partials/sidebar_adminkit.blade.php ENDPATH**/ ?>