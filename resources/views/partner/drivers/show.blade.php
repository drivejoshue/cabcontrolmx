@extends('layouts.partner')
@section('title','Conductor #'.$driver->id)

@push('styles')
<style>
  .avatar-lg{width:140px;height:140px;object-fit:cover}
  .stat-pill{border-radius:999px;padding:.35rem .8rem}

  /* Tokens simples por theme (Tabler/Bootstrap 5 usa data-bs-theme) */
  :root{
    --p-card-border: rgba(0,0,0,.10);
    --p-soft-bg: rgba(0,0,0,.04);
    --p-help-bg: rgba(13,110,253,.10);
    --p-help-border: rgba(13,110,253,.28);
    --p-warn-bg: rgba(255,193,7,.14);
    --p-warn-border: rgba(255,193,7,.30);
  }
  [data-bs-theme="dark"]{
    --p-card-border: rgba(255,255,255,.10);
    --p-soft-bg: rgba(255,255,255,.05);
    --p-help-bg: rgba(13,110,253,.12);
    --p-help-border: rgba(13,110,253,.22);
    --p-warn-bg: rgba(255,193,7,.10);
    --p-warn-border: rgba(255,193,7,.20);
  }

  .card{ border-color: var(--p-card-border) !important; }
  .soft-box{
    background: var(--p-soft-bg);
    border: 1px solid var(--p-card-border);
    border-radius: 12px;
  }

  .help-callout{
    background: var(--p-help-bg);
    border: 1px solid var(--p-help-border);
    border-radius: 12px;
    padding: .65rem .8rem;
  }
  .warn-callout{
    background: var(--p-warn-bg);
    border: 1px solid var(--p-warn-border);
    border-radius: 12px;
    padding: .65rem .8rem;
  }

  /* En dark: suavizar saturación para que no “grite” */
  [data-bs-theme="dark"] .badge.bg-success,
  [data-bs-theme="dark"] .badge.bg-warning,
  [data-bs-theme="dark"] .badge.bg-danger,
  [data-bs-theme="dark"] .badge.bg-primary,
  [data-bs-theme="dark"] .badge.bg-secondary{
    filter: saturate(.88);
  }

  /* Tabla head suave (evita blanco duro en dark) */
  [data-bs-theme="dark"] .table-light{
    --tblr-table-bg: rgba(255,255,255,.05);
    --tblr-table-color: rgba(255,255,255,.78);
  }

  .btn[disabled], .btn.disabled{
    opacity: .55;
    cursor: not-allowed;
  }
</style>
@endpush

@section('content')
<div class="container-fluid p-0">

  {{-- Header + acciones --}}
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-3">
      <div class="rounded bg-white border d-flex align-items-center justify-content-center" style="width:60px;height:60px;">
        <i data-feather="user"></i>
      </div>
      <div>
        <h3 class="mb-0">{{ $driver->nombre ?? $driver->name ?? ('Conductor #'.$driver->id) }}</h3>
        <div class="text-muted">
          ID: {{ $driver->id }} · Tel: {{ $driver->telefono ?? $driver->phone ?? '—' }}
        </div>
      </div>
    </div>

   <div class="d-flex gap-2">
  <a href="{{ route('partner.drivers.documents.index',['id'=>$driver->id]) }}" class="btn btn-outline-primary">
    <i data-feather="file-text"></i> Documentos y verificación
  </a>
  <a href="{{ route('partner.drivers.edit',$driver->id) }}" class="btn btn-primary">
    <i data-feather="edit-2"></i> Editar
  </a>
  <a href="{{ route('partner.drivers.index') }}" class="btn btn-outline-secondary">
    <i data-feather="arrow-left"></i> Volver
  </a>

  {{-- Botón que abre modal --}}
  <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deactivateDriverModal">
    <i data-feather="trash-2"></i> Desactivar
  </button>

  {{-- Form real (submit desde el modal) --}}
  <form id="deactivateDriverForm" method="post" action="{{ route('partner.drivers.destroy',$driver->id) }}">
    @csrf @method('DELETE')
  </form>
