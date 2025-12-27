
<?php $__env->startSection('title','Usuarios'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xl">
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <h2 class="page-title">Usuarios del tenant</h2>
        <div class="text-muted mt-1">Admins y Dispatchers (staff interno)</div>
      </div>
      <div class="col-auto ms-auto">
        <a href="<?php echo e(route('admin.users.create')); ?>" class="btn btn-primary">
          <i class="ti ti-user-plus me-1"></i> Nuevo usuario
        </a>
      </div>
    </div>
  </div>

  <?php if(session('success')): ?>
    <div class="alert alert-success"><?php echo e(session('success')); ?></div>
  <?php endif; ?>
  <?php if(session('warning')): ?>
    <div class="alert alert-warning"><?php echo e(session('warning')); ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Listado</h3>
    </div>

    <div class="table-responsive">
      <table class="table card-table table-vcenter">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Email</th>
            <th>Rol</th>
            <th class="text-muted">Creado</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php $__empty_1 = true; $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php
              $isMe = auth()->check() && (int)auth()->id() === (int)$u->id;
            ?>

            <tr>
              <td class="fw-medium">
                <?php echo e($u->name); ?>

                <?php if($isMe): ?>
                  <span class="badge bg-secondary-lt ms-2">Tú</span>
                <?php endif; ?>
              </td>

              <td class="text-muted"><?php echo e($u->email); ?></td>

              <td>
                <?php if(!empty($u->is_admin)): ?>
                  <span class="badge bg-indigo-lt">
                    <i class="ti ti-shield me-1"></i> Admin
                  </span>
                <?php else: ?>
                  <span class="badge bg-azure-lt">
                    <i class="ti ti-headset me-1"></i> Dispatcher
                  </span>
                <?php endif; ?>
              </td>

              <td class="text-muted"><?php echo e(optional($u->created_at)->format('Y-m-d H:i')); ?></td>

              <td class="text-end">
                <div class="btn-list flex-nowrap justify-content-end">
                  <a class="btn btn-sm btn-outline-primary"
                     href="<?php echo e(route('admin.users.edit', $u)); ?>">
                    <i class="ti ti-pencil me-1"></i> Editar
                  </a>

                  
                  <?php if(!empty($u->is_admin) || !empty($u->is_dispatcher)): ?>
                    <a class="btn btn-sm btn-outline-secondary"
                       href="<?php echo e(route('dispatch')); ?>"
                       title="Abrir Dispatch">
                      <i class="ti ti-route me-1"></i> Ir a Dispatch
                    </a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr>
              <td colspan="5" class="text-center text-muted py-4">No hay usuarios.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/admin/users/index.blade.php ENDPATH**/ ?>