@php
  $tenantName = $tenant->name ?? 'Tu central';
  $emailAdmin = auth()->user()->email ?? '—';
  $planCode = $profile?->plan_code ?? 'PV_STARTER';

  $plan = $billingPlan ?? null;

  $trialDays = (int)($plan?->trial_days ?? 14);
  $trialVehicles = (int)($plan?->trial_vehicles ?? 5);

  $pricePerVehicle = $plan?->price_per_vehicle;
  if ($pricePerVehicle === null) $pricePerVehicle = $profile?->price_per_vehicle;
  if ($pricePerVehicle === null) $pricePerVehicle = 299;

  $currency = $plan?->currency ?? ($profile?->currency ?? 'MXN');

  // Descuentos (si aún no están en billing_plans, los dejamos fijos aquí por ahora)
  $disc1From = (int)($plan?->discount_tier1_from ?? 50);
  $disc1To   = (int)($plan?->discount_tier1_to ?? 99);
  $disc1Pct  = (float)($plan?->discount_tier1_percent ?? 10);

  $disc2From = (int)($plan?->discount_tier2_from ?? 100);
  $disc2Pct  = (float)($plan?->discount_tier2_percent ?? 20);

    $providerName = $provider?->contact_name ?? '—';
  $providerDisplay = $provider?->display_name ?? 'Orbana';
@endphp

@push('styles')
<style>
  /* Tipografía del documento dentro del modal */
  #billingTermsBox.terms-doc{
    font-size: 1rem;          /* antes se veía chico */
    line-height: 1.45;
  }
  #billingTermsBox.terms-doc h4{
    font-size: 1.05rem;
    margin-top: 1rem;
    margin-bottom: .5rem;
  }
  #billingTermsBox.terms-doc .muted{
    color: rgba(0,0,0,.55);
  }
  [data-bs-theme="dark"] #billingTermsBox.terms-doc .muted{
    color: rgba(255,255,255,.65);
  }
</style>
@endpush

