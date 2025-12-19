

<?php $__env->startSection('title','Nuevo sector'); ?>

<?php $__env->startPush('styles'); ?>
  <!-- (Opcional) estilos globales extra para esta vista -->
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div class="container-fluid p-0">
  <h3 class="mb-3">Nuevo sector</h3>

  <?php if($errors->any()): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <li><?php echo e($e); ?></li>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="post" action="<?php echo e(route('sectores.store')); ?>" id="formSector">
    <?php echo csrf_field(); ?>

    <div class="row g-3">
      <div class="col-12 col-xl-8">
        
        <?php echo $__env->make('admin.sectores._form', ['sector' => null], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      </div>

      <div class="col-12 col-xl-4">
        <div class="card">
          <div class="card-body">
            <h5 class="mb-3">Detalles</h5>

            <div class="mb-3">
              <label class="form-label">Nombre del sector</label>
              <input type="text" name="nombre" class="form-control" value="<?php echo e(old('nombre')); ?>" required>
            </div>

            <div class="d-grid gap-2">
              <button class="btn btn-primary" type="submit">Guardar sector</button>
              <a href="<?php echo e(route('sectores.index')); ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
  <!-- (Opcional) scripts globales extra para esta vista -->
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/admin/sectores/create.blade.php ENDPATH**/ ?>