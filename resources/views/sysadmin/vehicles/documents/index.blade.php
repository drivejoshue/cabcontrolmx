@extends('layouts.sysadmin')
@section('title','Docs vehículo · '.$vehicle->economico)

@section('content')
<div class="container-fluid">

  {{-- Encabezado --}}
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">
        Documentos del vehículo
      </h3>
      <div class="text-muted">
        Tenant: <b>{{ $tenant->name }}</b><br>
        Eco: <b>{{ $vehicle->economico }}</b> · Placa: <b>{{ $vehicle->plate }}</b>
        @php
          $vs = $vehicle->verification_status ?? 'pending';
          $vsBadge = $vs==='verified' ? 'success' : ($vs==='rejected' ? 'danger' : 'warning');
        @endphp
        · Status verificación:
        <span class="badge bg-{{ $vsBadge }}">{{ $vs }}</span>
        @if(!empty($vehicle->verification_notes))
          <span class="text-danger ms-2">
            <b>Notas:</b> {{ $vehicle->verification_notes }}
          </span>
        @endif
      </div>
    </div>

    <div class="d-flex gap-2">
      <a href="{{ route('sysadmin.verifications.index') }}" class="btn btn-outline-secondary">
        Cola de verificación
      </a>
      <a href="{{ route('sysadmin.verifications.vehicles.show', $vehicle->id) }}" class="btn btn-outline-secondary">
        Vista rápida de verificación
      </a>
    </div>
  </div>

  {{-- Alertas --}}
  @if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
  @endif
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

  @php
    // Mapeo de tipos que ya usas en el panel tenant
    $types = [
      'foto_vehiculo'             => 'Foto del vehículo',
      'placas'                    => 'Foto de placas',
      'cedula_transporte_publico' => 'Cédula / Transporte público (Taxi)',
      'tarjeta_circulacion'       => 'Tarjeta de circulación',
      'seguro'                    => 'Póliza (opcional)',
      'otro'                      => 'Otro documento',
    ];

    // Requeridos para considerar el taxi listo
    $required = ['foto_vehiculo','placas','cedula_transporte_publico','tarjeta_circulacion'];

    $approvedByType = collect($documents)
      ->where('status','approved')
      ->groupBy('type')
      ->map(fn($g) => $g->sortByDesc('id')->first());

    $requiredOk = collect($required)->every(fn($t) => isset($approvedByType[$t]));
  @endphp

  {{-- Resumen de requeridos --}}
  <div class="card mb-3">
    <div class="card-body">
      <div class="mb-2"><b>Requeridos para verificación:</b></div>
      <div class="d-flex flex-wrap gap-2">
        @foreach($required as $rt)
          @php $ok = isset($approvedByType[$rt]); @endphp
          <span class="badge bg-{{ $ok ? 'success' : 'secondary' }}">
            {{ $types[$rt] ?? $rt }} {{ $ok ? '✓' : '—' }}
          </span>
        @endforeach
      </div>

      @if(!$requiredOk)
        <div class="alert alert-info mt-3 mb-0">
          Faltan documentos requeridos o aún no están aprobados.
          Puedes subirlos manualmente o esperar a que el tenant los complete.
        </div>
      @else
        <div class="alert alert-success mt-3 mb-0">
          Todos los documentos requeridos están aprobados. El vehículo está listo
          para ser marcado como <b>verified</b> si lo consideras correcto.
        </div>
      @endif
    </div>
  </div>

  {{-- Alta manual de documento desde SysAdmin (opcional) --}}
  <div class="card mb-3">
    <div class="card-header">
      <b>Agregar documento (SysAdmin)</b>
    </div>
    <div class="card-body">
      <form method="POST"
            action="{{ route('sysadmin.vehicles.documents.store', ['tenant'=>$tenant->id,'vehicle'=>$vehicle->id]) }}"
            enctype="multipart/form-data">
        @csrf
        <div class="row g-2 align-items-end">
          <div class="col-md-3">
            <label class="form-label">Tipo</label>
            <select name="type" class="form-select" required>
              @foreach($types as $k => $label)
                <option value="{{ $k }}">{{ $label }}</option>
              @endforeach
              <option value="otro">Otro documento</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">No. de documento</label>
            <input type="text" name="document_no" class="form-control" value="{{ old('document_no') }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">Emisor</label>
            <input type="text" name="issuer" class="form-control" value="{{ old('issuer') }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">Archivo</label>
            <input type="file" name="file" class="form-control">
            <small class="text-muted">Máx 4 MB · Imagen o PDF</small>
          </div>
        </div>

        <div class="row g-2 mt-2">
          <div class="col-md-3">
            <label class="form-label">Fecha emisión</label>
            <input type="date" name="issue_date" class="form-control" value="{{ old('issue_date') }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">Fecha vencimiento</label>
            <input type="date" name="expiry_date" class="form-control" value="{{ old('expiry_date') }}">
          </div>
          <div class="col-md-3">
            <button class="btn btn-primary mt-4 w-100">Guardar documento</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  {{-- Tabla de documentos --}}
  <div class="card">
    <div class="card-header">
      <b>Documentos cargados</b>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped mb-0 align-middle">
          <thead>
            <tr>
              <th>Tipo</th>
              <th>Status</th>
              <th>No. doc</th>
              <th>Emisor</th>
              <th>Fechas</th>
              <th>Notas revisión</th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
            @forelse($documents as $doc)
              @php
                $badge = match($doc->status) {
                  'approved' => 'success',
                  'rejected' => 'danger',
                  'expired'  => 'secondary',
                  default    => 'warning',
                };
              @endphp
              <tr>
                <td>{{ $types[$doc->type] ?? $doc->type }}</td>
                <td>
                  <span class="badge bg-{{ $badge }}">{{ $doc->status }}</span>
                </td>
                <td>{{ $doc->document_no ?? '—' }}</td>
                <td>{{ $doc->issuer ?? '—' }}</td>
                <td class="small text-muted">
                  Emisión: {{ $doc->issue_date ?: '—' }}<br>
                  Vence: {{ $doc->expiry_date ?: '—' }}
                </td>
                <td class="small">{{ $doc->review_notes ?? '—' }}</td>
                <td class="text-end">
                  @if($doc->file_path)
                    <a href="{{ asset('storage/'.$doc->file_path) }}"
                       target="_blank"
                       class="btn btn-sm btn-outline-secondary">
                      Ver
                    </a>
                    <a href="{{ route('sysadmin.vehicle-documents.download', $doc->id) }}"
                       class="btn btn-sm btn-outline-secondary">
                      Descargar
                    </a>
                  @endif

                  {{-- Aprobar --}}
                  <form method="POST"
                        action="{{ route('sysadmin.vehicle-documents.review', $doc->id) }}"
                        class="d-inline">
                    @csrf
                    <input type="hidden" name="status" value="approved">
                    <input type="hidden" name="review_notes" value="">
                    <button class="btn btn-sm btn-success"
                            onclick="return confirm('¿Aprobar este documento?')">
                      Aprobar
                    </button>
                  </form>

                  {{-- Rechazar --}}
                  <form method="POST"
                        action="{{ route('sysadmin.vehicle-documents.review', $doc->id) }}"
                        class="d-inline mt-1">
                    @csrf
                    <input type="hidden" name="status" value="rejected">
                    <input type="hidden" name="review_notes" value="">
                    <button class="btn btn-sm btn-danger"
                            onclick="return confirm('¿Rechazar este documento?')">
                      Rechazar
                    </button>
                  </form>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="text-center text-muted py-3">
                  No hay documentos cargados aún.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
@endsection
