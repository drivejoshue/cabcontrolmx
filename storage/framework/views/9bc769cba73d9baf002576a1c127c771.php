


<?php $__env->startSection('title', 'Wallet de la central'); ?>

<?php $__env->startPush('styles'); ?>
<style>
    .kpi { font-size: 1.6rem; font-weight: 800; line-height: 1.1; }
    .soft-card { border: 1px solid rgba(0,0,0,.06); }
    [data-theme="dark"] .soft-card { border-color: rgba(255,255,255,.10); }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<?php
    $bal = (float)($wallet->balance ?? 0);
    $cur = $wallet->currency ?? 'MXN';

    $nextAmount = is_array($nextCharge) ? (float)($nextCharge['amount'] ?? 0) : (is_object($nextCharge) ? (float)($nextCharge->amount ?? 0) : 0);
    $nextLabel  = is_array($nextCharge) ? ($nextCharge['label'] ?? null) : (is_object($nextCharge) ? ($nextCharge->label ?? null) : null);
?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h3 class="mb-0">Wallet</h3>
            <div class="text-muted small">Fondos disponibles para cubrir cargos del plan</div>
        </div>

        <div class="d-flex gap-2">
            <a href="<?php echo e(route('admin.billing.plan')); ?>" class="btn btn-outline-secondary">
                Volver a Plan
            </a>
            <a href="<?php echo e(route('admin.wallet.topup.create')); ?>" class="btn btn-primary">
                Recargar
            </a>
        </div>
    </div>

    <?php if(session('ok')): ?>
        <div class="alert alert-success"><?php echo e(session('ok')); ?></div>
    <?php endif; ?>
    <?php if(session('success')): ?>
        <div class="alert alert-success"><?php echo e(session('success')); ?></div>
    <?php endif; ?>

    <div class="row g-3">

        <div class="col-12 col-lg-5">
            <div class="card soft-card">
                <div class="card-header"><strong>Saldo</strong></div>
                <div class="card-body">
                    <div class="kpi">
                        $<?php echo e(number_format($bal, 2)); ?> <span class="fs-6"><?php echo e($cur); ?></span>
                    </div>

                    <div class="text-muted small mt-1">
                        <?php if(!empty($wallet->last_topup_at)): ?>
                            Última recarga: <?php echo e(\Carbon\Carbon::parse($wallet->last_topup_at)->format('d M Y H:i')); ?>

                        <?php else: ?>
                            Sin recargas registradas aún.
                        <?php endif; ?>
                    </div>

                    <hr>

                    <div class="d-flex gap-2 flex-wrap">
                        <a href="<?php echo e(route('admin.wallet.topup.create')); ?>" class="btn btn-primary">
                            Recargar wallet
                        </a>

                        <?php if($nextAmount > 0): ?>
                            <a href="<?php echo e(route('admin.wallet.topup.create', ['amount' => $nextAmount])); ?>"
                               class="btn btn-outline-primary">
                                Recargar monto sugerido
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if($nextAmount > 0): ?>
                        <div class="mt-3 p-3 bg-light rounded">
                            <div class="fw-semibold">Siguiente cargo estimado</div>
                            <div class="text-muted small">
                                <?php echo e($nextLabel ?: 'Próximo ciclo'); ?>

                            </div>
                            <div class="fw-bold mt-1">
                                $<?php echo e(number_format($nextAmount, 2)); ?> <?php echo e($cur); ?>

                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            
            <div class="card soft-card mt-3">
                <div class="card-header"><strong>Recarga manual (simulación)</strong></div>
                <div class="card-body">
                    <form method="POST" action="<?php echo e(route('admin.wallet.topup.manual')); ?>" class="row g-2">
                        <?php echo csrf_field(); ?>
                        <div class="col-12">
                            <label class="form-label">Monto</label>
                            <input type="number" step="0.01" name="amount"
                                   class="form-control <?php $__errorArgs = ['amount'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                                   min="10" max="200000" placeholder="Ej. 1500">
                            <?php $__errorArgs = ['amount'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Notas (opcional)</label>
                            <input type="text" name="notes"
                                   class="form-control <?php $__errorArgs = ['notes'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                                   maxlength="255" placeholder="Ej. Recarga para cubrir el mes">
                            <?php $__errorArgs = ['notes'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>

                        <div class="col-12 d-flex justify-content-end">
                            <button class="btn btn-primary">Aplicar recarga</button>
                        </div>

                        <div class="col-12 text-muted small">
                            Esto es temporal para pruebas. Después se reemplaza por MercadoPago/Conekta (OXXO, SPEI, tarjeta).
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <div class="card soft-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Movimientos recientes</strong>
                    <span class="text-muted small">Últimos 100</span>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th class="text-end">Monto</th>
                                <th>Referencia</th>
                                <th>Notas</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php $__empty_1 = true; $__currentLoopData = $movements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <?php
                                    $type = strtolower((string)$m->type);
                                    $isIn = in_array($type, ['topup','credit','refund','adjust'], true);
                                ?>
                                <tr>
                                    <td class="mono"><?php echo e($m->id); ?></td>
                                    <td><?php echo e(\Carbon\Carbon::parse($m->created_at)->format('d M Y H:i')); ?></td>
                                    <td>
                                        <span class="badge <?php echo e($isIn ? 'bg-success' : 'bg-danger'); ?> text-uppercase">
                                            <?php echo e($type); ?>

                                        </span>
                                    </td>
                                    <td class="text-end fw-semibold <?php echo e($isIn ? 'text-success' : 'text-danger'); ?>">
                                        <?php echo e($isIn ? '+' : '-'); ?> $<?php echo e(number_format((float)$m->amount, 2)); ?>

                                    </td>
                                    <td class="mono small">
                                        <?php echo e($m->external_ref ?: ($m->ref_type ? ($m->ref_type.'#'.$m->ref_id) : '—')); ?>

                                    </td>
                                    <td class="small text-muted"><?php echo e($m->notes ?: '—'); ?></td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        Aún no hay movimientos en el wallet.
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/admin/wallet/index.blade.php ENDPATH**/ ?>