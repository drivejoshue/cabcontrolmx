
<?php $__env->startSection('title','Crear QR Point'); ?>

<?php $__env->startPush('styles'); ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>
  #map-pick { height: 420px; border-radius: .5rem; }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div class="container-fluid px-0">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="mb-0">Nuevo QR Point</h3>
    <a href="<?php echo e(route('qr-points.index')); ?>" class="btn btn-outline-secondary">
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

  <form method="POST" action="<?php echo e(route('qr-points.store')); ?>" autocomplete="off" class="card">
    <?php echo csrf_field(); ?>
    <div class="card-body">

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Nombre *</label>
          <input type="text" name="name" class="form-control" value="<?php echo e(old('name')); ?>" required maxlength="120"
                 placeholder="Ej. Hotel Fiesta, Restaurante X">
        </div>

        <div class="col-md-6">
          <label class="form-label">Dirección (opcional)</label>
          <input type="text" name="address_text" class="form-control" value="<?php echo e(old('address_text')); ?>" maxlength="255"
                 placeholder="Ej. Av. X #123, Col. Centro">
        </div>

        <div class="col-md-4">
          <label class="form-label">Latitud *</label>
          <input id="lat" type="number" step="any" name="lat" class="form-control"
                 value="<?php echo e(old('lat', 19.1738)); ?>" required>
        </div>

        <div class="col-md-4">
          <label class="form-label">Longitud *</label>
          <input id="lng" type="number" step="any" name="lng" class="form-control"
                 value="<?php echo e(old('lng', -96.1342)); ?>" required>
        </div>

        <div class="col-md-4">
          <label class="form-label d-block">Estado</label>
          <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="active" value="1"
                   <?php echo e(old('active', 1) ? 'checked' : ''); ?>>
            <label class="form-check-label">Activo</label>
          </div>
        </div>
      </div>

      <hr>

      <div class="mb-2 d-flex align-items-center gap-2">
        <button type="button" id="btnPick" class="btn btn-sm btn-outline-primary">
          <i data-feather="map-pin"></i> Elegir en mapa
        </button>
        <span class="text-muted small">Click en el mapa para fijar coordenadas. Puedes arrastrar el marcador.</span>
      </div>

      <div id="map-pick" class="mb-3"></div>

      <div class="alert alert-info py-2 mb-0">
        <div class="fw-semibold mb-1">Nota</div>
        <div class="small">
          El <strong>código</strong> del QR se generará automáticamente al guardar.
        </div>
      </div>

    </div>

    <div class="card-footer d-flex justify-content-end gap-2">
      <a href="<?php echo e(route('qr-points.index')); ?>" class="btn btn-outline-secondary">Cancelar</a>
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

  const startLat = Number(latEl.value || 19.1738);
  const startLng = Number(lngEl.value || -96.1342);

  const map = L.map('map-pick').setView([startLat, startLng], 15);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OSM'
  }).addTo(map);

  let marker = L.marker([startLat, startLng], { draggable: true }).addTo(map);

  function syncInputs(lat, lng) {
    latEl.value = Number(lat).toFixed(6);
    lngEl.value = Number(lng).toFixed(6);
  }

  marker.on('dragend', () => {
    const p = marker.getLatLng();
    syncInputs(p.lat, p.lng);
  });

  // Si editan los inputs manualmente, reposicionar marker
  function moveMarkerFromInputs() {
    const lat = Number(latEl.value);
    const lng = Number(lngEl.value);
    if (!isFinite(lat) || !isFinite(lng)) return;
    marker.setLatLng([lat, lng]);
    map.panTo([lat, lng], { animate: true });
  }
  latEl.addEventListener('change', moveMarkerFromInputs);
  lngEl.addEventListener('change', moveMarkerFromInputs);

  let picking = false;

  const btnPick = document.getElementById('btnPick');
  btnPick?.addEventListener('click', () => {
    picking = !picking;
    btnPick.classList.toggle('btn-outline-primary', !picking);
    btnPick.classList.toggle('btn-primary', picking);
    btnPick.innerHTML = picking
      ? '<i data-feather="x-circle"></i> Cancelar selección'
      : '<i data-feather="map-pin"></i> Elegir en mapa';

    if (window.feather) window.feather.replace();

    // Evitar alert (más limpio)
  });

  map.on('click', (ev) => {
    if (!picking) return;
    const { lat, lng } = ev.latlng;
    marker.setLatLng(ev.latlng);
    syncInputs(lat, lng);
    picking = false;

    btnPick.classList.remove('btn-primary');
    btnPick.classList.add('btn-outline-primary');
    btnPick.innerHTML = '<i data-feather="map-pin"></i> Elegir en mapa';
    if (window.feather) window.feather.replace();
  });
});
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/admin/qr_points/create.blade.php ENDPATH**/ ?>