</div>

  </div>

  @if(session('ok'))
    <div class="alert alert-success">{{ session('ok') }}</div>
  @endif

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  {{-- ====== Resumen de verificación / documentos  ====== --}}
  @php
    $verStatus = $driver->verification_status ?? 'pending';
    $verBadge  = 'bg-secondary';
    $verLabel  = 'Pendiente';

    if ($verStatus === 'pending') {
      $verBadge = 'bg-warning text-dark';
      $verLabel = 'Pendiente';
    } elseif ($verStatus === 'verified') {
      $verBadge = 'bg-success';
      $verLabel = 'Verificado';
    } elseif ($verStatus === 'rejected') {
      $verBadge = 'bg-danger';
      $verLabel = 'Rechazado';
    }

    $docsCollection = collect($driverDocs ?? []);
    $docsCount = $docsCollection->count();
    $canAssignVehicle = $docsCount >= 2;

    // último doc por tipo (para chips)
    $latestByType = $docsCollection
      ->groupBy('type')
      ->map(fn($g) => $g->sortByDesc('id')->first());

    $typesMap = $driverDocTypesMap ?? [
      'licencia'       => 'Licencia de conducir',
      'ine'            => 'INE / identificación oficial',
      'selfie'         => 'Selfie con identificación',
      'foto_conductor' => 'Foto del conductor (opcional)',
    ];

    $pendingCount = $docsCollection->where('status','pending')->count();
    $approvedCount = $docsCollection->where('status','approved')->count();
    $rejectedCount = $docsCollection->where('status','rejected')->count();

    $hasUser = !empty($driver->user_id);
  @endphp

  @if(session('driver_creds'))
    @php $c = session('driver_creds'); @endphp
    <div class="alert alert-warning">
      <div class="d-flex justify-content-between align-items-start gap-2">
        <div>
          <div class="fw-bold mb-1">Credenciales de acceso (solo visible una vez)</div>
          <div class="small text-muted">Cópialas y guárdalas. Podras verlas en area de cuenta de usuario.</div>

          <div class="mt-2">
            <div class="small"><b>Email:</b> <code id="credEmail">{{ $c['email'] }}</code></div>
            <div class="small"><b>Password:</b> <code id="credPass">{{ $c['password'] }}</code></div>
          </div>
        </div>

        <div class="d-flex gap-2">
          <button type="button" class="btn btn-sm btn-outline-dark" onclick="copyText('credEmail')">Copiar email</button>
          <button type="button" class="btn btn-sm btn-primary" onclick="copyText('credPass')">Copiar password</button>
        </div>
      </div>
    </div>
  @endif

  <div class="card mb-3">
    <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
      <div>
        <div class="mb-1">
          <strong>Verificación Orbana (conductor)</strong>
        </div>

        <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
          <span class="badge {{ $verBadge }} stat-pill">{{ $verLabel }}</span>
          <span class="text-muted small">
            Los documentos son requeridos. quedan pendientes de validación por Orbana.
          </span>
        </div>

        @if(!empty($driver->verification_notes))
          <div class="text-danger small mt-1">
            <strong>Notas de revisión:</strong> {{ $driver->verification_notes }}
          </div>
        @endif

        <hr class="my-2">

        <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
          <div class="soft-box px-3 py-2 small">
            <b>Documentos cargados:</b> {{ $docsCount }}
            <span class="text-muted">· Aprobados: {{ $approvedCount }} · Pendientes: {{ $pendingCount }} · Rechazados: {{ $rejectedCount }}</span>
          </div>

          <div class="text-muted small">
            Para asignar vehículo: mínimo <b>2 documentos</b>.
          </div>
        </div>

        <div class="mb-1 small"><strong>Tipos de documento (opcionales):</strong></div>
        <div class="d-flex flex-wrap gap-2">
          @foreach($typesMap as $tKey => $tLabel)
            @php
              $doc = $latestByType[$tKey] ?? null;
              $st = $doc->status ?? null;

              $b = 'bg-secondary';
              $suffix = '—';
              if ($st === 'approved') { $b = 'bg-success'; $suffix = '✓'; }
              elseif ($st === 'pending') { $b = 'bg-warning text-dark'; $suffix = 'pendiente'; }
              elseif ($st === 'rejected') { $b = 'bg-danger'; $suffix = 'rechazado'; }

              // Si nunca subió ese tipo y es foto_conductor, marcar explícito opcional
              if (!$doc && $tKey === 'foto_conductor') {
                $b = 'bg-light text-muted';
                $suffix = 'opcional';
              }
            @endphp

            <span class="badge {{ $b }} stat-pill">
              {{ $tLabel }} <span class="opacity-75">{{ $suffix }}</span>
            </span>
          @endforeach
        </div>

        @if(!$canAssignVehicle)
          <div class="warn-callout mt-3 mb-0 small">
            <b>Acción requerida:</b> Para asignar un vehículo, sube al menos <b>2 documentos</b> del conductor.
            <div class="text-muted mt-1">Después quedará <b>pendiente de validación</b> por Orbana.</div>
          </div>
        @else
          <div class="help-callout mt-3 mb-0 small">
            <b>Listo para asignación:</b> ya hay {{ $docsCount }} documento(s) cargado(s).
            <div class="text-muted mt-1">Puedes asignar vehículo; la validación se hará posteriormente.</div>
          </div>
        @endif

       
      </div>

      <div class="text-end">
        <a href="{{ route('partner.drivers.documents.index', ['id' => $driver->id]) }}"
           class="btn btn-outline-primary">
          <i data-feather="file-text"></i> Ver / subir documentos
        </a>
      </div>
    </div>
  </div>

  {{-- Col 1: foto + estado / Col 2: ficha --}}
  <div class="row g-3">
    <div class="col-12 col-lg-4">
      <div class="card h-100">
        <div class="card-body text-center">
          @if(!empty($driver->foto_path))
            <img src="{{ asset('storage/'.$driver->foto_path) }}" class="rounded border mb-2 avatar-lg" alt="Foto conductor">
          @else
            <div class="bg-light border rounded d-flex align-items-center justify-content-center mx-auto mb-2 avatar-lg">
              <i data-feather="camera-off"></i>
            </div>
          @endif

          @php
            $status = $driver->status ?? 'offline';
            $badge  = $status==='idle' ? 'bg-success' : ($status==='busy' ? 'bg-warning text-dark' : 'bg-secondary');

            $vStatus = $driver->verification_status ?? 'pending';
            $vBadge  = $vStatus==='verified' ? 'bg-success'
                        : ($vStatus==='rejected' ? 'bg-danger' : 'bg-warning text-dark');
          @endphp

          <div>
            <span class="badge {{ $badge }} stat-pill text-uppercase">{{ $status }}</span>
          </div>

          <hr>

          <div class="text-start">
            <div class="small text-muted mb-1">Verificación Orbana</div>
            <div>
              <span class="badge {{ $vBadge }} stat-pill text-uppercase">
                {{ $vStatus }}
              </span>
            </div>

            @if(!empty($driver->verification_notes))
              <div class="small text-danger mt-2">
                <b>Notas:</b> {{ $driver->verification_notes }}
              </div>
            @endif

            <div class="mt-2">
              <a href="{{ route('partner.drivers.documents.index',['id'=>$driver->id]) }}" class="btn btn-sm btn-outline-primary">
                Ver / subir documentos
              </a>
            </div>

            <div class="text-muted small mt-2">
              Para asignar vehículo: mínimo <b>2 documentos</b>.
            </div>
          </div>

        </div>
      </div>
    </div>

    <div class="col-12 col-lg-8">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong>Ficha del conductor</strong>
          <span class="text-muted small">Actualizado: {{ $driver->updated_at ?? '—' }}</span>
        </div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-sm-3">Teléfono</dt><dd class="col-sm-9">{{ $driver->telefono ?? $driver->phone ?? '—' }}</dd>
            <dt class="col-sm-3">Email</dt><dd class="col-sm-9">{{ $driver->email ?? '—' }}</dd>
            <dt class="col-sm-3">Documento</dt><dd class="col-sm-9">{{ $driver->document_id ?? '—' }}</dd>
            <dt class="col-sm-3">Última ubicación</dt>
            <dd class="col-sm-9">
              @if(!empty($driver->last_lat) && !empty($driver->last_lng))
                {{ $driver->last_lat }}, {{ $driver->last_lng }}
              @else — @endif
            </dd>
          </dl>

          <hr>

          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="fw-semibold">Cuenta de acceso (App Driver)</div>
              <div class="text-muted small">
                @if($hasUser)
                  Usuario vinculado: <code>{{ $driver->user_email ?? ($linkedUser->email ?? '—') }}</code>
                @else
                  Sin usuario vinculado
                @endif
              </div>
              <div class="text-muted small mt-1">
                Si suspendes al conductor, también se bloquea su acceso a la app.
              </div>
            </div>

            <div class="d-flex gap-2">
              @if($hasUser)
                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalResetPwd">
                  <i data-feather="key"></i> Reset password
                </button>
              @else
                <a class="btn btn-sm btn-outline-primary" href="{{ route('partner.drivers.edit',$driver->id) }}">
                  <i data-feather="user-plus"></i> Crear usuario
                </a>
              @endif
            </div>
          </div>

          <div class="help-callout small mt-3">
            Consejo operativo: Sube imagenes claras de los documentos del Taxi, subirlos reduce rechazos y acelera soporte/validación.
          </div>

        </div>
      </div>
    </div>
  </div>

  {{-- Asignación y histórico --}}
  <div class="row g-3 mt-1">
    <div class="col-12 col-lg-6">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong>Asignación actual</strong>

          @if($canAssignVehicle)
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalAssignVehicle">
              <i data-feather="link"></i> Asignar vehículo
            </button>
          @else
            <button class="btn btn-sm btn-primary" type="button" onclick="showNeedDocsAssignVehicle()">
              <i data-feather="link"></i> Asignar vehículo
            </button>
          @endif
        </div>

        <div class="card-body">
          @if(!$canAssignVehicle)
            <div class="warn-callout small mb-3">
              <b>Bloqueado:</b> sube al menos <b>2 documentos</b> del conductor para habilitar la asignación.
              <div class="text-muted mt-1">Al asignar, el proceso queda pendiente de validación por Orbana.</div>
            </div>
          @endif

          @php $current = $currentAssignment ?? null; @endphp
          @if($current)
            <div class="d-flex align-items-center gap-3">
              <div class="rounded border bg-light d-flex align-items-center justify-content-center" style="width:64px;height:40px;">
                <i data-feather="truck"></i>
              </div>
              <div>
                <div><strong>#{{ $current->economico }}</strong> — {{ $current->brand ?? '—' }} {{ $current->model ?? '' }}</div>
                <div class="text-muted small">Placa: {{ $current->plate ?? '—' }}</div>
                <div class="text-muted small">Desde: {{ $current->start_at }}</div>
              </div>
            </div>

            <form class="mt-3"
                  method="post"
                  action="{{ route('partner.assignments.close', ['assignmentId' => $current->assignment_id]) }}"
                  onsubmit="return confirm('¿Cerrar asignación actual?');">
              @csrf
              <button class="btn btn-sm btn-outline-danger">
                <i data-feather="slash"></i> Cerrar asignación
              </button>
            </form>
          @else
            <div class="alert alert-info mb-0">
              Este conductor no tiene vehículo asignado actualmente.
            </div>
          @endif
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-6">
      <div class="card h-100">
        <div class="card-header"><strong>Histórico de asignaciones</strong></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
              <thead class="table-light">
                <tr><th>Vehículo</th><th>Desde</th><th>Hasta</th></tr>
              </thead>
              <tbody>
                @forelse($assignments ?? [] as $a)
                  <tr>
                    <td>#{{ $a->economico }} — {{ $a->brand ?? '—' }} {{ $a->model ?? '' }}</td>
                    <td>{{ $a->start_at }}</td>
                    <td>{{ $a->end_at ?? 'Vigente' }}</td>
                  </tr>
                @empty
                  <tr><td colspan="3" class="text-center text-muted">Sin registros</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

