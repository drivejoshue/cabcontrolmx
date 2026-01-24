@extends('layouts.partner')
@section('title','Vehículo')

@push('styles')
<style>
  .thumb-md{width:96px;height:64px;object-fit:cover}
  .avatar-sm{width:40px;height:40px;object-fit:cover;border-radius:50%}

  /* Suavizar alertas por theme */
  :root{
    --p-card-border: rgba(0,0,0,.10);
    --p-soft-bg: rgba(0,0,0,.04);
    --p-help-bg: rgba(13,110,253,.10);
    --p-help-border: rgba(13,110,253,.28);
    --p-warn-bg: rgba(255,193,7,.16);
    --p-warn-border: rgba(255,193,7,.32);
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

  [data-bs-theme="dark"] .table-light{
    --tblr-table-bg: rgba(255,255,255,.05);
    --tblr-table-color: rgba(255,255,255,.78);
  }
</style>
@endpush

@section('content')
<div class="container-fluid p-0">

  {{-- Header + acciones --}}
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-3">
      <div class="rounded border bg-white d-flex align-items-center justify-content-center" style="width:60px;height:60px;">
        <i data-feather="truck"></i>
      </div>
      <div>
        @php
          $activo = (int)($v->active ?? 0);
          $vs = $v->verification_status ?? 'pending';
          $vsBadge = $vs === 'verified'
            ? 'success'
            : ($vs === 'rejected' ? 'danger' : 'warning');

          $vehicleDocsCount = (int)($vehicleDocsCount ?? 0);
        @endphp

        <h3 class="mb-0 d-flex flex-wrap align-items-center gap-2">
          <span>Económico #{{ $v->economico }}</span>

          {{-- Estado operativo --}}
          <span class="badge {{ $activo ? 'bg-success' : 'bg-secondary' }}">
            {{ $activo ? 'Activo' : 'Inactivo' }}
          </span>

          {{-- Estado de verificación --}}
          <span class="badge bg-{{ $vsBadge }}">
            Verificación: {{ $vs }}
          </span>
        </h3>

        <div class="text-muted mt-1">
          Placa: {{ $v->plate ?: '—' }} ·
          {{ $v->brand ?: '—' }} {{ $v->model ?: '' }}
          {{ $v->year ? '('.$v->year.')' : '' }}
        </div>

        <div class="text-muted small mt-1">
          Documentos cargados: <b>{{ $vehicleDocsCount }}</b>.
        </div>
      </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
      <a href="{{ route('partner.vehicles.documents.index', $v->id) }}" class="btn btn-outline-info">
        <i data-feather="file-text"></i>
        @if($vs === 'verified')
          Ver documentos
        @else
          Documentos / Verificación
        @endif
      </a>

      <a href="{{ route('partner.vehicles.edit', ['id'=>$v->id]) }}" class="btn btn-primary">
        <i data-feather="edit-2"></i> Editar
      </a>

      <a href="{{ route('partner.vehicles.index') }}" class="btn btn-outline-secondary">
        <i data-feather="arrow-left"></i> Volver
      </a>
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

  {{-- Recordatorio operativo (no bloquea): verificación recomendada --}}
  @if($vs !== 'verified')
    <div class="warn-callout mb-3">
      <div class="fw-semibold mb-1">Recordatorio de verificación</div>
      <div class="small text-muted">
        Este vehículo puede operar mientras está <b>{{ $vs }}</b>, pero se recomienda completar la verificación.
        En operación real, Orbana puede <b>suspender</b> vehículos no verificados o rechazados según políticas internas/seguridad.
      </div>
      <div class="mt-2">
        <a href="{{ route('partner.vehicles.documents.index', $v->id) }}" class="btn btn-sm btn-outline-dark">
          <i data-feather="file-text"></i> Subir / revisar documentos
        </a>
      </div>
    </div>
  @endif

  {{-- Pills --}}
  <ul class="nav nav-pills mb-3">
    <li class="nav-item">
      <a class="nav-link active" data-bs-toggle="tab" href="#tab-detalles">
        <i data-feather="info"></i> Detalles
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#tab-foto">
        <i data-feather="image"></i> Imagen
      </a>
    </li>
  </ul>

  <div class="tab-content">

    {{-- Detalles --}}
    <div class="tab-pane fade show active" id="tab-detalles">
      <div class="row g-3">

        {{-- Ficha técnica --}}
        <div class="col-12 col-xl-7">
          <div class="card h-100">
            <div class="card-header"><strong>Ficha técnica</strong></div>
            <div class="card-body">
              <dl class="row mb-0">
                <dt class="col-sm-4">Económico</dt>
                <dd class="col-sm-8">#{{ $v->economico }}</dd>

                <dt class="col-sm-4">Placa</dt>
                <dd class="col-sm-8">{{ $v->plate ?: '—' }}</dd>

                <dt class="col-sm-4">Marca</dt>
                <dd class="col-sm-8">{{ $v->brand ?: '—' }}</dd>

                <dt class="col-sm-4">Modelo</dt>
                <dd class="col-sm-8">{{ $v->model ?: '—' }}</dd>

                <dt class="col-sm-4">Color</dt>
                <dd class="col-sm-8">{{ $v->color ?: '—' }}</dd>

                <dt class="col-sm-4">Año</dt>
                <dd class="col-sm-8">{{ $v->year ?: '—' }}</dd>

                <dt class="col-sm-4">Capacidad</dt>
                <dd class="col-sm-8">{{ $v->capacity ?: '—' }}</dd>

                <dt class="col-sm-4">Tipo</dt>
                <dd class="col-sm-8">{{ $v->type ? strtoupper($v->type) : '—' }}</dd>

                <dt class="col-sm-4">Póliza / ID</dt>
                <dd class="col-sm-8">{{ $v->policy_id ?: '—' }}</dd>

                <dt class="col-sm-4">Estado</dt>
                <dd class="col-sm-8">
                  @if($activo)
                    <span class="badge bg-success">Activo</span>
                  @else
                    <span class="badge bg-secondary">Inactivo</span>
                  @endif
                </dd>

                <dt class="col-sm-4">Verificación</dt>
                <dd class="col-sm-8">
                  <span class="badge bg-{{ $vsBadge }}">{{ $vs }}</span>
                  @if(!empty($v->verification_notes))
                    <div class="small text-danger mt-1">
                      {{ $v->verification_notes }}
                    </div>
                  @else
                    @if($vs === 'pending')
                      <div class="small text-muted mt-1">
                        Documentos en revisión por Orbana. Proceso de verificación de identidad vehicular.
                      </div>
                    @elseif($vs === 'verified')
                      <div class="small text-muted mt-1">
                        Vehículo verificado. Identidad confirmada por Orbana.
                      </div>
                    @elseif($vs === 'rejected')
                      <div class="small text-muted mt-1">
                        Verificación rechazada. Revisa documentos y vuelve a subirlos desde la sección de Documentos.
                      </div>
                    @endif
                  @endif
                </dd>

                <dt class="col-sm-4">Creado</dt>
                <dd class="col-sm-8">{{ $v->created_at ?? '—' }}</dd>

                <dt class="col-sm-4">Actualizado</dt>
                <dd class="col-sm-8">{{ $v->updated_at ?? '—' }}</dd>
              </dl>

              <div class="help-callout small mt-3">
                Nota: Sube el documento que avala que la unidad registrar es TAXI para poder asignar un conductor.
              </div>
            </div>
          </div>
        </div>

        {{-- Resumen + mini foto + verificación/documentos --}}
        <div class="col-12 col-xl-5">
          <div class="card h-100">
            <div class="card-header"><strong>Resumen</strong></div>
            <div class="card-body">
              <div class="d-flex align-items-center gap-3 mb-3">
                @php
                  $foto = null;
                  if (!empty($v->foto_path))    { $foto = asset('storage/'.$v->foto_path); }
                  elseif (!empty($v->photo_url)){ $foto = $v->photo_url; }
                @endphp
                <div class="flex-shrink-0">
                  @if($foto)
                    <img src="{{ $foto }}" class="rounded border thumb-md" alt="Foto vehículo">
                  @else
                    <div class="rounded border bg-light d-flex align-items-center justify-content-center thumb-md">
                      <span class="text-muted small">Sin foto</span>
                    </div>
                  @endif
                </div>
                <div class="flex-grow-1">
                  <div class="mb-1"><strong>#{{ $v->economico }}</strong></div>
                  <div class="text-muted small">
                    {{ $v->brand ?: '—' }} {{ $v->model ?: '' }} {{ $v->year ? '('.$v->year.')' : '' }}
                  </div>
                  <div class="text-muted small">
                    Placa: {{ $v->plate ?: '—' }}
                  </div>
                  <div class="text-muted small">
                    Docs: <b>{{ $vehicleDocsCount }}</b> (opcionales)
                  </div>
                </div>
              </div>
{{-- =========================
   Acciones Operativas (ACTIVAR / SUSPENDER)
   ========================= --}}
@php
  $activo = (int)($v->active ?? 0);
  $vs = $v->verification_status ?? 'pending';

  $hasCurrentDrivers = (isset($currentDrivers) && $currentDrivers instanceof \Illuminate\Support\Collection)
    ? ($currentDrivers->count() > 0)
    : (!empty($currentDrivers) && count($currentDrivers) > 0);

  $canActivateByUi = ($activo === 0);
  // Recomendación UI: exigir verificado + chofer asignado antes de permitir activar
  // (aunque el backend debe ser el guardián real)
  $uiBlockReason = null;
  if ($canActivateByUi) {
    if ($vs !== 'verified') {
      $uiBlockReason = 'Para activar se requiere verificación en estado VERIFIED.';
    } elseif (!$hasCurrentDrivers) {
      $uiBlockReason = 'Para activar se requiere al menos 1 chofer asignado vigente.';
    }
  }
@endphp

<div class="card mb-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <strong>Estado operativo y cobro</strong>
    <span class="badge {{ $activo ? 'bg-success' : 'bg-secondary' }}">
      {{ $activo ? 'ACTIVO' : 'INACTIVO' }}
    </span>
  </div>

  <div class="card-body">
    @if($activo)
      <div class="alert alert-success mb-3">
        <div class="fw-semibold mb-1">Vehículo ACTIVO</div>
        <div class="small">
          Este vehículo está habilitado para operar. <b>Cuenta para cobro</b> en el esquema de partners.
        </div>
      </div>
    @else
      <div class="alert alert-secondary mb-3">
        <div class="fw-semibold mb-1">Vehículo INACTIVO</div>
        <div class="small">
          Este vehículo está en modo borrador/registro. <b>No cuenta para cobro</b> hasta que lo actives manualmente.
        </div>
      </div>
    @endif

    <div class="soft-box p-3 mb-3">
      <div class="fw-semibold mb-1">Distinción importante</div>
      <ul class="small text-muted mb-0">
        <li><b>Subir documentos</b> NO activa el vehículo automáticamente.</li>
        <li><b>Asignar un chofer</b> NO activa el vehículo automáticamente.</li>
        <li><b>Activar</b> es una acción manual y explícita. Al activar, <b>inicia el cobro</b> (si aplica).</li>
        <li><b>Suspender</b> desactiva la unidad y deja de contar para cobro futuro (según tu lógica de facturación).</li>
      </ul>
    </div>

    <div class="d-flex flex-wrap gap-2">
      @if($activo === 0)
        <button
          type="button"
          class="btn btn-success"
          data-bs-toggle="modal"
          data-bs-target="#modalActivateVehicle"
          {{ $uiBlockReason ? 'disabled' : '' }}
        >
          <i data-feather="check-circle"></i> Activar vehículo
        </button>

        @if($uiBlockReason)
          <div class="small text-danger mt-1">
            <i data-feather="alert-triangle"></i>
            {{ $uiBlockReason }}
          </div>
        @else
          <div class="small text-muted mt-1">
            <i data-feather="info"></i>
            Se pedirá confirmación explícita antes de activar.
          </div>
        @endif
      @else
        <button
          type="button"
          class="btn btn-outline-danger"
          data-bs-toggle="modal"
          data-bs-target="#modalSuspendVehicle"
        >
          <i data-feather="pause-circle"></i> Suspender vehículo
        </button>

        <div class="small text-muted mt-1">
          <i data-feather="info"></i>
          Suspender deshabilita la unidad. Se pedirá confirmación explícita.
        </div>
      @endif
    </div>
  </div>
</div>

              {{-- Bloque verificación / documentos --}}
              <div class="alert alert-{{ $vs === 'verified' ? 'success' : ($vs === 'rejected' ? 'danger' : 'warning') }} mb-3">
                <div class="fw-semibold mb-1">Estado de verificación</div>
                <div class="small mb-2">
                  @if($vs === 'pending')
                    Documentos en proceso de revisión por Orbana. El taxi puede operar mientras se verifica.
                  @elseif($vs === 'verified')
                    Vehículo verificado por Orbana. Identidad confirmada.
                  @elseif($vs === 'rejected')
                    Verificación rechazada. Revisa notas y vuelve a subir documentación corregida.
                  @endif
                </div>
                <a href="{{ route('partner.vehicles.documents.index',$v->id) }}" class="btn btn-sm btn-outline-dark">
                  <i data-feather="file-text"></i> Ver / subir documentos
                </a>
              </div>

              {{-- Recordatorio de asignación de chofer --}}
              <div class="alert alert-secondary mb-0">
                <div class="fw-semibold mb-1">Asignación de chofer</div>
                <div class="small mb-2">
                  La asignación se realiza desde la ficha del conductor
                  (Conductores → Ver → “Asignar vehículo”).
                </div>
                <a href="{{ route('partner.drivers.index') }}" class="btn btn-sm btn-outline-primary">
                  Ir a Conductores
                </a>
              </div>
            </div>
          </div>
        </div>

        {{-- Choferes asignados (vigentes) --}}
        <div class="col-12">
          <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
              <strong>Chofer(es) asignado(s) actualmente</strong>
              <span class="text-muted small">Vigentes = end_at NULL</span>
            </div>
            <div class="card-body">
              @if(($currentDrivers ?? collect())->count())
                <div class="table-responsive">
                  <table class="table table-sm align-middle">
                    <thead class="table-light">
                      <tr>
                        <th>Chofer</th>
                        <th>Teléfono</th>
                        <th>Desde</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($currentDrivers as $cd)
                        <tr>
                          <td>
                            <div class="d-flex align-items-center gap-2">
                              @php $df = !empty($cd->foto_path) ? asset('storage/'.$cd->foto_path) : null; @endphp
                              @if($df)
                                <img src="{{ $df }}" class="avatar-sm border" alt="">
                              @else
                                <div class="avatar-sm border d-flex align-items-center justify-content-center bg-light">
                                  <i data-feather="user"></i>
                                </div>
                              @endif
                              <a href="{{ route('partner.drivers.show',$cd->driver_id) }}" class="text-decoration-none">
                                {{ $cd->name }}
                              </a>
                            </div>
                          </td>
                          <td>{{ $cd->phone ?? '—' }}</td>
                          <td>{{ $cd->start_at }}</td>
                          <td class="text-end">
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('partner.drivers.show',$cd->driver_id) }}">
                              Ver conductor
                            </a>
                          </td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              @else
                <div class="alert alert-info mb-0">
                  Este vehículo no tiene chofer asignado actualmente.
                </div>
              @endif
            </div>
          </div>
        </div>

        {{-- Histórico --}}
        <div class="col-12">
          <div class="card">
            <div class="card-header"><strong>Histórico de asignaciones</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                  <thead class="table-light">
                    <tr>
                      <th>Chofer</th>
                      <th>Teléfono</th>
                      <th>Desde</th>
                      <th>Hasta</th>
                    </tr>
                  </thead>
                  <tbody>
                    @forelse($assignments ?? [] as $a)
                      <tr>
                        <td>
                          <a class="text-decoration-none" href="{{ route('partner.drivers.show',$a->driver_id) }}">
                            {{ $a->name }}
                          </a>
                        </td>
                        <td>{{ $a->phone ?? '—' }}</td>
                        <td>{{ $a->start_at }}</td>
                        <td>{{ $a->end_at ?? 'Vigente' }}</td>
                      </tr>
                    @empty
                      <tr>
                        <td colspan="4" class="text-center text-muted">
                          Sin registros
                        </td>
                      </tr>
                    @endforelse
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

      </div> {{-- row --}}
    </div> {{-- tab-detalles --}}

    {{-- Imagen --}}
    <div class="tab-pane fade" id="tab-foto">
      <div class="card">
        <div class="card-header"><strong>Imagen del vehículo</strong></div>
        <div class="card-body">
          @php
            $foto = null;
            if (!empty($v->foto_path))    { $foto = asset('storage/'.$v->foto_path); }
            elseif (!empty($v->photo_url)){ $foto = $v->photo_url; }
          @endphp

          @if($foto)
            <div class="text-center">
              <img src="{{ $foto }}" class="img-fluid rounded border" style="max-height:440px;object-fit:contain;">
            </div>
          @else
            <div class="alert alert-info mb-0">
              Este vehículo no tiene imagen cargada todavía.
            </div>
          @endif

          <div class="mt-3 d-flex gap-2">
            <a href="{{ route('partner.vehicles.edit', ['id'=>$v->id]) }}" class="btn btn-primary">
              <i data-feather="upload"></i> Subir/Reemplazar foto
            </a>
            <a href="{{ route('partner.vehicles.index') }}" class="btn btn-outline-secondary">
              <i data-feather="arrow-left"></i> Volver al listado
            </a>
          </div>
        </div>
      </div>
    </div> {{-- tab-foto --}}

  </div> {{-- tab-content --}}


  {{-- =========================
   MODAL: ACTIVAR
   ========================= --}}
