<?php
/** @var \Illuminate\Support\Collection $sectores */
?>

<?php $__env->startSection('title','Crear paradero'); ?>

<?php $__env->startPush('styles'); ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>
  #map-pick { height: 420px; border-radius: .5rem; }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div class="container-fluid px-0">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="mb-0">Nuevo paradero</h3>
    <a href="<?php echo e(route('taxistands.index')); ?>" class="btn btn-outline-secondary">
      <i data-feather="arrow-left"></i> Volver
    </a>
  </div>

  <?php if($errors->any()): ?>
    <div class="alert alert-danger">
      <div class="fw-bold mb-1">Corrige los siguientes campos:</div>
      <ul class="mb-0">
        <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($e); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="POST" action="<?php echo e(route('taxistands.store')); ?>" autocomplete="off" class="card">
    <?php echo csrf_field(); ?>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Nombre *</label>
          <input type="text" name="nombre" class="form-control" value="<?php echo e(old('nombre')); ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Sector *</label>
          <select name="sector_id" class="form-select" required>
            <option value="">-- Selecciona --</option>
            <?php $__currentLoopData = $sectores; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sec): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($sec->id); ?>" <?php if(old('sector_id')==$sec->id): echo 'selected'; endif; ?>><?php echo e($sec->nombre); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label">Latitud *</label>
          <input id="lat" type="number" step="any" name="latitud" class="form-control"
                 value="<?php echo e(old('latitud', 19.1738)); ?>" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Longitud *</label>
          <input id="lng" type="number" step="any" name="longitud" class="form-control"
                 value="<?php echo e(old('longitud', -96.1342)); ?>" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Capacidad</label>
          <input type="number" name="capacidad" class="form-control" min="0" value="<?php echo e(old('capacidad', 0)); ?>">
        </div>
      </div>

      <hr>
      <div class="mb-2 d-flex align-items-center gap-2">
        <button type="button" id="btnPick" class="btn btn-sm btn-outline-primary">
          <i data-feather="map-pin"></i> Elegir en mapa
        </button>
        <span class="text-muted small">Click en el mapa para fijar coordenadas.</span>
      </div>
      <div id="map-pick" class="mb-3"></div>
    </div>

    <div class="card-footer d-flex justify-content-end gap-2">
      <a href="<?php echo e(route('taxistands.index')); ?>" class="btn btn-outline-secondary">Cancelar</a>
      <button class="btn btn-primary" type="submit">
        <i data-feather="save"></i> Guardar
      </button>
    </div>
  </form>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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
});
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/admin/taxistands/create.blade.php ENDPATH**/ ?>