<div class="modal fade" id="billingTermsModal" tabindex="-1" aria-labelledby="billingTermsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <div>
          <h5 class="modal-title fs-4" id="billingTermsModalLabel">ORBANA DISPATCH — Términos del Servicio</h5>
          <div class="text-muted">
            Términos del Servicio, Condiciones de Facturación y Política de Verificación de Flota
          </div>
          <div class="text-muted">
            Tenant: <strong>{{ $tenantName }}</strong> · Plan: <strong>{{ $planCode }}</strong>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">

        <div class="alert alert-info mb-3">
          <div class="fw-semibold mb-1"><i class="ti ti-info-circle"></i> Aceptación digital</div>
          <div>
            Al hacer clic en <strong>“Acepto”</strong>, La Central confirma que cuenta con facultades para obligarse a estos términos y
            autoriza el inicio/continuidad de la facturación bajo el esquema descrito.
            La aceptación se registra con fecha/hora, usuario administrador y Tenant ID.
          </div>
          <div class="mt-2">
            Este tenant está asociado al usuario administrador: <strong>{{ $emailAdmin }}</strong>
          </div>
        </div>

        <div class="border rounded p-4 terms-doc" id="billingTermsBox" style="max-height: 72vh; overflow:auto;">

          <div class="muted">
            <div><strong>Versión:</strong> 1.0</div>
            <div><strong>Fecha de entrada en vigor:</strong> 11 de enero de 2026</div>
            <div><strong>Sitio web:</strong> orbana.mx</div>
          </div>

          <hr class="my-3">

          <div>
            <div><strong>Proveedor:</strong> {{ $providerName }} (persona física) (“Orbana”)</div>
            <div><strong>Cliente (Tenant):</strong> {{ $tenantName }} (“La Central”)</div>
            <div><strong>Tenant ID:</strong> {{ $tenant->id }}</div>
            <div><strong>Zona horaria operativa:</strong> {{ $tenant->timezone ?? 'America/Mexico_City' }}</div>
          </div>

          <h4>1) Definiciones</h4>
          <ul>
            <li><strong>Servicio:</strong> Plataforma Orbana Dispatch (panel web) y componentes operativos asociados habilitados para el Tenant.</li>
            <li><strong>Tenant / Central:</strong> Organización cliente que administra usuarios, conductores y vehículos dentro del sistema.</li>
            <li><strong>Unidad / Vehículo:</strong> Registro de vehículo asociado al Tenant dentro de Orbana.</li>
            <li><strong>Vehículo activo:</strong> Vehículo marcado como activo dentro del Tenant.</li>
            <li><strong>Plan per-vehicle:</strong> Modelo de facturación por unidades activas (prepago mensual).</li>
            <li><strong>Wallet (saldo):</strong> Saldo prepago del Tenant para cubrir cargos del servicio.</li>
            <li><strong>Factura:</strong> Documento interno del sistema que refleja el cargo por periodo (mes completo o prorrateo post-trial).</li>
            <li><strong>Periodo:</strong> Rango de fechas facturadas; normalmente del día 1 al último día del mes (cierre a fin de mes).</li>
            <li><strong>Trial:</strong> Periodo de prueba inicial con reglas y límites.</li>
          </ul>

          <h4>2) Alcance del servicio y naturaleza de la relación</h4>
          <ul>
            <li>Orbana provee una plataforma tecnológica para operación de despacho, gestión y monitoreo.</li>
            <li>La Central es responsable del cumplimiento legal y operativo de su servicio de transporte (permisos, licencias, seguros, condiciones del vehículo, contratación, control interno, etc.).</li>
            <li>Para proteger a usuarios finales y la integridad de la plataforma, Orbana puede requerir verificación mínima de flota y conductores conforme a este documento.</li>
          </ul>

          <h4>3) Plan, precios y descuentos (per-vehicle)</h4>
          <ul>
            <li>
              <strong>Precio:</strong>
              <strong>${{ number_format((float)$pricePerVehicle, 2) }} {{ $currency }}</strong> por vehículo activo / mes.
              Los importes y el detalle del plan se muestran dentro del módulo de Facturación del Tenant.
            </li>
            <li>
              <strong>Trial:</strong> {{ $trialDays }} días, máximo {{ $trialVehicles }} vehículos activos.
            </li>
            <li>
              <strong>Descuentos por flotilla:</strong>
              {{ (int)$disc1Pct }}% si tiene {{ $disc1From }}–{{ $disc1To }} vehículos activos;
              {{ (int)$disc2Pct }}% si tiene {{ $disc2From }} o más vehículos activos.
              El descuento, cuando aplique, se refleja en la factura y en el prorrateo post-trial.
            </li>
          </ul>

          <h4>4) Modelo de facturación (per-vehicle) y reglas operativas</h4>

          <h4 style="margin-top:.25rem;">4.1 Trial (periodo de prueba)</h4>
          <ul>
            <li>Durante el trial el servicio puede operar sin cobro inmediato.</li>
            <li>Límite durante trial: máximo <strong>{{ $trialVehicles }}</strong> vehículos activos.</li>
            <li>Si La Central excede el límite del trial, Orbana podrá restringir la activación de vehículos adicionales hasta finalizar el trial o regularizar el plan.</li>
          </ul>

          <h4>4.2 Fin de trial y activación post-trial (prorrateo a fin de mes)</h4>
          <ul>
            <li>Al terminar el trial, el sistema genera una factura post-trial por el periodo desde el día siguiente al fin del trial hasta el último día del mes (prorrateo).</li>
            <li>La factura post-trial tiene un plazo máximo de <strong>5 días naturales</strong> para pagarse (fecha de vencimiento).</li>
            <li>Si al finalizar el día de vencimiento la factura no está pagada, el Tenant puede entrar a bloqueo operativo conforme a la sección 6.</li>
          </ul>

          <h4>4.3 Cargo mensual (prepago) con corte a fin de mes</h4>
          <ul>
            <li>La factura mensual se emite el día <strong>1</strong> (zona horaria del Tenant) por el periodo del 1 al último día del mes.</li>
            <li>Límite de pago: hasta el día <strong>5</strong>.</li>
            <li>Bloqueo: desde el día <strong>6</strong> si no se cubrió el pago.</li>
          </ul>

          <h4>4.4 Regla de altas/bajas de unidades</h4>
          <ul>
            <li>La cantidad facturada se determina por el número de vehículos activos al momento de emisión de la factura (mensual o post-trial).</li>
            <li>Altas posteriores (activar/agregar unidades después de emitida la factura) se reflejan en el siguiente ciclo.</li>
            <li>Bajas posteriores (desactivar unidades después de emitida la factura) no generan reembolso del periodo en curso.</li>
          </ul>

          <h4>5) Wallet, recargas y métodos de pago</h4>
          <ul>
            <li>La Central mantiene un saldo en wallet para cubrir facturas y deberá asegurar saldo suficiente dentro del periodo de gracia para evitar bloqueos.</li>
            <li><strong>Métodos de pago habilitados:</strong> Mercado Pago y transferencia (SPEI/depósito). Los datos y referencias se mostrarán dentro del módulo de Facturación del Tenant.</li>
            <li>Los tiempos de acreditación dependen del método de pago y del proveedor.</li>
          </ul>

          <h4>6) Facturas, vencimiento, periodo de gracia y bloqueo</h4>
          <ul>
            <li>Cada factura tiene fecha de emisión y fecha límite de pago visible en la plataforma.</li>
            <li>Si una factura no está pagada al finalizar el periodo de gracia, Orbana podrá aplicar bloqueo operativo del Tenant.</li>
            <li>El bloqueo puede restringir funciones críticas (por ejemplo: acceso al panel, operación, creación/asignación de servicios y funciones operativas).</li>
            <li>Para reactivar, La Central debe cubrir el monto pendiente; una vez registrado el pago, el servicio se reactivará conforme a las validaciones del sistema.</li>
          </ul>

          <h4>7) Soporte, tickets y comunicaciones</h4>
          <ul>
            <li>Soporte por <strong>tickets dentro de la plataforma</strong> como canal principal para dudas, aclaraciones y solicitudes.</li>
            <li>Las notificaciones operativas pueden mostrarse en el módulo de facturación, banners/modales del sistema y comunicaciones internas.</li>
            <li>La Central se compromete a mantener datos de contacto actualizados.</li>
          </ul>

          <h4>8) Privacidad de datos y seguridad</h4>
          <ul>
            <li>Orbana trata datos necesarios para operar el servicio: usuarios del Tenant, vehículos, conductores, viajes, eventos operativos y registros de auditoría.</li>
            <li>Se usan para operación del servicio, soporte, seguridad, trazabilidad y mejora operativa.</li>
            <li>Medidas razonables: control de acceso por roles, hashing de credenciales, auditoría y HTTPS/TLS cuando aplique.</li>
          </ul>

          <h4>9) Política de verificación de flota</h4>
          <ul>
            <li>Orbana podrá requerir verificación documental de la central y/o unidades para proteger la integridad y reputación del servicio.</li>
            <li>Si un vehículo no pasa verificación, se notificará para corrección y reenvío de documentos.</li>
            <li>Orbana podrá solicitar reenvío (por ejemplo 1 a 3 intentos) según el tipo de documento y observaciones.</li>
            <li>Si no se corrige en un plazo razonable, Orbana podrá suspender la unidad (desactivarla en sistema) hasta regularización.</li>
            <li>La suspensión de una unidad por documentación no implica reembolso de cargos ya devengados en el periodo correspondiente.</li>
            <li>La Central es responsable de su operación interna (licencias, permisos, etc.); Orbana no administra la relación laboral ni el cumplimiento legal interno de la Central.</li>
          </ul>

          <h4>10) Limitación de responsabilidad</h4>
          <ul>
            <li>Orbana no presta el servicio de taxi; provee tecnología.</li>
            <li>La Central es responsable del servicio de transporte y de su personal.</li>
            <li>Orbana no responde por daños indirectos derivados de la operación de la Central, salvo donde la ley lo prohíba.</li>
          </ul>

          <h4>11) Cambios a las condiciones</h4>
          <ul>
            <li>Orbana podrá actualizar estos términos y notificar dentro del sistema.</li>
            <li>La aceptación podrá requerirse para continuar operando si impacta facturación o verificación.</li>
          </ul>

          <h4>12) Aceptación</h4>
          <div>
            Al presionar <strong>“Acepto”</strong>, La Central declara y acepta lo anterior.
            La aceptación se registra automáticamente con: usuario administrador, fecha/hora, Tenant ID y versión del documento.
          </div>

          <hr class="my-3">

          <h4>ANEXO A — Resumen operativo </h4>
          <ul class="mb-0">
            <li><strong>Corte:</strong> fin de mes.</li>
            <li><strong>Cobro:</strong> prepago mensual desde wallet.</li>
            <li><strong>Trial:</strong> {{ $trialDays }} días, máximo {{ $trialVehicles }} vehículos activos.</li>
            <li><strong>Post-trial:</strong> prorrateo hasta fin de mes; factura vence en 5 días.</li>
            <li><strong>Mensual:</strong> factura día 1; se paga máximo el día 5; bloqueo desde el día 6 si no se pagó.</li>
            <li><strong>Altas/bajas:</strong> se reflejan el siguiente ciclo; sin reembolsos del periodo en curso.</li>
            <li><strong>Verificación:</strong> unidades/conductores deben cumplir; unidades no verificadas pueden suspenderse.</li>
          </ul>

        </div>

        <div class="mt-3">
          <label class="form-check">
            <input class="form-check-input" type="checkbox" id="billingTermsCheck">
            <span class="form-check-label fw-semibold">
              Confirmo que leí y acepto los términos de facturación y verificación.
            </span>
          </label>
          <div class="text-muted">
            Para habilitar “Acepto”, desplázate hasta el final del texto y marca la casilla.
          </div>
        </div>

      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>

        <form method="POST" action="{{ route('admin.billing.accept_terms') }}" class="m-0">
          @csrf
          <button type="submit" class="btn btn-primary" id="btnAcceptBillingTerms" disabled>
            <i class="ti ti-check"></i> Acepto
          </button>
        </form>
      </div>

    </div>
  </div>
</div>

@push('scripts')
<script>
(function () {
  const box = document.getElementById('billingTermsBox');
  const chk = document.getElementById('billingTermsCheck');
  const btn = document.getElementById('btnAcceptBillingTerms');
  if (!box || !chk || !btn) return;

  let reachedBottom = false;

  function nearBottom(el) {
    const threshold = 12;
    return (el.scrollTop + el.clientHeight) >= (el.scrollHeight - threshold);
  }

  function update() {
    btn.disabled = !(reachedBottom && chk.checked);
  }

  box.addEventListener('scroll', function () {
    if (nearBottom(box)) reachedBottom = true;
    update();
  });

  chk.addEventListener('change', update);

  const modal = document.getElementById('billingTermsModal');
  modal?.addEventListener('shown.bs.modal', function () {
    reachedBottom = nearBottom(box);
    chk.checked = false;
    box.scrollTop = 0;
    update();
  });
})();
</script>
@endpush
