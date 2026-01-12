{{-- resources/views/admin/tenant/edit.blade.php --}}
@extends('layouts.admin')
@section('title','Mi Central')

@section('content')
<div class="container-fluid p-0">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h1 class="h3 mb-1">Mi Central</h1>
      <div class="text-muted">Administra los datos públicos y de notificación de tu central.</div>
    </div>
    <div class="text-end small text-muted">
      <div>Tenant ID: <strong>{{ $tenant->id }}</strong></div>
      <div>Onboarding: <strong>{{ $tenant->onboarding_done_at ? 'Completado' : 'Pendiente' }}</strong></div>
    </div>
  </div>

  @if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
  @endif

  @if(session('ok'))
    <div class="alert alert-success">{{ session('ok') }}</div>
  @endif

  @if ($errors->any())
    <div class="alert alert-danger">
      <div class="fw-bold mb-1">Revisa los campos:</div>
      <ul class="mb-0">
        @foreach($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  @php
    $docs = $docs ?? collect();

    $p = $tenant->billingProfile ?? null;
    $billingAccepted = !empty($p?->accepted_terms_at);

    $docLabel = [
      'id_official'     => 'Identificación oficial',
      'proof_address'   => 'Comprobante de domicilio',
      'tax_certificate' => 'Constancia fiscal (opcional)',
    ];

    $docIcon = [
      'id_official'     => 'ti ti-id',
      'proof_address'   => 'ti ti-home',
      'tax_certificate' => 'ti ti-receipt-tax',
    ];

    $badgeDoc = function($status) {
      return match($status) {
        'approved' => 'bg-success-lt text-success',
        'rejected' => 'bg-danger-lt text-danger',
        'pending'  => 'bg-warning-lt text-warning',
        default    => 'bg-secondary-lt text-secondary',
      };
    };

    $docStatusText = function($status) {
      return match($status) {
        'approved' => 'Aprobado',
        'rejected' => 'Rechazado',
        'pending'  => 'Pendiente',
        default    => 'Sin archivo',
      };
    };
  @endphp

  <div class="row g-3">

    {{-- ===================== COLUMNA IZQUIERDA ===================== --}}
    <div class="col-12 col-lg-7">

      {{-- ===================== FORM 1: DATOS CENTRAL ===================== --}}
      <div class="card shadow-sm border-0">
        <div class="card-header bg-transparent border-0 pb-0">
          <h5 class="card-title mb-0">Datos generales</h5>
          <div class="text-muted small">Estos datos puedes cambiarlos cuando gustes.</div>
        </div>

        <div class="card-body">
          <form method="POST" action="{{ route('admin.tenant.update') }}">
            @csrf

            <div class="mb-3">
              <label class="form-label">Nombre de la central <span class="text-danger">*</span></label>
              <input
                type="text"
                name="name"
                class="form-control @error('name') is-invalid @enderror"
                value="{{ old('name', $tenant->name) }}"
                maxlength="150"
                required
              >
              @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
              <label class="form-label">Email de notificaciones</label>
              <input
                type="email"
                name="notification_email"
                class="form-control @error('notification_email') is-invalid @enderror"
                value="{{ old('notification_email', $tenant->notification_email) }}"
                maxlength="190"
                placeholder="Ej. notificaciones@tucentral.com"
              >
              <div class="form-text">
                Si lo dejas vacío, se usará tu email de usuario: <strong>{{ auth()->user()->email }}</strong>
              </div>
              @error('notification_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="row g-2">
              <div class="col-12 col-md-6">
                <div class="mb-3">
                  <label class="form-label">Teléfono público</label>
                  <input
                    type="text"
                    name="public_phone"
                    class="form-control @error('public_phone') is-invalid @enderror"
                    value="{{ old('public_phone', $tenant->public_phone) }}"
                    maxlength="30"
                    placeholder="Ej. +52 229 123 4567"
                  >
                  @error('public_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
              </div>

              <div class="col-12 col-md-6">
                <div class="mb-3">
                  <label class="form-label">Ciudad pública</label>
                  <input
                    type="text"
                    name="public_city"
                    class="form-control @error('public_city') is-invalid @enderror"
                    value="{{ old('public_city', $tenant->public_city) }}"
                    maxlength="120"
                    placeholder="Ej. Veracruz, Ver."
                  >
                  @error('public_city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
              </div>
            </div>

            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary">
                Guardar cambios
              </button>
              <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">
                Volver
              </a>
            </div>
          </form>
        </div>
      </div>

      {{-- ===================== BILLING (FORM SEPARADO, NO ANIDADO) ===================== --}}
      <div class="d-flex align-items-center justify-content-between mt-3">
        <div class="small text-muted">
          <i class="ti ti-info-circle"></i>
          La facturación se habilita al aceptar términos. Una vez aceptado, no se puede revertir desde aquí.
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
          @if($billingAccepted)
            <span class="badge bg-success-lt text-success">
              <i class="ti ti-check"></i> Billing habilitado
            </span>
            <span class="text-muted small">
              {{ \Illuminate\Support\Carbon::parse($p->accepted_terms_at)->format('Y-m-d H:i') }}
            </span>
          @else
            <span class="badge bg-warning-lt text-warning">
              <i class="ti ti-alert-triangle"></i> Falta aceptar billing
            </span>

           <button type="button" class="btn btn-sm btn-primary"
        data-bs-toggle="modal" data-bs-target="#billingTermsModal">
  <i class="ti ti-file-check"></i> Aceptar billing
</button>
@include('admin.tenant._terms_modal', [
  'tenant' => $tenant,
  'profile' => $p,
  'billingPlan' => $billingPlan,
])

          @endif

        </div>
      </div>

      {{-- ===================== FORM 2: DOCUMENTOS (SEPARADO) ===================== --}}
      <div class="card shadow-sm border-0 mt-3">
        <div class="card-header bg-transparent border-0 pb-0">
          <h5 class="card-title mb-0">Documentación de la central</h5>
          <div class="text-muted small">
            Sube tus documentos antes de terminar el trial. Orbana debe validarlos.
          </div>
        </div>

        <div class="card-body">

          @error('docs')
            <div class="alert alert-danger">{{ $message }}</div>
          @enderror

          <form method="POST" action="{{ route('admin.tenant.documents.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="list-group list-group-flush border rounded">
              @foreach(['id_official','proof_address','tax_certificate'] as $type)
                @php
                  $doc = $docs->get($type);

                  $status = $doc->status ?? null;
                  $badgeClass = $badgeDoc($status);

                  $original = $doc->original_name ?? null;
                  $uploaded = !empty($doc?->uploaded_at)
                    ? \Illuminate\Support\Carbon::parse($doc->uploaded_at)->format('Y-m-d H:i')
                    : null;

                  $isRejected = ($status === 'rejected' && !empty($doc->review_notes));
                  $isApprovedLocked = ($status === 'approved'); // bloqueado para tenant
                @endphp

                <div class="list-group-item">
                  <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                    <div class="d-flex align-items-start gap-2" style="min-width: 280px;">
                      <span class="avatar avatar-sm bg-secondary-lt text-secondary">
                        <i class="{{ $docIcon[$type] ?? 'ti ti-file-text' }}"></i>
                      </span>

                      <div>
                        <div class="fw-semibold">{{ $docLabel[$type] }}</div>

                        <div class="text-muted small mt-1">
                          @if($doc)
                            <div>
                              <span class="text-muted">Archivo:</span>
                              <span class="fw-semibold">{{ $original }}</span>
                            </div>
                            <div>
                              <span class="text-muted">Subido:</span>
                              <span>{{ $uploaded ?? '—' }}</span>
                            </div>

                            @if($isRejected)
                              <div class="mt-1">
                                <span class="badge bg-danger-lt text-danger">
                                  <i class="ti ti-info-circle"></i> Observación
                                </span>
                                <span class="text-danger">{{ $doc->review_notes }}</span>
                              </div>
                            @endif
                          @else
                            <div>No se ha subido.</div>
                          @endif

                          <div class="mt-2">
                            <span class="badge {{ $badgeClass }}">
                              {{ $docStatusText($status) }}
                            </span>

                            <span class="ms-2 text-muted">
                              @if($type === 'tax_certificate')
                                Opcional
                              @else
                                Requerido
                              @endif
                            </span>

                            @if($isApprovedLocked)
                              <span class="ms-2 badge bg-secondary-lt text-secondary">
                                Bloqueado
                              </span>
                            @endif
                          </div>
                        </div>

                        @if($doc)
                          <div class="mt-2">
                            <a class="btn btn-sm btn-outline-secondary"
                               href="{{ route('admin.tenant.documents.download', $doc->id) }}">
                              <i class="ti ti-download"></i> Descargar
                            </a>

                            @if($isApprovedLocked)
                              <span class="text-muted small ms-2">
                                Para actualizar un documento aprobado, solicita a Orbana que lo reabra.
                              </span>
                            @endif
                          </div>
                        @endif
                      </div>
                    </div>
                  </div>

                  <div class="mt-3">
                    <input
                      type="file"
                      name="{{ $type }}"
                      class="form-control @error($type) is-invalid @enderror"
                      {{ $isApprovedLocked ? 'disabled' : '' }}
                    >
                    <div class="form-text">PDF/JPG/PNG · máx 10 MB</div>
                    @error($type) <div class="invalid-feedback">{{ $message }}</div> @enderror
                  </div>
                </div>
              @endforeach
            </div>

            <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mt-3">
              <div class="text-muted small">
                <i class="ti ti-shield-check"></i>
                Al enviar documentos, confirmas que son vigentes y pertenecen a la central.
              </div>

              <button class="btn btn-primary" type="submit">
                <i class="ti ti-upload"></i> Enviar documentos
              </button>
            </div>
          </form>

        </div>
      </div>

    </div>

    {{-- ===================== COLUMNA DERECHA ===================== --}}
    <div class="col-12 col-lg-5">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-transparent border-0 pb-0">
          <h5 class="card-title mb-0">Ubicación y cobertura</h5>
          <div class="text-muted small">Las coordenadas no se pueden editar desde aquí.</div>
        </div>

        <div class="card-body">
          <div class="alert alert-warning">
            <div class="fw-bold mb-1">Coordenadas bloqueadas</div>
            <div class="small">
              Para cambiar <strong>latitud/longitud</strong> o el <strong>radio</strong>, debes solicitarlo a <strong>Orbana</strong>.
            </div>
          </div>

          <div class="row g-2">
            <div class="col-6">
              <label class="form-label">Latitud</label>
              <input type="text" class="form-control" value="{{ $tenant->latitud ?? '-' }}" disabled>
            </div>
            <div class="col-6">
              <label class="form-label">Longitud</label>
              <input type="text" class="form-control" value="{{ $tenant->longitud ?? '-' }}" disabled>
            </div>
            <div class="col-12">
              <label class="form-label">Radio de cobertura (km)</label>
              <input type="text" class="form-control" value="{{ $tenant->coverage_radius_km ?? '-' }}" disabled>
            </div>
          </div>

          <hr class="my-3">

          <div class="row g-2">
            <div class="col-12">
              <label class="form-label">Zona horaria</label>
              <input type="text" class="form-control" value="{{ $tenant->timezone ?? 'America/Mexico_City' }}" disabled>
            </div>
            <div class="col-12">
              <label class="form-label">UTC offset (min)</label>
              <input type="text" class="form-control" value="{{ $tenant->utc_offset_minutes ?? '-' }}" disabled>
            </div>
          </div>

          <hr class="my-3">

          <div class="small text-muted">
            <div><strong>Slug:</strong> {{ $tenant->slug }}</div>
            <div><strong>Última actualización:</strong> {{ $tenant->updated_at }}</div>
            <div><strong>Onboarding done:</strong> {{ $tenant->onboarding_done_at ?? '-' }}</div>
          </div>

          <div class="mt-3">
            <div class="p-3 rounded border bg-body-tertiary">
              <div class="fw-bold mb-1">Solicitud de cambio de coordenadas</div>
              <div class="small text-muted mb-2">
                Por seguridad, la ubicación y el radio solo pueden modificarse mediante solicitud a soporte (Orbana).
                Envía estos datos para que podamos validar y aplicar el cambio:
              </div>

              <div class="small">
                <div><strong>Central:</strong> {{ $tenant->name }}</div>
                <div><strong>Tenant ID:</strong> {{ $tenant->id }}</div>
                <div><strong>Lat/Lng:</strong> {{ $tenant->latitud ?? '-' }}, {{ $tenant->longitud ?? '-' }}</div>
                <div><strong>Radio (km):</strong> {{ $tenant->coverage_radius_km ?? '-' }}</div>
                <div><strong>Ciudad pública:</strong> {{ $tenant->public_city ?? '-' }}</div>
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
</div>
@endsection

@push('scripts')
<script>
(function () {
  const btn = document.getElementById('btnCopyTenantLocation');
  if (!btn) return;

  btn.addEventListener('click', async () => {
    const text =
`Solicitud de cambio de coordenadas (TENANT)
Tenant ID: {{ $tenant->id }}
Central: {{ addslashes($tenant->name) }}
Ciudad: {{ addslashes($tenant->public_city ?? '-') }}
Lat/Lng actual: {{ $tenant->latitud ?? '-' }}, {{ $tenant->longitud ?? '-' }}
Radio actual (km): {{ $tenant->coverage_radius_km ?? '-' }}

Motivo del cambio:
(NOTA: escribe aquí el motivo y las coordenadas nuevas solicitadas)`;

    try {
      await navigator.clipboard.writeText(text);
      btn.textContent = 'Copiado';
      setTimeout(() => btn.textContent = 'Copiar datos para soporte', 1200);
    } catch (e) {
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
@endpush
