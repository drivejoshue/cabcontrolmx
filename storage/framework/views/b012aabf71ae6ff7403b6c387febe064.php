

<?php $__env->startSection('title','Primeros pasos'); ?>

<?php $__env->startSection('content'); ?>
<?php
  $isReady = !empty($tenant->onboarding_done_at)
    && !is_null($tenant->latitud)
    && !is_null($tenant->longitud)
    && !is_null($tenant->coverage_radius_km)
    && (float)$tenant->coverage_radius_km > 0;
?>

<div class="d-flex align-items-start justify-content-between mb-3">
  <div>
    <h1 class="h3 mb-1">Bienvenido, <?php echo e($tenant->name); ?></h1>
    <div class="text-muted">
      Define la ciudad y el centro del mapa. Esto configura tu despacho y cobertura inicial.
    </div>
    <div class="small mt-1">
      <span class="text-muted">Ciudad guardada:</span>
      <strong id="uiSavedCity"><?php echo e($tenant->public_city ?: '—'); ?></strong>
      <?php if($isReady): ?>
        <span class="badge bg-success ms-2">Listo</span>
      <?php else: ?>
        <span class="badge bg-warning text-dark ms-2">Pendiente</span>
      <?php endif; ?>
    </div>
  </div>

  <div class="d-flex gap-2">
    <a href="<?php echo e(route('admin.dashboard')); ?>"
       class="btn btn-primary <?php echo e($isReady ? '' : 'disabled'); ?>"
       <?php if(!$isReady): ?> aria-disabled="true" tabindex="-1" <?php endif; ?>>
      Continuar
    </a>
  </div>
</div>

<?php if(session('status')): ?>
  <div class="alert alert-success"><?php echo e(session('status')); ?></div>
<?php endif; ?>

<?php if($errors->any()): ?>
  <div class="alert alert-danger">
    <div class="fw-semibold mb-1">Revisa los campos:</div>
    <ul class="mb-0">
      <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <li><?php echo e($e); ?></li>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </ul>
  </div>
<?php endif; ?>

