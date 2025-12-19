<?php
  // $sector puede ser null (create) o un stdClass (edit)
  $geomValue = '';
  if (!empty($sector?->area)) {
      $geomValue = is_string($sector->area) ? $sector->area : json_encode($sector->area, JSON_UNESCAPED_UNICODE);
  } else {
      $geomValue = old('area', '');
  }
  // Fallback si no se pasa $geojsonUrl desde el include
  $geojsonUrl = $geojsonUrl ?? url('admin/sectores.geojson');
?>

<?php $__env->startPush('styles'); ?>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css"/>
  <style>
    .card-map { border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,.08); }
    #map-sector { height: 78vh; border-radius: 12px; border: 1px solid #e5e7eb; }
    .pill-actions .nav-link { border-radius: 999px; padding: .35rem .9rem; }
    .pill-actions .nav-link.active { background: #0d6efd; color:#fff; }
  </style>
<?php $__env->stopPush(); ?>

<div class="card card-map mb-4">
  <div class="card-body">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
      <div>
        <h5 class="mb-0">Área del sector</h5>
        <small class="text-muted">Dibuja el polígono. Verás otros sectores en gris para orientarte.</small>
      </div>
      <ul class="nav nav-pills pill-actions">
        <li class="nav-item"><a id="btnDraw" class="nav-link active" href="#">Dibujar</a></li>
        <li class="nav-item"><a id="btnEdit" class="nav-link" href="#">Editar</a></li>
        <li class="nav-item"><a id="btnDelete" class="nav-link" href="#">Borrar</a></li>
        <li class="nav-item"><a id="btnFit" class="nav-link" href="#">Ajustar vista</a></li>
      </ul>
    </div>

    <div class="d-flex align-items-center justify-content-between mb-2">
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" role="switch" id="toggleExisting" checked>
        <label class="form-check-label" for="toggleExisting">Ver sectores existentes</label>
      </div>
      <div class="text-muted small" id="shapeInfo">—</div>
    </div>

    <div id="map-sector" class="w-100"></div>

    <textarea name="area" id="area" class="form-control mt-3" rows="5" readonly
              placeholder="GeoJSON del polígono" required><?php echo e($geomValue); ?></textarea>
    <small class="text-muted">Se guarda como GeoJSON (Feature). Se actualiza al dibujar/editar.</small>
  </div>
</div>

<?php $__env->startPush('scripts'); ?>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
  <script>
  (function(){
    const map = L.map('map-sector', { zoomControl: true, preferCanvas: true });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 20, minZoom: 2}).addTo(map);

   map.createPane('existingPane');
map.getPane('existingPane').style.zIndex = 350; // debajo del default (400)

const existingLayer = L.geoJSON(null, {
  pane: 'existingPane',
  interactive: false, // no capta clics; la edición del tuyo queda libre
  style: function (f) {
    return {
      color: '#d6336c',       // borde rojizo
      weight: 2,
      dashArray: '4,3',       // opcional: guiones para diferenciar
      fill: true,
      fillColor: '#f8d7da',   // rojo claro de fondo
      fillOpacity: 0.35
    };
  },
  onEachFeature: (f, layer) => {
    const name = (f.properties && f.properties.nombre) ? f.properties.nombre : 'Sector';
    const id   = (f.properties && typeof f.properties.id !== 'undefined') ? f.properties.id : '';
    layer.bindTooltip(name + (id ? (' #' + id) : ''), {sticky:true});
  }
});

    const drawnLayer = new L.FeatureGroup();
    map.addLayer(drawnLayer);

    // Toolbar de Leaflet.draw (la usamos pero sin botones Save/Cancel; controlamos con pills)
    const drawControl = new L.Control.Draw({
      edit: { featureGroup: drawnLayer, edit: true, remove: false },
      draw: {
        polygon: { allowIntersection:false, showArea:true, metric:true,
                   shapeOptions:{ color:'#198754', weight:3, fillOpacity:.3 } },
        marker:false, circle:false, rectangle:false, polyline:false, circlemarker:false
      }
    });
    map.addControl(drawControl);

    const txt = document.getElementById('area');

    // --- helpers ---
    function featureFromLayer(l){
      const gj = l.toGeoJSON();
      return (gj.type === 'Feature') ? gj : { type:'Feature', properties:{}, geometry: gj };
    }
    function serialize(){
      let f = null;
      drawnLayer.eachLayer(l => { f = featureFromLayer(l); });
      txt.value = f ? JSON.stringify(f) : '';
    }
    function updateInfo(){
      const info = document.getElementById('shapeInfo');
      let areaM2 = 0, vertices = 0, b = null;
      drawnLayer.eachLayer(l => {
        try {
          const gj = l.toGeoJSON();
          const geom = (gj.type === 'Feature') ? gj.geometry : gj;
          if (geom && geom.type === 'Polygon') {
            vertices = geom.coordinates[0]?.length || 0;
          }
          if (l.getLatLngs) areaM2 = L.GeometryUtil?.geodesicArea ? L.GeometryUtil.geodesicArea(l.getLatLngs()[0]) : 0;
          if (l.getBounds)  b = l.getBounds();
        } catch(e){}
      });
      const ha = areaM2/10000;
      info.textContent = vertices ? `Vértices: ${vertices} • Área aprox: ${ha.toFixed(2)} ha` : '—';
      
    }
    function attachLayerEditHandlers(layer){
      // ¡CLAVE! Se dispara al soltar un vértice (sin necesidad de botón Save)
      layer.on('edit', () => { serialize(); updateInfo(); });
    }

    // Cargar existentes (fondo)
    const existingToggle = document.getElementById('toggleExisting');
    function loadExisting(){
      fetch(<?php echo json_encode($geojsonUrl, 15, 512) ?>)
        .then(r => r.json())
        .then(fc => {
          existingLayer.clearLayers();
          existingLayer.addData(fc);
          if (existingToggle.checked) existingLayer.addTo(map);
          fitInitial();
        })
        .catch(()=>{});
    }
    existingToggle.addEventListener('change', ()=>{
      if (existingToggle.checked) existingLayer.addTo(map); else map.removeLayer(existingLayer);
    });

    // Precarga en edición
    try {
      if (txt.value && txt.value.trim()) {
        const parsed = JSON.parse(txt.value);
        const geom = (parsed.type === 'Feature') ? parsed.geometry : parsed;
        if (geom) {
          const lyr = L.geoJSON(geom, {style:{color:'#198754', weight:3, fillOpacity:.3}});
          lyr.eachLayer(l => { drawnLayer.addLayer(l); attachLayerEditHandlers(l); });
          serialize(); // normaliza a Feature
        }
      }
    } catch(e) {}

    // Eventos de Leaflet.draw
    map.on(L.Draw.Event.CREATED, (e) => {
      drawnLayer.clearLayers();
      drawnLayer.addLayer(e.layer);
      attachLayerEditHandlers(e.layer);
      serialize();
      updateInfo();
    });

    // Estos dos pueden no dispararse si no usas botones nativos, pero los dejamos por si acaso
    map.on(L.Draw.Event.EDITED,  () => { serialize(); updateInfo(); });
    map.on('draw:editvertex',     () => { serialize(); updateInfo(); }); // al mover/insertar vértice

    // Pills
    document.getElementById('btnDraw').addEventListener('click', (ev)=>{
      ev.preventDefault();
      new L.Draw.Polygon(map, drawControl.options.draw.polygon).enable();
      activate(ev.target);
    });
    document.getElementById('btnEdit').addEventListener('click', (ev)=>{
      ev.preventDefault();
      new L.EditToolbar.Edit(map, { featureGroup: drawnLayer }).enable();
      activate(ev.target);
      // No hay "save": confiamos en los eventos 'edit' de capa / 'draw:editvertex'
    });
    document.getElementById('btnDelete').addEventListener('click', (ev)=>{
      ev.preventDefault();
      drawnLayer.clearLayers();
      serialize(); updateInfo();
      activate(ev.target);
    });
    document.getElementById('btnFit').addEventListener('click', (ev)=>{
      ev.preventDefault();
      fitInitial(true);
    });

    function activate(a){
      document.querySelectorAll('.pill-actions .nav-link').forEach(x=>x.classList.remove('active'));
      a.classList.add('active');
    }
    function fitInitial(force=false){
      const b = boundsOf(drawnLayer); if (b) return map.fitBounds(b, {padding:[24,24]});
      const be = boundsOf(existingLayer); if (be) return map.fitBounds(be, {padding:[24,24]});
      if (force) return map.setView([19.4326,-99.1332], 12);
    }
    function boundsOf(layer){ try { const b = layer.getBounds(); if (b?.isValid()) return b; } catch(e) {} return null; }

    // Fallback de área geodésica
    if (!L.GeometryUtil) L.GeometryUtil = {};
    if (!L.GeometryUtil.geodesicArea) {
      L.GeometryUtil.geodesicArea = function(latLngs){
        if (!latLngs?.length) return 0;
        const R = 6378137, pts = latLngs.map(ll => [ll.lng*Math.PI/180, ll.lat*Math.PI/180]);
        let sum = 0;
        for (let i=0,j=pts.length-1;i<pts.length;j=i++){
          const p1=pts[i], p2=pts[j];
          sum += (p2[0]-p1[0]) * (2 + Math.sin(p1[1]) + Math.sin(p2[1]));
        }
        return Math.abs(sum*R*R/2.0);
      }
    }

    loadExisting();
    setTimeout(()=>fitInitial(true), 400);
  })();
  </script>
<?php $__env->stopPush(); ?>
<?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/admin/sectores/_form.blade.php ENDPATH**/ ?>