{{-- Modal: asignar vehículo --}}
<div class="modal fade" id="modalAssignVehicle" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="{{ route('partner.drivers.assignVehicle',['id'=>$driver->id]) }}">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title">Asignar vehículo a {{ $driver->nombre ?? $driver->name ?? 'Conductor' }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        @if(!$canAssignVehicle)
          <div class="warn-callout small mb-3">
            Para asignar un vehículo, primero sube al menos <b>2 documentos</b> del conductor.
            <div class="text-muted mt-1">Se marcará como pendiente de validación por Orbana.</div>
          </div>
        @endif

        <div class="mb-2">
          <label class="form-label">Vehículo</label>
          <select class="form-select" name="vehicle_id" required>
            @foreach($vehiclesForSelect ?? [] as $veh)
              <option value="{{ $veh->id }}">#{{ $veh->economico }} — {{ $veh->brand }} {{ $veh->model }} ({{ $veh->plate ?: 'sin placa' }})</option>
            @endforeach
          </select>
          <div class="text-muted small mt-1">
            Vehiculos e Partner.
          </div>
        </div>

        <div class="mb-2">
          <label class="form-label">Inicio</label>
          <input type="datetime-local" name="start_at" class="form-control">
          <div class="text-muted small mt-1">Si lo dejas vacío, se usa la fecha/hora actual.</div>
        </div>

        

        <div class="mt-2">
          <label class="form-label">Nota (opcional)</label>
          <input type="text" class="form-control" name="note" maxlength="255">
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" type="submit" {{ $canAssignVehicle ? '' : 'disabled' }}>
          Guardar
        </button>
      </div>
    </form>
  </div>
