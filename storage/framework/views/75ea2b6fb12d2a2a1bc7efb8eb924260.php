


<?php $__env->startSection('title','Dispatch Settings'); ?>

<?php $__env->startSection('content'); ?>
<?php if(session('ok')): ?>
  <div class="alert alert-success"><?php echo e(session('ok')); ?></div>
<?php endif; ?>
<?php if($errors->any()): ?>
  <div class="alert alert-danger">
    <ul class="mb-0">
      <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($e); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </ul>
  </div>
<?php endif; ?>

<form class="row g-3" method="POST" action="<?php echo e(route('admin.dispatch_settings.update')); ?>">
  <?php echo csrf_field(); ?>
  <?php echo method_field('PUT'); ?>

  <div class="col-12">
    <div class="card shadow-sm border-0">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="mb-0">Auto Dispatch</h5>
      <span class="badge bg-secondary">
        Tenant: <?php echo e(auth()->user()->tenant_id ?? 'sin-tenant'); ?>

      </span>
      </div>
      <div class="card-body row g-3">
        <div class="col-md-3">
          <label class="form-label">Habilitado</label>
          <select name="auto_enabled" class="form-select">
            <option value="1" <?php if(old('auto_enabled', (int)$row->auto_enabled) == 1): echo 'selected'; endif; ?>>Sí</option>
            <option value="0" <?php if(old('auto_enabled', (int)$row->auto_enabled) == 0): echo 'selected'; endif; ?>>No</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Delay (s)</label>
          <input type="number" step="1" min="0" class="form-control"
                 name="auto_dispatch_delay_s"
                 value="<?php echo e(old('auto_dispatch_delay_s', $row->auto_dispatch_delay_s)); ?>" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Previsualizar N</label>
          <input type="number" step="1" min="1" class="form-control"
                 name="auto_dispatch_preview_n"
                 value="<?php echo e(old('auto_dispatch_preview_n', $row->auto_dispatch_preview_n)); ?>" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Radio previsualización (km)</label>
          <input type="number" step="0.01" min="0" class="form-control"
                 name="auto_dispatch_radius_km"
                 value="<?php echo e(old('auto_dispatch_radius_km', $row->auto_dispatch_radius_km)); ?>" />
        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card shadow-sm border-0">
      <div class="card-header"><h5 class="mb-0">Olas & Expiración</h5></div>
      <div class="card-body row g-3">
        <div class="col-md-3">
          <label class="form-label">Tamaño de ola (N)</label>
          <input type="number" step="1" min="1" class="form-control"
                 name="wave_size_n"
                 value="<?php echo e(old('wave_size_n', $row->wave_size_n)); ?>" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Expira oferta (seg)</label>
          <input type="number" step="1" min="5" class="form-control"
                 name="offer_expires_sec"
                 value="<?php echo e(old('offer_expires_sec', $row->offer_expires_sec)); ?>" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Lead time (min)</label>
          <input type="number" step="1" min="0" class="form-control"
                 name="lead_time_min"
                 value="<?php echo e(old('lead_time_min', $row->lead_time_min)); ?>" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Auto-asignar si único</label>
          <select name="auto_assign_if_single" class="form-select">
            <option value="1" <?php if(old('auto_assign_if_single', (int)$row->auto_assign_if_single) == 1): echo 'selected'; endif; ?>>Sí</option>
            <option value="0" <?php if(old('auto_assign_if_single', (int)$row->auto_assign_if_single) == 0): echo 'selected'; endif; ?>>No</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card shadow-sm border-0">
      <div class="card-header"><h5 class="mb-0">Búsqueda & Bases</h5></div>
      <div class="card-body row g-3">
        <div class="col-md-4">
          <label class="form-label">Radio general (km)</label>
          <input type="number" step="0.01" min="0" class="form-control"
                 name="nearby_search_radius_km"
                 value="<?php echo e(old('nearby_search_radius_km', $row->nearby_search_radius_km)); ?>" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Radio de base (km)</label>
          <input type="number" step="0.01" min="0" class="form-control"
                 name="stand_radius_km"
                 value="<?php echo e(old('stand_radius_km', $row->stand_radius_km)); ?>" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Usar Google para ETA</label>
          <select name="use_google_for_eta" class="form-select">
            <option value="1" <?php if(old('use_google_for_eta', (int)$row->use_google_for_eta) == 1): echo 'selected'; endif; ?>>Sí</option>
            <option value="0" <?php if(old('use_google_for_eta', (int)$row->use_google_for_eta) == 0): echo 'selected'; endif; ?>>No</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 text-end">
    <button class="btn btn-lg btn-primary shadow">Guardar cambios</button>
  </div>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/admin/dispatch_settings/edit.blade.php ENDPATH**/ ?>