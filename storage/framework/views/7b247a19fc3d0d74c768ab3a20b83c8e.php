
<?php $__env->startSection('title','Reporte de viajes'); ?>

<?php $__env->startPush('styles'); ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
  .map-mini { height: 120px; border-radius: .75rem; }
  .kpi .card { min-height: 110px; }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div class="card shadow-sm border-0 mb-3">
  <div class="card-body">
    <form class="row g-2" method="GET" action="<?php echo e(route('admin.reports.rides')); ?>">
      <div class="col-sm-3">
        <label class="form-label">Desde</label>
        <input type="date" name="from" class="form-control" value="<?php echo e($from); ?>">
      </div>
      <div class="col-sm-3">
        <label class="form-label">Hasta</label>
        <input type="date" name="to" class="form-control" value="<?php echo e($to); ?>">
      </div>
      <div class="col-sm-3">
        <label class="form-label">Estado</label>
        <select name="status" class="form-select">
          <option value="">— Todos —</option>
          <?php $__currentLoopData = ['finished','canceled','offered','accepted','arrived','onboard']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($s); ?>" <?php if($status===$s): echo 'selected'; endif; ?>><?php echo e(ucfirst($s)); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
      </div>
      <div class="col-sm-3 d-flex align-items-end gap-2">
        <button class="btn btn-primary shadow">Filtrar</button>
        <a class="btn btn-outline-secondary" href="<?php echo e(route('admin.reports.rides.csv', request()->query())); ?>">CSV</a>
      </div>
    </form>
  </div>
</div>

<div class="row g-3 kpi">
  <div class="col-md-3">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <div class="text-muted small">Total</div>
        <div class="fs-3 fw-bold"><?php echo e($totals['total']); ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <div class="text-muted small">Finalizados</div>
        <div class="fs-3 fw-bold"><?php echo e($totals['finished']); ?></div>
        <div class="small text-muted"> Cobro registrado en <?php echo e($totals['collect_rate_pct'] ?? 0); ?>%</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <div class="text-muted small">Cancelados</div>
        <div class="fs-3 fw-bold"><?php echo e($totals['canceled']); ?></div>
        <div class="small text-muted"><?php echo e($totals['cancel_rate']); ?>% cancelación</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <div class="text-muted small">Ingresos</div>
        <div class="small text-muted">Cotizado (suma / prom)</div>
<div class="fw-semibold">
  <?php echo e(number_format($totals['sum_quote'] ?? 0,2)); ?>

  <span class="text-muted">/</span>
  <?php echo e(number_format($totals['avg_quote'] ?? 0,2)); ?>

</div>

<div class="small text-muted mt-2">Cobrado (suma / prom)</div>
<div class="fw-semibold">
  <?php echo e(number_format($totals['sum_collected'] ?? 0,2)); ?>

  <span class="text-muted">/</span>
  <?php echo e(number_format($totals['avg_collected'] ?? 0,2)); ?>

</div>

<div class="small mt-2 <?php echo e(($totals['delta_sum'] ?? 0)>=0 ? 'text-success' : 'text-danger'); ?>">
  Δ suma: <?php echo e(number_format($totals['delta_sum'] ?? 0,2)); ?>



        </div>
      </div>
    </div>
  </div>
</div>


<div class="card shadow-sm border-0 mt-3">
  <div class="card-body">
    <div class="table-responsive">
     <table class="table align-middle">
  <thead>
    <tr>
      <th>ID</th>
      <th>Estado</th>
      <th>Fecha</th>
      <th>Origen → Destino</th>
      <th>Duración</th>
      <th>Distancia</th>
      <th>Quote</th>     
      <th>Cobrado</th>   
      <th></th>
    </tr>
  </thead>
  <tbody>
  <?php $__empty_1 = true; $__currentLoopData = $rides; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <tr>
      <td>#<?php echo e($r->id); ?></td>
      <td>
        <?php if($r->status==='finished'): ?>
          <span class="badge text-bg-success">finished</span>
        <?php elseif($r->status==='canceled'): ?>
          <span class="badge text-bg-danger">canceled</span>
        <?php else: ?>
          <span class="badge text-bg-secondary"><?php echo e($r->status); ?></span>
        <?php endif; ?>
      </td>
      <td>
        <div class="small"><?php echo e($r->requested_at?->format('Y-m-d H:i')); ?></div>
        <?php if($r->scheduled_for): ?>
          <div class="small text-muted">Prog: <?php echo e($r->scheduled_for->format('Y-m-d H:i')); ?></div>
        <?php endif; ?>
      </td>
      <td>
        <div class="small fw-semibold"><?php echo e($r->origin_label ?? 'Origen'); ?></div>
        <div class="small text-muted">→ <?php echo e($r->dest_label ?? 'Destino'); ?></div>
        <div class="small text-muted">
          <?php echo e($r->passenger_name ?? '—'); ?>

          <?php if($r->passenger_phone): ?> · <?php echo e($r->passenger_phone); ?> <?php endif; ?>
        </div>
        <div class="small d-flex flex-wrap gap-1">
          <?php if($r->scheduled_for): ?>
            <span class="badge rounded-pill text-bg-info">Programado</span>
          <?php endif; ?>
          <span class="badge rounded-pill text-bg-light border"><?php echo e($r->requested_channel ?? '—'); ?></span>
          <?php if($r->vehicle_economico): ?>
            <span class="badge rounded-pill text-bg-dark">Eco <?php echo e($r->vehicle_economico); ?></span>
          <?php endif; ?>
        </div>
        <?php if($r->driver_name): ?>
          <div class="small text-muted mt-1">
            Conductor: <?php echo e($r->driver_name); ?>

            <?php if($r->driver_phone): ?> · <?php echo e($r->driver_phone); ?> <?php endif; ?>
          </div>
        <?php endif; ?>
      </td>
      <td class="small"><?php echo e($r->duration_s ? gmdate('H:i:s', $r->duration_s) : '—'); ?></td>
      <td class="small"><?php echo e($r->distance_m ? number_format($r->distance_m/1000,2) .' km' : '—'); ?></td>

      
      <td class="small">
        <?php echo e(isset($r->quoted_amount) ? number_format($r->quoted_amount,2).' '.$r->currency : '—'); ?>

      </td>

      
      <td class="small">
        <?php echo e(isset($r->total_amount) && $r->total_amount !== null ? number_format($r->total_amount,2).' '.$r->currency : '—'); ?>

      </td>

      <td>
        <a href="<?php echo e(route('admin.reports.rides.show',$r->id)); ?>" class="btn btn-sm btn-outline-primary">Ver</a>
      </td>
    </tr>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <tr><td colspan="9" class="text-center text-muted">Sin resultados</td></tr>
  <?php endif; ?>
  </tbody>
