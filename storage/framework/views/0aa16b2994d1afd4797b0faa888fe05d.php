<?php
use Illuminate\Support\Facades\Route;

// Helper: marcar activo
$is = function ($pattern) {
    return request()->routeIs($pattern) ? 'active' : '';
};
?>

<nav id="sidebar" class="sidebar js-sidebar">
  <div class="sidebar-content js-simplebar">

    
    <a class="sidebar-brand d-flex align-items-center gap-2" href="<?php echo e(route('sysadmin.dashboard')); ?>">
      <img
        src="<?php echo e(asset('images/logonf.png')); ?>"
        alt="logo"
        class="brand-img"
        height="28"
      >
      <span class="sidebar-brand-text">
        Orbana · SysAdmin
      </span>
    </a>

    <ul class="sidebar-nav">

      
      <li class="sidebar-header">Panel</li>

      <li class="sidebar-item <?php echo e($is('sysadmin.dashboard')); ?>">
        <a class="sidebar-link" href="<?php echo e(route('sysadmin.dashboard')); ?>">
          <i class="align-middle" data-feather="home"></i>
          <span class="align-middle">Dashboard</span>
        </a>
      </li>

      
      <li class="sidebar-item <?php echo e($is('sysadmin.tenants.*')); ?>">
  <a class="sidebar-link" href="<?php echo e(route('sysadmin.tenants.index')); ?>">
    <i class="align-middle" data-feather="layers"></i>
    <span class="align-middle">Lista de tenants</span>
  </a>
</li>


      
      <li class="sidebar-header">Facturación</li>

      <li class="sidebar-item <?php echo e($is('sysadmin.invoices.*')); ?>">
        <a class="sidebar-link" href="<?php echo e(route('sysadmin.invoices.index')); ?>">
          <i class="align-middle" data-feather="file-text"></i>
          <span class="align-middle">Facturas a tenants</span>
        </a>
      </li>

      
      <li class="sidebar-header">Verificación</li>

      <li class="sidebar-item <?php echo e($is('sysadmin.verifications.*')); ?>">
        <a class="sidebar-link" href="<?php echo e(route('sysadmin.verifications.index')); ?>">
          <i class="align-middle" data-feather="check-square"></i>
          <span class="align-middle">Cola de verificación</span>
        </a>
      </li>

      
      <li class="sidebar-header">Reportes</li>

      <li class="sidebar-item">
        <a class="sidebar-link" href="#">
          <i class="align-middle" data-feather="bar-chart-2"></i>
          <span class="align-middle">Comisiones (próx.)</span>
        </a>
      </li>

      
      <li class="sidebar-header">Otros</li>

      <li class="sidebar-item">
        <a class="sidebar-link" href="<?php echo e(route('admin.dashboard')); ?>">
          <i class="align-middle" data-feather="corner-up-left"></i>
          <span class="align-middle">Volver a panel tenant</span>
        </a>
      </li>

    </ul>
  </div>
</nav>
<?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/partials/sidebar_sysadmin.blade.php ENDPATH**/ ?>