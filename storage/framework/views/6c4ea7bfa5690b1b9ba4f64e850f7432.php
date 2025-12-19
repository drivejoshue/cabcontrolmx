

<?php $__env->startSection('title', 'Factura #'.$invoice->id.' – '.$tenant->name); ?>

<?php $__env->startSection('content'); ?>
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">
        Factura #<?php echo e($invoice->id); ?>

        <?php
          $st = strtolower($invoice->status);
          $badge = $st === 'paid'
              ? 'success'
              : ($st === 'canceled' ? 'secondary' : 'warning');
        ?>
        <span class="badge bg-<?php echo e($badge); ?> align-middle">
          <?php echo e(strtoupper($invoice->status)); ?>

        </span>
      </h3>
      <div class="text-muted small">
        Central: <?php echo e($tenant->name); ?> · Tenant ID: <?php echo e($tenant->id); ?><br>
        Periodo: <?php echo e($invoice->period_start); ?> → <?php echo e($invoice->period_end); ?>

      </div>
    </div>

    <div class="d-flex gap-2">
      <a href="<?php echo e(route('admin.billing.plan')); ?>" class="btn btn-outline-secondary">
        Volver a plan y facturación
      </a>
      <a href="<?php echo e(route('admin.billing.invoices.csv', $invoice)); ?>" class="btn btn-outline-primary">
        Descargar CSV
      </a>
      
    </div>
  </div>

  <div class="row g-3">

    
    <div class="col-12 col-lg-4">
      <div class="card h-100">
        <div class="card-header">
          <strong>Datos de la factura</strong>
        </div>
        <div class="card-body small">
          <dl class="row mb-0">
            <dt class="col-5">Tenant</dt>
            <dd class="col-7">
              #<?php echo e($tenant->id); ?> – <?php echo e($tenant->name); ?>

            </dd>

            <dt class="col-5">Periodo</dt>
            <dd class="col-7">
              <?php echo e($invoice->period_start); ?> → <?php echo e($invoice->period_end); ?>

            </dd>

            <dt class="col-5">Emitida el</dt>
            <dd class="col-7"><?php echo e($invoice->issue_date); ?></dd>

            <dt class="col-5">Vence el</dt>
            <dd class="col-7"><?php echo e($invoice->due_date); ?></dd>

            <dt class="col-5">Status</dt>
            <dd class="col-7"><?php echo e($invoice->status); ?></dd>

            <dt class="col-5">Moneda</dt>
            <dd class="col-7"><?php echo e($invoice->currency); ?></dd>
          </dl>
        </div>
      </div>
    </div>

    
    <div class="col-12 col-lg-4">
      <div class="card h-100">
        <div class="card-header">
          <strong>Importes</strong>
        </div>
        <div class="card-body small">
          <dl class="row mb-0">
            <dt class="col-6">Vehículos facturados</dt>
            <dd class="col-6"><?php echo e($invoice->vehicles_count); ?></dd>

            <dt class="col-6">Base mensual</dt>
            <dd class="col-6">
              $<?php echo e(number_format($invoice->base_fee, 2)); ?> <?php echo e($invoice->currency); ?>

            </dd>

            <dt class="col-6">Cargo por vehículos extra</dt>
            <dd class="col-6">
              $<?php echo e(number_format($invoice->vehicles_fee, 2)); ?> <?php echo e($invoice->currency); ?>

            </dd>

            <dt class="col-6">Total</dt>
            <dd class="col-6 fw-bold">
              $<?php echo e(number_format($invoice->total, 2)); ?> <?php echo e($invoice->currency); ?>

            </dd>
          </dl>

          <hr>

          <div class="small text-muted">
            Perfil de billing: <?php echo e($profile->plan_code ?? 'N/D'); ?><br>
            Modelo:
            <?php if(($profile->billing_model ?? 'per_vehicle') === 'per_vehicle'): ?>
              Cobro por vehículo
            <?php else: ?>
              Comisión por viaje
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    
    <div class="col-12 col-lg-4">
      <div class="card h-100">
        <div class="card-header">
          <strong>Notas</strong>
        </div>
        <div class="card-body small">
          <?php if($invoice->notes): ?>
            <p class="mb-0"><?php echo e($invoice->notes); ?></p>
          <?php else: ?>
            <p class="text-muted mb-0">
              No hay notas registradas para esta factura.
            </p>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div> 
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/admin/billing/invoice_show.blade.php ENDPATH**/ ?>