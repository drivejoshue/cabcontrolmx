

<?php $__env->startSection('title','Vehiculos'); ?>
<?php $__env->startSection('page-id','vehicles'); ?>

<?php $__env->startSection('content'); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Vehículos</h3>
  <a href="<?php echo e(route('vehicles.create')); ?>" class="btn btn-primary"><i data-feather="plus"></i> Nuevo</a>
</div>

<form class="mb-3" method="get">
  <div class="input-group">
    <input type="text" class="form-control" name="q" value="<?php echo e($q); ?>" placeholder="Buscar por económico, placa, marca, modelo…">
    <button class="btn btn-outline-secondary">Buscar</button>
  </div>
</form>

<div class="card">
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th>Foto</th><th>Económico</th><th>Placa</th><th>Marca/Modelo</th><th>Año</th><th>Cap.</th><th>Activo</th><th class="text-end">Acciones</th>
        </tr>
      </thead>
      <tbody>
      <?php $__empty_1 = true; $__currentLoopData = $vehicles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <tr>
          <td style="width:72px">
            <?php if($v->foto_path): ?>
              <img src="<?php echo e(asset('storage/'.$v->foto_path)); ?>" class="rounded" style="width:64px;height:40px;object-fit:cover;">
            <?php else: ?>
              <div class="bg-light border rounded d-flex align-items-center justify-content-center" style="width:64px;height:40px;">—</div>
            <?php endif; ?>
          </td>
          <td><?php echo e($v->economico); ?></td>
          <td><?php echo e($v->plate); ?></td>
          <td><?php echo e(trim(($v->brand ?? '').' '.($v->model ?? '')) ?: '—'); ?></td>
          <td><?php echo e($v->year ?? '—'); ?></td>
          <td><?php echo e($v->capacity); ?></td>
          <td><?php if($v->active): ?> <span class="badge bg-success">Sí</span> <?php else: ?> <span class="badge bg-secondary">No</span> <?php endif; ?></td>
          <td class="text-end">
            <a href="<?php echo e(route('vehicles.show',$v->id)); ?>" class="btn btn-sm btn-outline-secondary">Ver</a>
            <a href="<?php echo e(route('vehicles.edit',$v->id)); ?>" class="btn btn-sm btn-primary">Editar</a>
          </td>
        </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr><td colspan="8" class="text-center text-muted">Sin vehículos.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer">
    <?php echo e($vehicles->links()); ?>

  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin_tabler', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/admin/vehicles/index.blade.php ENDPATH**/ ?>