<div class="modal fade" id="modalActivateVehicle" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirmar ACTIVACIÓN</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <div class="alert alert-warning">
          <div class="fw-semibold mb-1">Acción delicada</div>
          <div class="small">
            Estás a punto de <b>ACTIVAR</b> el vehículo <b>#{{ $v->economico }}</b>.
            Esta acción es manual y explícita.
          </div>
        </div>

        <div class="soft-box p-3 mb-3">
          <div class="fw-semibold mb-1">Qué sucede al ACTIVAR</div>
          <ul class="small text-muted mb-0">
            <li>El vehículo pasa a estado <b>ACTIVO</b>.</li>
            <li>El vehículo <b>empieza a contar para cobro</b> desde hoy cn fecha corte dia 1 de cada mes .</li>
            <li>Se registra la activación para facturación / auditoría.</li>
          </ul>
        </div>

        <div class="soft-box p-3 mb-3">
          <div class="fw-semibold mb-1">Qué NO sucede al ACTIVAR</div>
          <ul class="small text-muted mb-0">
            <li>No se crean ni modifican asignaciones de chofer automáticamente.</li>
            <li>No se “arreglan” documentos pendientes: la verificación es independiente.</li>
          </ul>
        </div>

        <div class="small text-muted mb-2">
          Requisitos esperados para activar:
          <ul class="mb-0">
            <li>Verificación: <b>verified</b></li>
            <li>Chofer asignado: <b>sí</b></li>
            <li>Saldo suficiente: <b>sí</b> </li>
          </ul>
        </div>

        <div class="form-check mt-3">
          <input class="form-check-input" type="checkbox" id="chkConfirmActivate">
          <label class="form-check-label" for="chkConfirmActivate">
            Confirmo que entiendo que <b>ACTIVAR inicia el cobro</b> (si aplica) y deseo continuar.
          </label>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          Cancelar
        </button>

        <form method="POST" action="{{ route('partner.vehicles.activate', ['id'=>$v->id]) }}" class="m-0">
          @csrf
          <button type="submit" class="btn btn-success" id="btnConfirmActivate" disabled>
            Sí, activar ahora
          </button>
        </form>
      </div>
    </div>
  </div>
