
<?php $__env->startSection('title','Tarifas'); ?>
<?php $__env->startSection('content'); ?>

<?php if(session('ok')): ?>
  <div class="alert alert-success"><?php echo e(session('ok')); ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Política de tarifa</h3>
  <a href="<?php echo e(route('admin.fare_policies.edit', ['tenant_id'=>$tenantId])); ?>" class="btn btn-primary shadow">
    Editar
  </a>
</div>

<?php if(!$policy): ?>
  <div class="card shadow-sm border-0">
    <div class="card-body text-muted">
      No hay política cargada aún para este tenant. Presiona <strong>Editar</strong> para crear la base.
    </div>
  </div>
<?php else: ?>
  <div class="card shadow-sm border-0">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-3"><div class="text-muted small">Modo</div><div class="fw-semibold"><?php echo e($policy->mode); ?></div></div>
        <div class="col-md-3"><div class="text-muted small">Base</div><div class="fw-semibold"><?php echo e(number_format($policy->base_fee,2)); ?></div></div>
        <div class="col-md-3"><div class="text-muted small">Por km</div><div class="fw-semibold"><?php echo e(number_format($policy->per_km,2)); ?></div></div>
        <div class="col-md-3"><div class="text-muted small">Por min</div><div class="fw-semibold"><?php echo e(number_format($policy->per_min,2)); ?></div></div>

        <div class="col-md-3"><div class="text-muted small">Noche: inicia</div><div class="fw-semibold"><?php echo e($policy->night_start_hour); ?></div></div>
        <div class="col-md-3"><div class="text-muted small">Noche: termina</div><div class="fw-semibold"><?php echo e($policy->night_end_hour); ?></div></div>
        <div class="col-md-3"><div class="text-muted small">Mult. noche</div><div class="fw-semibold"><?php echo e(number_format($policy->night_multiplier,2)); ?></div></div>

        <div class="col-md-3"><div class="text-muted small">Redondeo</div>
          <div class="fw-semibold">
            <?php echo e($policy->round_mode); ?>

            <?php if($policy->round_mode === 'decimals'): ?> (<?php echo e($policy->round_decimals); ?>)
            <?php else: ?>
            <?php endif; ?>
          </div>
        </div>

        <div class="col-md-3"><div class="text-muted small">Round to</div><div class="fw-semibold"><?php echo e(number_format($policy->round_to,2)); ?></div></div>
        <div class="col-md-3"><div class="text-muted small">Mínimo</div><div class="fw-semibold"><?php echo e(number_format($policy->min_total,2)); ?></div></div>

        <div class="col-md-12">
          <div class="text-muted small mb-1">Extras (JSON)</div>
          <pre class="mb-0 bg-light p-2 rounded small"><?php echo e($policy->extras ? json_encode($policy->extras, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : '{}'); ?></pre>
        </div>

        <div class="col-md-6"><div class="text-muted small">Vigente desde</div><div class="fw-semibold"><?php echo e($policy->active_from?->format('Y-m-d') ?? '—'); ?></div></div>
        <div class="col-md-6"><div class="text-muted small">Vigente hasta</div><div class="fw-semibold"><?php echo e($policy->active_to?->format('Y-m-d') ?? '—'); ?></div></div>
      </div>
    </div>
    <div class="card-footer bg-transparent border-0 text-end">
      <a href="<?php echo e(route('admin.fare_policies.edit', ['tenant_id'=>$tenantId])); ?>" class="btn btn-outline-primary">
        Editar
      </a>
    </div>
  </div>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/admin/fare_policies/index.blade.php ENDPATH**/ ?>