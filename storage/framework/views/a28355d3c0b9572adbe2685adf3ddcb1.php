

<?php $__env->startSection('title','Mi Central'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-fluid p-0">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h1 class="h3 mb-1">Mi Central</h1>
      <div class="text-muted">Administra los datos públicos y de notificación de tu central.</div>
    </div>
    <div class="text-end small text-muted">
      <div>Tenant ID: <strong><?php echo e($tenant->id); ?></strong></div>
      <div>Onboarding: <strong><?php echo e($tenant->onboarding_done_at ? 'Completado' : 'Pendiente'); ?></strong></div>
    </div>
  </div>

  <?php if(session('status')): ?>
    <div class="alert alert-success"><?php echo e(session('status')); ?></div>
  <?php endif; ?>

  <?php if($errors->any()): ?>
    <div class="alert alert-danger">
      <div class="fw-bold mb-1">Revisa los campos:</div>
      <ul class="mb-0">
        <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <li><?php echo e($e); ?></li>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="POST" action="<?php echo e(route('admin.tenant.update')); ?>">
    <?php echo csrf_field(); ?>

    <div class="row g-3">
      
      <div class="col-12 col-lg-7">
        <div class="card shadow-sm border-0">
          <div class="card-header bg-transparent border-0 pb-0">
            <h5 class="card-title mb-0">Datos generales</h5>
            <div class="text-muted small">Estos datos puedes cambiarlos cuando gustes.</div>
          </div>

          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">Nombre de la central <span class="text-danger">*</span></label>
              <input
                type="text"
                name="name"
                class="form-control <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                value="<?php echo e(old('name', $tenant->name)); ?>"
                maxlength="150"
                required
              >
              <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>

            <div class="mb-3">
              <label class="form-label">Email de notificaciones</label>
              <input
                type="email"
                name="notification_email"
                class="form-control <?php $__errorArgs = ['notification_email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                value="<?php echo e(old('notification_email', $tenant->notification_email)); ?>"
                maxlength="190"
                placeholder="Ej. notificaciones@tucentral.com"
              >
              <div class="form-text">
                Si lo dejas vacío, se usará tu email de usuario: <strong><?php echo e(auth()->user()->email); ?></strong>
              </div>
              <?php $__errorArgs = ['notification_email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>

            <div class="row g-2">
              <div class="col-12 col-md-6">
                <div class="mb-3">
                  <label class="form-label">Teléfono público</label>
                  <input
                    type="text"
                    name="public_phone"
                    class="form-control <?php $__errorArgs = ['public_phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                    value="<?php echo e(old('public_phone', $tenant->public_phone)); ?>"
                    maxlength="30"
                    placeholder="Ej. +52 229 123 4567"
                  >
                  <?php $__errorArgs = ['public_phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>
              </div>

              <div class="col-12 col-md-6">
                <div class="mb-3">
                  <label class="form-label">Ciudad pública</label>
                  <input
                    type="text"
                    name="public_city"
                    class="form-control <?php $__errorArgs = ['public_city'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                    value="<?php echo e(old('public_city', $tenant->public_city)); ?>"
                    maxlength="120"
                    placeholder="Ej. Veracruz, Ver."
                  >
                  <?php $__errorArgs = ['public_city'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>
              </div>
            </div>

            
            

            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary">
                Guardar cambios
              </button>
              <a href="<?php echo e(route('admin.dashboard')); ?>" class="btn btn-outline-secondary">
                Volver
              </a>
            </div>
          </div>
        </div>
      </div>

      
      <div class="col-12 col-lg-5">
        <div class="card shadow-sm border-0">
          <div class="card-header bg-transparent border-0 pb-0">
            <h5 class="card-title mb-0">Ubicación y cobertura</h5>
            <div class="text-muted small">
              Las coordenadas no se pueden editar desde aquí.
            </div>
          </div>

          <div class="card-body">
            <div class="alert alert-warning">
              <div class="fw-bold mb-1">Coordenadas bloqueadas</div>
              <div class="small">
                Para cambiar <strong>latitud/longitud</strong> o el <strong>radio</strong>, debes solicitarlo a <strong>SysAdmin</strong>.
              </div>
            </div>

            <div class="row g-2">
              <div class="col-6">
                <label class="form-label">Latitud</label>
                <input type="text" class="form-control" value="<?php echo e($tenant->latitud ?? '-'); ?>" disabled>
              </div>
              <div class="col-6">
                <label class="form-label">Longitud</label>
                <input type="text" class="form-control" value="<?php echo e($tenant->longitud ?? '-'); ?>" disabled>
              </div>
              <div class="col-12">
                <label class="form-label">Radio de cobertura (km)</label>
                <input type="text" class="form-control" value="<?php echo e($tenant->coverage_radius_km ?? '-'); ?>" disabled>
              </div>
            </div>

            <hr class="my-3">

            <div class="row g-2">
              <div class="col-12">
                <label class="form-label">Zona horaria</label>
                <input type="text" class="form-control" value="<?php echo e($tenant->timezone ?? 'America/Mexico_City'); ?>" disabled>
              </div>
              <div class="col-12">
                <label class="form-label">UTC offset (min)</label>
                <input type="text" class="form-control" value="<?php echo e($tenant->utc_offset_minutes ?? '-'); ?>" disabled>
              </div>
            </div>

            <hr class="my-3">

            <div class="small text-muted">
              <div><strong>Slug:</strong> <?php echo e($tenant->slug); ?></div>
              <div><strong>Última actualización:</strong> <?php echo e($tenant->updated_at); ?></div>
              <div><strong>Onboarding done:</strong> <?php echo e($tenant->onboarding_done_at ?? '-'); ?></div>
            </div>

         <div class="mt-3">
  <div class="p-3 rounded border bg-light">
    <div class="fw-bold mb-1">Solicitud de cambio de coordenadas</div>
    <div class="small text-muted mb-2">
      Por seguridad, la ubicación y el radio solo pueden modificarse mediante solicitud a soporte (SysAdmin).
      Envía estos datos para que podamos validar y aplicar el cambio:
    </div>

    <div class="small">
      <div><strong>Central:</strong> <?php echo e($tenant->name); ?></div>
      <div><strong>Tenant ID:</strong> <?php echo e($tenant->id); ?></div>
      <div><strong>Lat/Lng:</strong> <?php echo e($tenant->latitud ?? '-'); ?>, <?php echo e($tenant->longitud ?? '-'); ?></div>
      <div><strong>Radio (km):</strong> <?php echo e($tenant->coverage_radius_km ?? '-'); ?></div>
      <div><strong>Ciudad pública:</strong> <?php echo e($tenant->public_city ?? '-'); ?></div>
    </div>

    <button type="button" class="btn btn-outline-secondary w-100 mt-2" id="btnCopyTenantLocation">
      Copiar datos para soporte
    </button>
    <div class="form-text">Esto copia al portapapeles un texto listo para pegar en WhatsApp o correo.</div>
  </div>
</div>


          </div>
        </div>
      </div>
    </div>
  </form>

</div>
<?php $__env->stopSection(); ?>
<?php $__env->startPush('scripts'); ?>
<script>
(function () {
  const btn = document.getElementById('btnCopyTenantLocation');
  if (!btn) return;

  btn.addEventListener('click', async () => {
    const text =
`Solicitud de cambio de coordenadas (TENANT)
Tenant ID: <?php echo e($tenant->id); ?>

Central: <?php echo e(addslashes($tenant->name)); ?>

Ciudad: <?php echo e(addslashes($tenant->public_city ?? '-')); ?>

Lat/Lng actual: <?php echo e($tenant->latitud ?? '-'); ?>, <?php echo e($tenant->longitud ?? '-'); ?>

Radio actual (km): <?php echo e($tenant->coverage_radius_km ?? '-'); ?>


Motivo del cambio:
(NOTA: escribe aquí el motivo y las coordenadas nuevas solicitadas)`;

    try {
      await navigator.clipboard.writeText(text);
      btn.textContent = 'Copiado';
      setTimeout(() => btn.textContent = 'Copiar datos para soporte', 1200);
    } catch (e) {
      // fallback muy simple sin dependencias
      const ta = document.createElement('textarea');
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
      btn.textContent = 'Copiado';
      setTimeout(() => btn.textContent = 'Copiar datos para soporte', 1200);
    }
  });
})();
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/admin/tenant/edit.blade.php ENDPATH**/ ?>