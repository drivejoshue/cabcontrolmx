

<?php $__env->startSection('title','QR Points'); ?>

<?php $__env->startPush('styles'); ?>
<style>
  .qr-mini {
    width: 44px; height: 44px; border-radius: 10px;
    border: 1px solid rgba(0,0,0,.12);
    overflow: hidden;
    background: #fff;
    display:flex; align-items:center; justify-content:center;
  }
  [data-theme="dark"] .qr-mini {
    border-color: rgba(255,255,255,.14);
    background: rgba(255,255,255,.06);
  }
  .qr-mini img { width:100%; height:100%; object-fit:cover; display:block; }
  .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono","Courier New", monospace; }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div class="container-fluid p-0">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h1 class="h3 mb-0">QR Points</h1>
      <div class="text-muted">Crea puntos (hotel, restaurante, etc.) para que el pasajero pida taxi desde un QR.</div>
    </div>
    <a href="<?php echo e(route('admin.qr-points.create')); ?>" class="btn btn-primary btn-sm">
      + Nuevo QR Point
    </a>
  </div>

  <?php if(session('success')): ?>
    <div class="alert alert-success py-2"><?php echo e(session('success')); ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header">
      <form class="row g-2 align-items-center" method="GET" action="<?php echo e(route('admin.qr-points.index')); ?>">
        <div class="col-sm-6 col-md-4">
          <input class="form-control form-control-sm" name="q" value="<?php echo e($q); ?>" placeholder="Buscar por nombre, dirección o código">
        </div>
        <div class="col-auto">
          <button class="btn btn-sm btn-outline-secondary">Buscar</button>
        </div>
      </form>
    </div>

    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead>
          <tr>
            <th style="width:72px;">QR</th>
            <th>Punto</th>
            <th style="width:160px;">Código</th>
            <th style="width:120px;">Estado</th>
            <th style="width:240px;" class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php $__empty_1 = true; $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $it): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <?php
            // URL basada en APP_URL (env)
            $base = rtrim(config('app.url'), '/');
            $publicUrl = $base.'/q/'.$it->code;

            // QR mini (Google Charts) + fallback QuickChart
            $qrMini = 'https://chart.googleapis.com/chart?cht=qr&chs=96x96&chld=M|1&chl='.urlencode($publicUrl);
            $qrMiniAlt = 'https://quickchart.io/qr?size=120&text='.urlencode($publicUrl);
          ?>

          <tr>
            <td>
              <div class="qr-mini">
                <img
                  src="<?php echo e($qrMini); ?>"
                  alt="QR"
                  loading="lazy"
                  referrerpolicy="no-referrer"
                  onerror="this.onerror=null;this.src='<?php echo e($qrMiniAlt); ?>';"
                >
              </div>
            </td>

            <td>
              <div class="fw-semibold"><?php echo e($it->name); ?></div>
              <div class="text-muted small">
                <?php echo e($it->address_text ?: '—'); ?>

                <span class="ms-2 mono"><?php echo e(number_format($it->lat, 6)); ?>, <?php echo e(number_format($it->lng, 6)); ?></span>
              </div>
            </td>

            <td class="mono"><?php echo e($it->code); ?></td>

            <td>
              <?php if($it->active): ?>
                <span class="badge bg-success">Activo</span>
              <?php else: ?>
                <span class="badge bg-secondary">Inactivo</span>
              <?php endif; ?>
            </td>

            <td class="text-end">
              <div class="btn-group">
                
                <a class="btn btn-sm btn-outline-primary" href="<?php echo e(route('admin.qr-points.show', $it)); ?>">
                  Ver
                </a>

                <a class="btn btn-sm btn-outline-secondary" href="<?php echo e(route('admin.qr-points.edit', $it)); ?>">
                  Editar
                </a>

                <form method="POST" action="<?php echo e(route('admin.qr-points.destroy', $it)); ?>" onsubmit="return confirm('¿Eliminar este QR Point?');">
                  <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                  <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <tr><td colspan="5" class="text-center text-muted py-4">No hay QR Points.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="card-footer">
      <?php echo e($items->links()); ?>

    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/admin/qr_points/index.blade.php ENDPATH**/ ?>