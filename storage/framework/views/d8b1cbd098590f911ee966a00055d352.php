


<?php $__env->startSection('title', 'Recargar wallet'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h3 class="mb-0">Recargar wallet</h3>
            <div class="text-muted small">
                Recarga manual (simulación). Luego se conecta a MercadoPago/Conekta (OXXO/SPEI/tarjeta).
            </div>
        </div>
        <a href="<?php echo e(route('admin.wallet.index')); ?>" class="btn btn-outline-secondary">
            Volver
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-12 col-lg-6">
            <div class="card">
                <div class="card-header"><strong>Datos de recarga</strong></div>
                <div class="card-body">
                    <form method="POST" action="<?php echo e(route('admin.wallet.topup.store')); ?>">
                        <?php echo csrf_field(); ?>

                        <div class="mb-3">
                            <label class="form-label">Monto</label>
                            <input type="number"
                                   step="0.01"
                                   name="amount"
                                   value="<?php echo e(old('amount', request('amount'))); ?>"
                                   class="form-control <?php $__errorArgs = ['amount'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                                   min="1"
                                   placeholder="Ej. 1500">
                            <?php $__errorArgs = ['amount'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                            <div class="form-text">
                                Consejo: recarga el monto sugerido para cubrir el siguiente cargo del plan.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notas (opcional)</label>
                            <input type="text"
                                   name="notes"
                                   value="<?php echo e(old('notes')); ?>"
                                   class="form-control <?php $__errorArgs = ['notes'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                                   maxlength="255"
                                   placeholder="Ej. Recarga para cubrir fin de mes">
                            <?php $__errorArgs = ['notes'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="<?php echo e(route('admin.wallet.index')); ?>" class="btn btn-outline-secondary">Cancelar</a>
                            <button class="btn btn-primary">Aplicar recarga</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="alert alert-info mt-3 mb-0">
                <strong>Próximo paso:</strong> aquí se reemplaza el “aplicar recarga” por
                “crear intento de pago” y “confirmación por webhook” (MercadoPago/Conekta),
                incluyendo OXXO/SPEI/tarjeta.
            </div>
        </div>
    </div>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/admin/wallet/topup_create.blade.php ENDPATH**/ ?>