</div>



{{-- =========================
   MODAL: SUSPENDER
   ========================= --}}
<div class="modal fade" id="modalSuspendVehicle" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirmar SUSPENSIÓN</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <div class="alert alert-danger">
          <div class="fw-semibold mb-1">Acción delicada</div>
          <div class="small">
            Estás a punto de <b>SUSPENDER</b> el vehículo <b>#{{ $v->economico }}</b>.
          </div>
        </div>

        <div class="soft-box p-3 mb-3">
          <div class="fw-semibold mb-1">Qué sucede al SUSPENDER</div>
          <ul class="small text-muted mb-0">
            <li>El vehículo pasa a estado <b>INACTIVO</b>.</li>
            <li>Deja de estar habilitado para operar como unidad activa.</li>
            <li>Al ser suspendido, el vehículo <b>deja de contar para cobro futuro</b></li>
          </ul>
        </div>

        <div class="soft-box p-3 mb-3">
          <div class="fw-semibold mb-1">Qué NO sucede al SUSPENDER</div>
          <ul class="small text-muted mb-0">
            <li>No borra el vehículo ni elimina históricos.</li>
            <li>No elimina documentos ni asignaciones históricas.</li>
          </ul>
        </div>

        <div class="form-check mt-3">
          <input class="form-check-input" type="checkbox" id="chkConfirmSuspend">
          <label class="form-check-label" for="chkConfirmSuspend">
            Confirmo que deseo <b>SUSPENDER</b> el vehículo y entiendo el impacto operativo.
          </label>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          Cancelar
        </button>

        <form method="POST" action="{{ route('partner.vehicles.suspend', ['id'=>$v->id]) }}" class="m-0">
          @csrf
          <button type="submit" class="btn btn-danger" id="btnConfirmSuspend" disabled>
            Sí, suspender
          </button>
        </form>
      </div>
    </div>
  </div>
</div>




</div>
@endsection
@push('scripts')
<script>
(function () {
  function bindCheckbox(checkboxId, buttonId) {
    var chk = document.getElementById(checkboxId);
    var btn = document.getElementById(buttonId);
    if (!chk || !btn) return;

    function sync() {
      btn.disabled = !chk.checked;
    }
    chk.addEventListener('change', sync);
    sync();

    // Cuando se abra el modal, reiniciar checkbox para forzar confirmación cada vez
    var modalEl = chk.closest('.modal');
    if (modalEl) {
      modalEl.addEventListener('show.bs.modal', function () {
        chk.checked = false;
        sync();
      });
    }
  }

  bindCheckbox('chkConfirmActivate', 'btnConfirmActivate');
  bindCheckbox('chkConfirmSuspend', 'btnConfirmSuspend');
})();
</script>
@endpush
