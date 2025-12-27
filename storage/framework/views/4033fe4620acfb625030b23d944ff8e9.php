

<?php $__env->startSection('title','Imprimir QR'); ?>

<?php $__env->startPush('styles'); ?>
<style>
  .print-wrap { max-width: 850px; margin: 0 auto; }
  .qr-card { border: 1px solid rgba(0,0,0,.12); border-radius: 16px; overflow: hidden; }
  [data-theme="dark"] .qr-card { border-color: rgba(255,255,255,.12); }

  .qr-head { padding: 16px 18px; border-bottom: 1px solid rgba(0,0,0,.08); }
  [data-theme="dark"] .qr-head { border-bottom-color: rgba(255,255,255,.10); }

  .qr-body { padding: 18px; display: grid; grid-template-columns: 360px 1fr; gap: 18px; align-items: start; }

  .qr-img {
    width: 100%;
    max-width: 360px;
    aspect-ratio: 1 / 1;
    background: #fff;
    border-radius: 14px;
    border: 1px solid rgba(0,0,0,.10);
    display:flex; align-items:center; justify-content:center;
    overflow:hidden;
    margin: 0 auto; /* centra dentro de su columna */
  }
  [data-theme="dark"] .qr-img { background: #fff; } /* QR siempre sobre blanco para imprimir */
  .qr-img img { width: 100%; height: 100%; object-fit: contain; display:block; }

  .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono","Courier New", monospace; }

  @media print {
    .no-print { display:none !important; }
    .container-fluid { padding: 0 !important; }
    .qr-card { border: none !important; border-radius: 0 !important; }
    .qr-head { border: none !important; padding: 0 0 12px 0 !important; }
    body { background: #fff !important; }
  }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<?php
  // URL basada en APP_URL (env)
  $base = rtrim(config('app.url'), '/');
  $publicUrl = $base.'/q/'.$item->code;

  $qrImg  = 'https://chart.googleapis.com/chart?cht=qr&chs=720x720&chld=M|1&chl='.urlencode($publicUrl);
  $qrImgAlt = 'https://quickchart.io/qr?size=720&text='.urlencode($publicUrl);
?>

<div class="container-fluid p-0">
  <div class="print-wrap">

    <div class="d-flex align-items-center justify-content-between mb-3 no-print">
      <div>
        <h1 class="h3 mb-0">Imprimir QR</h1>
        <div class="text-muted">Listo para pegar en el punto físico.</div>
      </div>
      <div class="d-flex gap-2">
        <a href="<?php echo e(route('admin.qr-points.index')); ?>" class="btn btn-outline-secondary btn-sm">
          <i data-feather="arrow-left"></i> Volver
        </a>
        <button class="btn btn-primary btn-sm" onclick="window.print()">
          <i data-feather="printer"></i> Imprimir
        </button>
      </div>
    </div>

    <div class="qr-card">
      <div class="qr-head">
        <div class="d-flex justify-content-between align-items-start gap-3">
          <div>
            <div class="h4 mb-1"><?php echo e($item->name); ?></div>
            <div class="text-muted">
              <?php echo e($item->address_text ?: '—'); ?>

            </div>
          </div>
          <div class="text-end">
            <div class="text-muted small">Código</div>
            <div class="mono fw-semibold"><?php echo e($item->code); ?></div>
          </div>
        </div>
      </div>

      <div class="qr-body">
        <div>
          <div class="qr-img">
            <img src="<?php echo e($qrImg); ?>" alt="QR"
                 referrerpolicy="no-referrer"
                 onerror="this.onerror=null;this.src='<?php echo e($qrImgAlt); ?>';">
          </div>

          <div class="text-center mt-2">
            <div class="text-muted small">Escanea para pedir taxi</div>
          </div>
        </div>

        <div>
          <div class="mb-3">
            <div class="text-muted small">Origen (coordenadas)</div>
            <div class="mono fw-semibold"><?php echo e(number_format($item->lat, 6)); ?>, <?php echo e(number_format($item->lng, 6)); ?></div>
          </div>

          <div class="mb-3">
            <div class="text-muted small">Link público</div>
            <div class="mono" style="word-break: break-all;"><?php echo e($publicUrl); ?></div>
          </div>

          <div class="alert alert-info py-2 mb-0">
            <div class="fw-semibold mb-1">Instrucción para el pasajero</div>
            <div class="small">
              Escanea el QR para pedir un taxi desde este punto. El origen ya está definido.
            </div>
          </div>

          
          <div class="mt-3 no-print">
            <div class="text-muted small mb-2">Opciones</div>
            <div class="d-flex gap-2 flex-wrap">
              <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print()">
                <i data-feather="printer"></i> Imprimir
              </button>
              
              
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/admin/qr_points/show.blade.php ENDPATH**/ ?>