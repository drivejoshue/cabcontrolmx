@extends('layouts.admin')
@section('title','Conductor #'.$driver->id)

@push('styles')
<style>
  .avatar-lg{width:140px;height:140px;object-fit:cover}
  .stat-pill{border-radius:999px;padding:.35rem .8rem}
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
  <a href="{{ route('drivers.documents.index',['id'=>$driver->id]) }}" class="btn btn-outline-primary">
    <i data-feather="file-text"></i> Documentos y verificación
  </a>
  <a href="{{ route('drivers.edit',$driver->id) }}" class="btn btn-primary">
    <i data-feather="edit-2"></i> Editar
  </a>
  <a href="{{ route('drivers.index') }}" class="btn btn-outline-secondary">
    <i data-feather="arrow-left"></i> Volver
  </a>
  <form method="post" action="{{ route('drivers.destroy',$driver->id) }}" onsubmit="return confirm('¿Eliminar definitivamente?');">
    @csrf @method('DELETE')
    <button class="btn btn-outline-danger">
      <i data-feather="trash-2"></i> Eliminar
    </button>
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

  {{-- ====== Tarjeta de verificación Orbana ====== --}}
  @php
    $verStatus = $driver->verification_status ?? 'not_started';
    $verBadge  = 'bg-secondary';
    $verLabel  = 'Sin iniciar';

    if ($verStatus === 'pending') {
      $verBadge = 'bg-warning text-dark';
      $verLabel = 'En proceso de verificación';
    } elseif ($verStatus === 'verified') {
      $verBadge = 'bg-success';
      $verLabel = 'Verificado';
    } elseif ($verStatus === 'rejected') {
      $verBadge = 'bg-danger';
      $verLabel = 'Rechazado';
    }

    // Resumen de documentos
    $docsCollection   = collect($driverDocs ?? []);
    $approvedByType   = $docsCollection
                          ->where('status','approved')
                          ->groupBy('type')
                          ->map(fn($g) => $g->sortByDesc('id')->first());
    $requiredOkDriver = collect($driverRequiredTypes ?? [])->every(fn($t) => isset($approvedByType[$t]));
  @endphp


@if(session('driver_creds'))
  @php $c = session('driver_creds'); @endphp
  <div class="alert alert-warning">
    <div class="d-flex justify-content-between align-items-start gap-2">
      <div>
        <div class="fw-bold mb-1">Credenciales de acceso (solo visible una vez)</div>
        <div class="small text-muted">Cópialas y guárdalas. No se podrán volver a mostrar.</div>

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
            {{-- el chofer entra activo, la verificación es posterior --}}
            Este conductor puede operar aun con verificación pendiente.
          </span>
        </div>

        @if(!empty($driver->verification_notes))
          <div class="text-danger small">
            <strong>Notas de revisión:</strong> {{ $driver->verification_notes }}
          </div>
        @endif

        <hr class="my-2">

        <div class="mb-1 small"><strong>Documentos requeridos para verificación:</strong></div>
        <div class="d-flex flex-wrap gap-2">
          @foreach($driverRequiredTypes ?? [] as $rt)
            @php
              $ok = isset($approvedByType[$rt]);
              $label = $driverDocTypesMap[$rt] ?? $rt;
            @endphp
            <span class="badge bg-{{ $ok ? 'success' : 'secondary' }} stat-pill">
              {{ $label }} {{ $ok ? '✓' : '—' }}
            </span>
          @endforeach
          {{-- opcional --}}
          @if(isset($driverDocTypesMap['foto_conductor']))
            @php
              $optOk = isset($approvedByType['foto_conductor']);
            @endphp
            <span class="badge bg-{{ $optOk ? 'success' : 'light text-muted' }} stat-pill">
              {{ $driverDocTypesMap['foto_conductor'] }} {{ $optOk ? '✓' : '(opcional)' }}
            </span>
          @endif
        </div>

        @if(!$requiredOkDriver)
          <div class="alert alert-info mt-3 mb-0 py-2 px-3 small">
            Sube licencia, identificación e imagen del conductor desde la sección de documentos.
            Estos seran validados por Orbana, como proceso de validacion de identidad  (Consulte aviso de privacidad).
          </div>
        @else
          <div class="alert alert-success mt-3 mb-0 py-2 px-3 small">
            Todos los documentos requeridos están cargados y aprobados. La cuenta puede considerarse verificada.
          </div>
        @endif
      </div>

      <div class="text-end">
        <a href="{{ route('drivers.documents.index', ['id' => $driver->id]) }}"
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
    <a href="{{ route('drivers.documents.index',['id'=>$driver->id]) }}" class="btn btn-sm btn-outline-primary">
      Ver / subir documentos
    </a>
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
          @php
  $hasUser = !empty($driver->user_id);

@endphp

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
  </div>

  <div class="d-flex gap-2">
    @if($hasUser)
      <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalResetPwd">
        <i data-feather="key"></i> Reset password
      </button>
    @else
      <a class="btn btn-sm btn-outline-primary" href="{{ route('drivers.edit',$driver->id) }}">
        <i data-feather="user-plus"></i> Crear usuario
      </a>
    @endif
  </div>
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
          <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalAssignVehicle">
            <i data-feather="link"></i> Asignar vehículo
          </button>
        </div>
        <div class="card-body">
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

            <form class="mt-3" method="post" action="{{ route('assignments.close',['id'=>$current->assignment_id]) }}">
              @csrf @method('PUT')
              <button class="btn btn-sm btn-outline-danger">
                <i data-feather="slash"></i> Cerrar asignación
              </button>
            </form>
          @else
            <div class="alert alert-info mb-0">Este conductor no tiene vehículo asignado actualmente.</div>
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
    <form class="modal-content" method="post" action="{{ route('drivers.assignVehicle',['id'=>$driver->id]) }}">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title">Asignar vehículo a {{ $driver->nombre ?? $driver->name ?? 'Conductor' }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <label class="form-label">Vehículo</label>
          <select class="form-select" name="vehicle_id" required>
            @foreach($vehiclesForSelect ?? [] as $veh)
              <option value="{{ $veh->id }}">#{{ $veh->economico }} — {{ $veh->brand }} {{ $veh->model }} ({{ $veh->plate ?: 'sin placa' }})</option>
            @endforeach
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">Inicio</label>
          <input type="datetime-local" name="start_at" class="form-control">
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="chkCloseConf" name="close_conflicts" value="1" checked>
          <label class="form-check-label" for="chkCloseConf">Cerrar asignaciones vigentes en conflicto</label>
        </div>
        <div class="mt-2">
          <label class="form-label">Nota (opcional)</label>
          <input type="text" class="form-control" name="note" maxlength="255">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" type="submit">Guardar</button>
      </div>
    </form>
  </div>
</div>

@if(!empty($linkedUser))
<div class="modal fade" id="modalResetPwd" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="{{ route('drivers.resetPassword', ['id'=>$driver->id]) }}">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title">Resetear contraseña</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-warning">
          Se generará una contraseña temporal nueva para <b>{{ $linkedUser->email }}</b>.
          La contraseña se mostrará una sola vez al guardar.
        </div>
        <p class="mb-0 small text-muted">
          Recomendación: el conductor debe cambiarla desde soporte o en un flujo futuro de “cambiar contraseña”.
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

@endsection
<script>
  async function copyText(elId){
    const el = document.getElementById(elId);
    if(!el) return;
    const text = el.innerText || el.textContent;
    try {
      await navigator.clipboard.writeText(text.trim());
    } catch (e) {
      // fallback
      const ta = document.createElement('textarea');
      ta.value = text.trim();
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
    }
  }
</script>