</div>

{{-- Modal: bloqueo por falta de documentos (popup amigable) --}}
<div class="modal fade" id="modalNeedDocs" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">No se puede asignar vehículo aún</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="warn-callout small mb-2">
          Para poder asignar un vehículo, primero sube al menos <b>2 documentos</b> del conductor.
          <div class="text-muted mt-1">Después quedará <b>pendiente de validación</b> por Orbana.</div>
        </div>

        <div class="small text-muted">
          Documentos : INE, licencia, selfie, foto del conductor.
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cerrar</button>
        <a class="btn btn-primary" href="{{ route('partner.drivers.documents.index',['id'=>$driver->id]) }}">
          <i data-feather="file-text"></i> Subir documentos
        </a>
      </div>
    </div>
  </div>
</div>

@if(!empty($linkedUser))
  <div class="modal fade" id="modalResetPwd" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form class="modal-content" method="post" action="{{ route('partner.drivers.resetPassword', ['id'=>$driver->id]) }}">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Resetear contraseña</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="warn-callout small">
            Se generará una contraseña temporal nueva para <b>{{ $linkedUser->email }}</b>.
            <div class="text-muted mt-1">La contraseña se mostrará una sola vez al guardar.</div>
          </div>
          <p class="mb-0 small text-muted mt-2">
            Recomendación: el conductor debe cambiarla después (soporte o flujo futuro de cambio de contraseña).
          </p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-warning" type="submit">Generar nueva contraseña</button>
        </div>
      </form>
    </div>
  </div>
