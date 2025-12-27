<?php
/** @var object $stand */
/** @var \Illuminate\Support\Collection $sectores */
?>

<?php $__env->startSection('title','Editar paradero'); ?>

<?php $__env->startPush('styles'); ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>
  #map-pick { height: 420px; border-radius: .5rem; }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div class="container-fluid px-0">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="mb-0">Editar paradero #<?php echo e($stand->id); ?></h3>
    <a href="<?php echo e(route('taxistands.index')); ?>" class="btn btn-outline-secondary">
      <i data-feather="arrow-left"></i> Volver
    </a>
  </div>

  <?php if(session('ok')): ?> <div class="alert alert-success"><?php echo e(session('ok')); ?></div> <?php endif; ?>
  <?php if($errors->any()): ?>
    <div class="alert alert-danger">
      <div class="fw-bold mb-1">Corrige los siguientes campos:</div>
      <ul class="mb-0">
        <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($e); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="POST" action="<?php echo e(route('taxistands.update',$stand->id)); ?>" autocomplete="off" class="card">
    <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Nombre *</label>
          <input type="text" name="nombre" class="form-control" value="<?php echo e(old('nombre',$stand->nombre)); ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Sector *</label>
          <select name="sector_id" class="form-select" required>
            <?php $__currentLoopData = $sectores; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sec): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($sec->id); ?>" <?php if(old('sector_id',$stand->sector_id)==$sec->id): echo 'selected'; endif; ?>><?php echo e($sec->nombre); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label">Latitud *</label>
          <input id="lat" type="number" step="any" name="latitud" class="form-control"
                 value="<?php echo e(old('latitud',$stand->latitud)); ?>" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Longitud *</label>
          <input id="lng" type="number" step="any" name="longitud" class="form-control"
                 value="<?php echo e(old('longitud',$stand->longitud)); ?>" required>
        </div>
        <div class="col-md-2">
          <label class="form-label">Capacidad</label>
          <input type="number" name="capacidad" class="form-control" min="0" value="<?php echo e(old('capacidad',$stand->capacidad ?? 0)); ?>">
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="activo" id="activo"
                   value="1" <?php if(old('activo',$stand->activo ?? $stand->active ?? 1)==1): echo 'checked'; endif; ?>>
            <label class="form-check-label" for="activo">Activo</label>
          </div>
        </div>
      </div>

      <hr id="preview">

      <div class="mb-2 d-flex align-items-center gap-2">
        <button type="button" id="btnPick" class="btn btn-sm btn-outline-primary">
          <i data-feather="map-pin"></i> Elegir en mapa
        </button>
        <span class="text-muted small">Click en el mapa para fijar coordenadas.</span>
      </div>
      <div id="map-pick" class="mb-3"></div>

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Código</label>
          <input class="form-control" value="<?php echo e($stand->codigo); ?>" readonly>
          <small class="text-muted">Este código puede usarse para enrolar conductor en cola.</small>
        </div>
        <div class="col-md-6">
          <label class="form-label">QR Secret</label>
          <input class="form-control" value="<?php echo e($stand->qr_secret); ?>" readonly>
        </div>
      </div>

     <div class="mt-3">
    <label class="form-label">QR</label>
    <div id="qr" class="border rounded p-3 d-inline-block"></div>
    <small class="text-muted d-block">
        Apunta a: <code><?php echo e($stand->qr_secret); ?></code>
        (este es el valor que leerá la app del chofer).
    </small>
</div>


    <div class="card-footer d-flex justify-content-end gap-2">
      <a href="<?php echo e(route('taxistands.index')); ?>" class="btn btn-outline-secondary">Cancelar</a>
      <button class="btn btn-primary" type="submit">
        <i data-feather="save"></i> Guardar cambios
      </button>
    </div>
  </form>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const latEl = document.getElementById('lat');
  const lngEl = document.getElementById('lng');
  const map = L.map('map-pick').setView([Number(latEl.value), Number(lngEl.value)], 15);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'&copy; OSM'}).addTo(map);

  let m = L.marker([Number(latEl.value), Number(lngEl.value)], { draggable: true }).addTo(map);
  m.on('dragend', () => {
    const p = m.getLatLng();
    latEl.value = p.lat.toFixed(6);
    lngEl.value = p.lng.toFixed(6);
  });

  let picking = false;
  document.getElementById('btnPick')?.addEventListener('click', ()=> {
    picking = !picking;
    alert(picking ? 'Click en el mapa para fijar el punto.' : 'Selección por mapa desactivada.');
  });

  map.on('click', (ev) => {
    if (!picking) return;
    const {lat,lng} = ev.latlng;
    m.setLatLng(ev.latlng);
    latEl.value = lat.toFixed(6);
    lngEl.value = lng.toFixed(6);
    picking = false;
  });

  // QR
  const target = <?php echo json_encode($stand->qr_secret, 15, 512) ?>;
  const el = document.getElementById('qr');
  if (el) new QRCode(el, { text: target, width: 160, height: 160 });
});
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/admin/taxistands/edit.blade.php ENDPATH**/ ?>