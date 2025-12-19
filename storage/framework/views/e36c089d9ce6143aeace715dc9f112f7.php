<?php /** @var \Illuminate\Pagination\LengthAwarePaginator $sectores */ ?>

<?php $__env->startSection('title','Sectores'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-fluid p-0">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Sectores</h3>
    <a href="<?php echo e(route('sectores.create')); ?>" class="btn btn-primary">
      <i data-feather="plus"></i> Nuevo
    </a>
  </div>

  <?php if(session('ok')): ?> <div class="alert alert-success"><?php echo e(session('ok')); ?></div> <?php endif; ?>

  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th>ID</th>
          <th>Nombre</th>
          <th>Activo</th>
          <th>Actualizado</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php $__empty_1 = true; $__currentLoopData = $sectores; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <tr>
          <td><?php echo e($s->id); ?></td>
          <td><?php echo e($s->nombre); ?></td>
          <td><?php echo $s->activo ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-secondary">No</span>'; ?></td>
          <td><?php echo e($s->updated_at); ?></td>
          <td class="text-end">
            <a href="<?php echo e(route('sectores.show',$s->id)); ?>" class="btn btn-sm btn-outline-secondary"><i data-feather="eye"></i></a>
            <a href="<?php echo e(route('sectores.edit',$s->id)); ?>" class="btn btn-sm btn-outline-primary"><i data-feather="edit"></i></a>
            <form action="<?php echo e(route('sectores.destroy',$s->id)); ?>" method="POST" class="d-inline" onsubmit="return confirm('Desactivar sector?');">
              <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
              <button class="btn btn-sm btn-outline-danger"><i data-feather="x"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr><td colspan="5" class="text-center text-muted">Sin sectores</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php echo e($sectores->links()); ?>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/admin/sectores/index.blade.php ENDPATH**/ ?>