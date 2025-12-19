<?php
/** @var \Illuminate\Pagination\LengthAwarePaginator $taxistands */
?>

<?php $__env->startSection('title','Paraderos (Taxi Stands)'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-fluid px-0">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="mb-0">Paraderos (Taxi Stands)</h3>
    <a href="<?php echo e(route('taxistands.create')); ?>" class="btn btn-primary">
      <i data-feather="plus"></i> Nuevo paradero
    </a>
  </div>

  <?php if(session('ok')): ?>
    <div class="alert alert-success"><?php echo e(session('ok')); ?></div>
  <?php endif; ?>

  <div class="table-responsive">
    <table class="table table-striped align-middle">
      <thead>
        <tr>
          <th>ID</th>
          <th>Nombre</th>
          <th>Sector</th>
          <th>Lat/Lng</th>
          <th>Capacidad</th>
          <th>Activo</th>
          <th class="text-end">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php $__empty_1 = true; $__currentLoopData = $taxistands; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <tr>
            <td><?php echo e($s->id); ?></td>
            <td><a href="<?php echo e(route('taxistands.edit',$s->id)); ?>"><?php echo e($s->nombre); ?></a></td>
            <td><?php echo e($s->sector_nombre ?? '—'); ?></td>
            <td class="text-muted"><?php echo e(number_format($s->latitud,6)); ?>, <?php echo e(number_format($s->longitud,6)); ?></td>
            <td><?php echo e($s->capacidad ?? 0); ?></td>
            <td>
              <?php if(($s->activo ?? $s->active ?? 1) == 1): ?>
                <span class="badge bg-success">Sí</span>
              <?php else: ?>
                <span class="badge bg-secondary">No</span>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-secondary" href="<?php echo e(route('taxistands.edit',$s->id)); ?>">
                <i data-feather="edit-2"></i>
              </a>
              <a class="btn btn-sm btn-outline-info" href="<?php echo e(route('taxistands.edit',$s->id)); ?>#preview">
                <i data-feather="map"></i>
              </a>
              <form action="<?php echo e(route('taxistands.destroy',$s->id)); ?>" method="POST" class="d-inline"
                    onsubmit="return confirm('¿Desactivar este paradero?');">
                <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                <button class="btn btn-sm btn-outline-danger" type="submit">
                  <i data-feather="slash"></i>
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <tr><td colspan="7" class="text-center text-muted">Sin paraderos aún.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="mt-3"><?php echo e($taxistands->links()); ?></div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/admin/taxistands/index.blade.php ENDPATH**/ ?>