@endif
<div class="modal fade" id="deactivateDriverModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Desactivar conductor</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <p class="mb-2">
          ¿Confirmas desactivar a <strong>{{ $driver->name }}</strong>?
        </p>
        <div class="text-muted small">
          Se bloqueará el acceso a la app. Puedes reactivarlo después.
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          Cancelar
        </button>

        <button type="button" class="btn btn-danger" id="btnConfirmDeactivate">
          Sí, desactivar
        </button>
      </div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
  async function copyText(elId){
    const el = document.getElementById(elId);
    if(!el) return;
    const text = (el.innerText || el.textContent || '').trim();
    if(!text) return;

    try {
      await navigator.clipboard.writeText(text);
    } catch (e) {
      const ta = document.createElement('textarea');
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
    }
  }

  function showNeedDocsAssignVehicle(){
    const el = document.getElementById('modalNeedDocs');
    if(!el || !window.bootstrap) {
      alert('Sube al menos 2 documentos del conductor para poder asignar un vehículo. Quedará pendiente de validación.');
      return;
    }
    const m = bootstrap.Modal.getOrCreateInstance(el);
    m.show();
  }
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const btn = document.getElementById('btnConfirmDeactivate');
  const form = document.getElementById('deactivateDriverForm');
  if (!btn || !form) return;

  btn.addEventListener('click', function () {
    form.submit();
  });
});
</script>
@endpush