<div class="row g-3">

  
  <div class="col-12 col-xxl-8">
    <div class="card">
      <div class="card-body">

        <div class="d-flex align-items-start justify-content-between mb-2">
          <div>
            <h5 class="card-title mb-0">1) Ubicación de tu central</h5>
            <div class="small text-muted">Mueve el mapa; el pin queda fijo al centro.</div>
          </div>

          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnMyLocation">
              Usar mi ubicación
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnResetCenter">
              Reset
            </button>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-12 col-lg-4">
            <form method="POST" action="<?php echo e(route('admin.onboarding.location')); ?>" id="frmLocation">
              <?php echo csrf_field(); ?>

              <div class="mb-3">
                <label class="form-label">Ciudad</label>
                <select class="form-select" id="citySelect">
                  <option value="">Escribe para buscar…</option>
                </select>
                <div class="form-text">Selecciona una ciudad y se centrará el mapa.</div>
              </div>

              <input type="hidden" name="public_city" id="public_city" value="<?php echo e(old('public_city', $tenant->public_city)); ?>">

              <div class="mb-2">
                <label class="form-label d-flex justify-content-between">
                  <span>Radio de cobertura</span>
                  <span class="text-muted small" id="radiusLabel"></span>
                </label>

                <input type="range"
                       class="form-range"
                       min="1" max="100" step="1"
                       id="coverageRadius"
                       name="coverage_radius_km"
                       value="<?php echo e(old('coverage_radius_km', $tenant->coverage_radius_km ?? 8)); ?>">

                <?php $__errorArgs = ['coverage_radius_km'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                  <div class="text-danger small"><?php echo e($message); ?></div>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

                <div class="form-text">En km. El círculo representa la cobertura inicial.</div>
              </div>

              <input type="hidden" name="latitud" id="latitud" value="<?php echo e(old('latitud', $tenant->latitud)); ?>">
              <input type="hidden" name="longitud" id="longitud" value="<?php echo e(old('longitud', $tenant->longitud)); ?>">

              <div class="small text-muted mb-3">
                <div><strong>Lat:</strong> <span id="latText">-</span></div>
                <div><strong>Lng:</strong> <span id="lngText">-</span></div>
              </div>

              <button class="btn btn-primary w-100" type="submit" id="btnSaveLocation">
                Guardar y habilitar panel
              </button>

              <?php $__errorArgs = ['center'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                <div class="text-danger small mt-2"><?php echo e($message); ?></div>
              <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </form>

            <hr class="my-4">

            <div class="small text-muted">
              <div class="fw-semibold mb-1">Notas</div>
              <ul class="mb-0">
                <li>Luego podrás crear sectores (polígonos) si delimitas zonas.</li>
                <li>Registra conductores/vehículos para operar en vivo.</li>
                <li>Configura bases/paraderos desde el despacho.</li>
              </ul>
            </div>
          </div>

          <div class="col-12 col-lg-8">
            <div id="onboardingMapWrap">
              <div id="onboardingMap"></div>

              
              <div class="cc-centerpin" aria-hidden="true">
                <div class="cc-pin-head"></div>
                <div class="cc-pin-dot"></div>
              </div>

              
              <div class="cc-map-hud small text-muted">
                Centro: <span id="hudLat">-</span>, <span id="hudLng">-</span>
              </div>
            </div>

            <div class="small text-muted mt-2">
              Tip: mueve el mapa; el pin se queda fijo. Guardamos coordenadas desde el centro.
            </div>
          </div>

        </div>

      </div>
    </div>
  </div>

  
  <div class="col-12 col-xxl-4">
    <div class="card">
      <div class="card-body">

        <h5 class="card-title mb-2">2) Accesos rápidos</h5>
        <div class="text-muted mb-3">Se habilitan después de guardar ubicación.</div>

        <div class="d-grid gap-2 mb-3">
          <?php $canGo = $isReady; ?>

          <?php if(isset($opsUrl) && $opsUrl): ?>
            <a href="<?php echo e($opsUrl); ?>"
               class="btn btn-outline-primary <?php echo e($canGo ? '' : 'disabled'); ?>"
               <?php if(!$canGo): ?> aria-disabled="true" tabindex="-1" <?php endif; ?>>
              Abrir Despacho
            </a>
          <?php else: ?>
            <button class="btn btn-outline-secondary" disabled>Despacho (ruta no configurada)</button>
          <?php endif; ?>

          <a href="<?php echo e(route('admin.tenant.edit')); ?>"
             class="btn btn-outline-secondary <?php echo e($canGo ? '' : 'disabled'); ?>"
             <?php if(!$canGo): ?> aria-disabled="true" tabindex="-1" <?php endif; ?>>
            Ir a "Mi central"
          </a>
        </div>

        <div class="row g-3">
          <div class="col-12">
            <div class="border rounded p-3">
              <div class="fw-semibold mb-1">App Conductor</div>
              <?php if(!empty($driverAppUrl)): ?>
                <div class="small text-muted mb-2">
                  <a href="<?php echo e($driverAppUrl); ?>" target="_blank" rel="noopener"><?php echo e($driverAppUrl); ?></a>
                </div>
              <?php else: ?>
                <div class="small text-muted">Define <code>DRIVER_APP_URL</code> en <code>.env</code>.</div>
              <?php endif; ?>
            </div>
          </div>

          <div class="col-12">
            <div class="border rounded p-3">
              <div class="fw-semibold mb-1">App Pasajero</div>
              <?php if(!empty($passengerAppUrl)): ?>
                <div class="small text-muted mb-2">
                  <a href="<?php echo e($passengerAppUrl); ?>" target="_blank" rel="noopener"><?php echo e($passengerAppUrl); ?></a>
                </div>
              <?php else: ?>
                <div class="small text-muted">Define <code>PASSENGER_APP_URL</code> en <code>.env</code>.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="alert alert-info mt-3 mb-0">
          <strong>Nota:</strong> si todavía no registras conductores/vehículos, es normal que el dashboard esté vacío.
        </div>

      </div>
    </div>
  </div>

</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('styles'); ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
      crossorigin=""/>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css">

<style>
  #onboardingMapWrap{
    position: relative;
    height: min(74vh, 820px);
    min-height: 560px;
    border-radius: 12px;
    overflow: hidden;
    background: #f6f6f6;
  }
  #onboardingMap{ height: 100%; width: 100%; }

  /* PIN fijo al centro: IMPORTANTÍSIMO (fuera del DOM de Leaflet panes) */
  .cc-centerpin{
    position:absolute;
    left:50%;
    top:50%;
    width:56px;
    height:56px;
    transform: translate(-50%, -100%); /* punta cae al centro */
    z-index: 2000; /* arriba de Leaflet */
    pointer-events: none;
    filter: drop-shadow(0 10px 14px rgba(0,0,0,.28));
  }
  .cc-pin-head{
    width:44px;height:44px;
    background:#0d6efd;
    border-radius: 50% 50% 50% 0;
    transform: rotate(-45deg);
    position:absolute;
    left:6px; top:5px;
  }
  .cc-pin-dot{
    width:16px;height:16px;
    background:#fff;border-radius:50%;
    position:absolute;
    left:20px; top:17px;
  }

  .cc-map-hud{
    position:absolute;
    right:10px;
    bottom:10px;
    z-index: 1500;
    background: rgba(255,255,255,.92);
    border: 1px solid rgba(0,0,0,.08);
    border-radius: 10px;
    padding: 6px 10px;
  }
  [data-theme="dark"] .cc-map-hud{
    background: rgba(17,24,39,.78);
    border-color: rgba(255,255,255,.10);
  }

  .leaflet-control-attribution{ font-size: 11px; }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startPush('scripts'); ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""></script>

