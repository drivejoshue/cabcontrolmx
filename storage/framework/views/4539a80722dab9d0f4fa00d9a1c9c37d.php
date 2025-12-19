
<?php $__env->startSection('title','Conductores'); ?>

<?php $__env->startSection('content'); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Conductores</h3>
  <a href="<?php echo e(route('drivers.create')); ?>" class="btn btn-primary"><i data-feather="plus"></i> Nuevo</a>
</div>

<form class="mb-3" method="get">
  <div class="input-group">
    <input type="text" class="form-control" name="q" value="<?php echo e($q); ?>" placeholder="Buscar por nombre, teléfono, email…">
    <button class="btn btn-outline-secondary">Buscar</button>
  </div>
</form>

<div class="card">
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th>Foto</th><th>Nombre</th><th>Teléfono</th><th>Email</th><th>Status</th><th class="text-end">Acciones</th>
        </tr>
      </thead>
      <tbody>
      <?php $__empty_1 = true; $__currentLoopData = $drivers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <tr>
          <td style="width:72px">
            <?php if($d->foto_path): ?>
              <img src="<?php echo e(asset('storage/'.$d->foto_path)); ?>" class="rounded" style="width:64px;height:40px;object-fit:cover;">
            <?php else: ?>
              <div class="bg-light border rounded d-flex align-items-center justify-content-center" style="width:64px;height:40px;">—</div>
            <?php endif; ?>
          </td>
          <td><?php echo e($d->name); ?></td>
          <td><?php echo e($d->phone ?? '—'); ?></td>
          <td><?php echo e($d->email ?? '—'); ?></td>
          <td>
            <span class="badge
              <?php if($d->status==='idle'): ?> bg-success
              <?php elseif($d->status==='busy'): ?> bg-warning text-dark
              <?php else: ?> bg-secondary <?php endif; ?>">
              <?php echo e($d->status); ?>

            </span>
          </td>
          <td class="text-end">
            <a href="<?php echo e(route('drivers.show',$d->id)); ?>" class="btn btn-sm btn-outline-secondary">Ver</a>
            <a href="<?php echo e(route('drivers.edit',$d->id)); ?>" class="btn btn-sm btn-primary">Editar</a>
          </td>
        </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr><td colspan="6" class="text-center text-muted">Sin conductores.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer">
    <?php echo e($drivers->links()); ?>

  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/admin/drivers/index.blade.php ENDPATH**/ ?>