</table>
    </div>

   <div class="d-flex justify-content-end">
  <?php echo e($rides->onEachSide(1)->links('vendor.pagination.bootstrap-5')); ?>

</div>
  </div>
</div>
<?php $__env->stopSection(); ?>
<?php $__env->startPush('styles'); ?>
<style>
  .table td, .table th { vertical-align: middle; }
  .table .text-truncate { max-width: 260px; }
  .kpi .card { min-height: 110px; }
  thead.table-light { position: sticky; top: 0; }
  .pagination { margin: .25rem 0 0; }
  .pagination .page-link { padding: .25rem .6rem; line-height: 1.2; }
  .pagination .page-item:first-child .page-link,
  .pagination .page-item:last-child  .page-link { border-radius: .5rem; }
</style>
<?php $__env->stopPush(); ?>
<?php $__env->startPush('scripts'); ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// Decodificador
function decodePolyline(str){let i=0,lat=0,lng=0,c=[];while(i<str.length){let b,sh=0,re=0;do{b=str.charCodeAt(i++)-63;re|=(b&0x1f)<<sh;sh+=5}while(b>=0x20);let dlat=(re&1)?~(re>>1):(re>>1);lat+=dlat;sh=0;re=0;do{b=str.charCodeAt(i++)-63;re|=(b&0x1f)<<sh;sh+=5}while(b>=0x20);let dlng=(re&1)?~(re>>1):(re>>1);lng+=dlng;c.push([lat/1e5,lng/1e5])}return c}
function toLatLngPair(list){const la=parseFloat(list?.[0]), ln=parseFloat(list?.[1]);return (Number.isFinite(la)&&Number.isFinite(ln))?[la,ln]:null}
async function fetchPolylineIfMissing(origin,dest){
  try{
    const r=await fetch("<?php echo e(route('api.geo.route')); ?>",{
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'<?php echo e(csrf_token()); ?>'},
      body:JSON.stringify({from:{lat:origin[0],lng:origin[1]},to:{lat:dest[0],lng:dest[1]},mode:'driving'})
    });
    const d=await r.json(); return d&&d.polyline?d.polyline:null;
  }catch(e){return null}
}

document.querySelectorAll('.map-mini').forEach(async function(el){
  const origin = toLatLngPair((el.dataset.origin||'').split(','));
  const dest   = toLatLngPair((el.dataset.dest  ||'').split(','));
  let poly64   = el.dataset.polyline || '';

  const map = L.map(el,{zoomControl:false,attributionControl:false,dragging:false,scrollWheelZoom:false});
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19}).addTo(map);

  if(!poly64 && origin && dest){
    const poly = await fetchPolylineIfMissing(origin,dest);
    if(poly) poly64 = btoa(poly);
  }

  let bounds = null;

  // Dibujar polyline si existe
  if(poly64){
    const coords = decodePolyline(atob(poly64));
    if(coords.length>1){
      const line = L.polyline(coords,{weight:3}).addTo(map);
      bounds = line.getBounds();
    }
  }

  // 🔴 Agregar SIEMPRE los pines (aunque haya polyline)
  if(origin){
    const o = L.circleMarker(origin,{radius:4,weight:2,fillOpacity:1});
    o.addTo(map);
    bounds = bounds ? bounds.extend(L.latLngBounds([origin,origin])) : L.latLngBounds([origin,origin]);
  }
  if(dest){
    const d = L.circleMarker(dest,{radius:4,weight:2,fillOpacity:1});
    d.addTo(map);
    bounds = bounds ? bounds.extend(L.latLngBounds([dest,dest])) : L.latLngBounds([dest,dest]);
  }

  // Fallback: recta O→D si no hubo polyline ni bounds
  if(!bounds && origin && dest){
    const group = L.layerGroup();
    L.circleMarker(origin,{radius:4,weight:2,fillOpacity:1}).addTo(group);
    L.circleMarker(dest,{radius:4,weight:2,fillOpacity:1}).addTo(group);
    L.polyline([origin,dest],{weight:3}).addTo(group);
    group.addTo(map);
    bounds = L.latLngBounds([origin,dest]);
  }

  if(bounds) map.fitBounds(bounds.pad(0.3));
});
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/admin/reports/rides/index.blade.php ENDPATH**/ ?>