<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

<script>
(function () {
  function boot() {
    console.log('[onboarding] boot start');

    const mapEl = document.getElementById('onboardingMap');
    const wrapEl = document.getElementById('onboardingMapWrap');
    console.log('[onboarding] mapEl?', !!mapEl, 'wrap?', !!wrapEl, 'Leaflet?', !!window.L);

    if (!mapEl || !wrapEl) return console.error('[onboarding] Falta contenedor del mapa');
    if (!window.L) return console.error('[onboarding] Leaflet no cargó');

    // Inputs
    const latInp = document.getElementById('latitud');
    const lngInp = document.getElementById('longitud');
    const latText = document.getElementById('latText');
    const lngText = document.getElementById('lngText');
    const hudLat  = document.getElementById('hudLat');
    const hudLng  = document.getElementById('hudLng');

    const radiusInput = document.getElementById('coverageRadius');
    const radiusLabel = document.getElementById('radiusLabel');

    const publicCityInp = document.getElementById('public_city');
    const uiSavedCity   = document.getElementById('uiSavedCity');

    // Defaults
    const defaultLat = <?php echo e((float)($tenant->latitud ?? 19.4326)); ?>;
    const defaultLng = <?php echo e((float)($tenant->longitud ?? -99.1332)); ?>;

    const lat0 = Number(latInp?.value) || defaultLat;
    const lng0 = Number(lngInp?.value) || defaultLng;

    // Mapa
    const map = L.map(mapEl, { zoomControl: true }).setView([lat0, lng0], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
      maxZoom: 19
    }).addTo(map);

    // Escala para “ver” km reales (ayuda a percepción del radio)
    L.control.scale({ imperial: false, metric: true, position: 'bottomleft' }).addTo(map);

    // Círculo (centro = centro del mapa)
    const circle = L.circle([lat0, lng0], {
      radius: (Number(radiusInput?.value) || 8) * 1000,
      color: '#0d6efd',
      fillColor: '#0d6efd',
      fillOpacity: 0.18,
      weight: 2
    }).addTo(map);

    let lastCenterSyncAt = 0;

    function syncCenterToInputs(force = false) {
      // throttle suave para no spamear UI/logs
      const now = Date.now();
      if (!force && (now - lastCenterSyncAt) < 120) return;
      lastCenterSyncAt = now;

      const c = map.getCenter();
      const lat = c.lat;
      const lng = c.lng;

      if (latInp) latInp.value = Number(lat).toFixed(7);
      if (lngInp) lngInp.value = Number(lng).toFixed(7);
      if (latText) latText.textContent = latInp.value;
      if (lngText) lngText.textContent = lngInp.value;

      if (hudLat) hudLat.textContent = latInp.value;
      if (hudLng) hudLng.textContent = lngInp.value;

      circle.setLatLng(c);
    }

    function setRadiusKm(km, opts = {}) {
      const rkm = Math.max(1, Number(km) || 8);
      if (radiusLabel) radiusLabel.textContent = rkm + ' km';
      circle.setRadius(rkm * 1000);

      // Opcional (recomendado): que el mapa “se adapte” al radio para percepción correcta.
      // Para radios grandes, se aleja; para chicos, se acerca un poco.
      if (opts.fit === true) {
        const b = circle.getBounds();
        map.fitBounds(b, { padding: [40, 40], maxZoom: 14 });
      }
    }

    map.on('move',   () => syncCenterToInputs(false));
    map.on('moveend',() => syncCenterToInputs(true));
    map.on('zoomend',() => syncCenterToInputs(true));

    // Init
    setRadiusKm(radiusInput?.value, { fit: true });
    syncCenterToInputs(true);

    radiusInput?.addEventListener('input', function () {
      // mientras arrastras: no fitBounds para que no “brinque”
      setRadiusKm(this.value, { fit: false });
    });
    radiusInput?.addEventListener('change', function () {
      // al soltar: sí ajusta bounds
      setRadiusKm(this.value, { fit: true });
    });

    document.getElementById('btnResetCenter')?.addEventListener('click', function () {
      map.setView([defaultLat, defaultLng], 13);
      setTimeout(() => syncCenterToInputs(true), 0);
    });

    document.getElementById('btnMyLocation')?.addEventListener('click', function () {
      if (!navigator.geolocation) return alert('Tu navegador no soporta geolocalización');

      const btn = this;
      btn.disabled = true;
      const oldTxt = btn.textContent;
      btn.textContent = 'Buscando...';

      navigator.geolocation.getCurrentPosition(
        function (pos) {
          const lat = pos.coords.latitude;
          const lng = pos.coords.longitude;
          map.setView([lat, lng], 14);
          setTimeout(() => syncCenterToInputs(true), 0);
          btn.disabled = false; btn.textContent = oldTxt;
        },
        function (err) {
          console.error('[onboarding] geolocation error:', err);
          btn.disabled = false; btn.textContent = oldTxt;
          alert('No se pudo obtener tu ubicación. Revisa permisos del navegador.');
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
      );
    });

    setTimeout(() => { map.invalidateSize(); console.log('[onboarding] invalidateSize'); }, 250);

    // ==== City select (Tom Select + AJAX) ====
    const citySelectEl = document.getElementById('citySelect');
    if (citySelectEl) {
      const ts = new TomSelect(citySelectEl, {
        valueField: 'label',
        labelField: 'label',
        searchField: ['label'],
        maxItems: 1,
        loadThrottle: 250,
        placeholder: 'Escribe una ciudad…',
        load: function(query, callback) {
          if (!query || query.length < 2) return callback();
          fetch(`<?php echo e(route('admin.onboarding.cities')); ?>?q=${encodeURIComponent(query)}`, {
            headers: { 'Accept': 'application/json' }
          })
          .then(r => r.json())
          .then(data => callback(data.items || []))
          .catch(() => callback());
        },
        onChange: function(val) {
          const item = this.options[val];
          if (!item) return;

          console.log('[onboarding] city pick:', item);

          // Guardar “Ciudad, Estado” al hidden input real
          if (publicCityInp) publicCityInp.value = item.label;
          if (uiSavedCity) uiSavedCity.textContent = item.label;

          // Centrar mapa (y sync)
          map.setView([item.lat, item.lng], 13);
          setTimeout(() => {
            syncCenterToInputs(true);
            // re-encuadrar radio actual para percepción correcta
            setRadiusKm(radiusInput?.value, { fit: true });
          }, 0);
        }
      });

      // Si ya hay city guardada, muéstrala al menos en UI
      if (publicCityInp?.value && uiSavedCity) uiSavedCity.textContent = publicCityInp.value;
    }

    console.log('[onboarding] OK');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.admin_onboarding', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/admin/onboarding/index.blade.php ENDPATH**/ ?>