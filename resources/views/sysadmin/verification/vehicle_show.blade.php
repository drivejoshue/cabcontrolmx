@extends('layouts.sysadmin')
@section('title','Verificación de vehículo')

@push('styles')
<style>
  .doc-preview-img {
    max-width: 200px;
    max-height: 140px;
    object-fit: cover;
    border-radius: 4px;
  }
  .doc-preview-frame {
    width: 100%;
    height: 320px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
  }
</style>
@endpush

@section('content')
<div class="container-fluid">

  {{-- Header --}}
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">
        Verificación de vehículo
        @php
          $status = $v->verification_status ?? 'pending';
          $statusLower = strtolower($status);
          $badge = match($statusLower) {
            'verified' => 'success',
            'rejected' => 'danger',
            default    => 'warning',
          };
        @endphp
        <span class="badge bg-{{ $badge }}">{{ $statusLower }}</span>
      </h3>
      <div class="text-muted small">
        Tenant #{{ $v->tenant_id }} · Vehículo #{{ $v->id }}
      </div>
      <div class="text-muted mt-1">
        Eco: <strong>{{ $v->economico }}</strong> · Placa: <strong>{{ $v->plate }}</strong><br>
        {{ $v->brand ?: '—' }} {{ $v->model ?: '' }} {{ $v->year ? '('.$v->year.')' : '' }}
      </div>
      @if(!empty($v->verification_notes))
        <div class="mt-1 text-danger">
          <strong>Notas actuales:</strong> {{ $v->verification_notes }}
        </div>
      @endif
    </div>

    <div class="d-flex gap-2">
      <a href="{{ route('sysadmin.verifications.index') }}" class="btn btn-outline-secondary">
        Volver a la cola
      </a>
      {{-- Opcional: link directo a documentos del tenant/vehículo --}}
      <a href="{{ route('sysadmin.vehicles.documents.index', ['tenant'=>$v->tenant_id, 'vehicle'=>$v->id]) }}"
         class="btn btn-outline-primary">
        Ver documentos (vista completa)
      </a>
    </div>
  </div>

  @if(session('ok'))
    <div class="alert alert-success">{{ session('ok') }}</div>
  @endif
  @if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
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
    // Mapeo de tipos (igual que en Admin)
    $typeLabels = [
      'foto_vehiculo'              => 'Foto del vehículo',
      'placas'                     => 'Foto de placas',
      'cedula_transporte_publico'  => 'Cédula / Transporte público (Taxi)',
      'tarjeta_circulacion'        => 'Tarjeta de circulación',
      'seguro'                     => 'Póliza (opcional)',
    ];

    // Último doc aprobado por tipo
    $approvedByType = collect($docs)
      ->where('status','approved')
      ->groupBy('type')
      ->map(fn($g)=>$g->sortByDesc('id')->first());

    $requiredOk = collect($required)->every(fn($t)=>isset($approvedByType[$t]));
  @endphp

  <div class="row g-3 mb-3">
    <div class="col-12 col-lg-6">
      <div class="card h-100">
        <div class="card-header">
          <strong>Resumen de verificación</strong>
        </div>
        <div class="card-body">
          <div class="mb-2">
            <strong>Estado actual:</strong>
            <span class="badge bg-{{ $badge }}">{{ $statusLower }}</span>
          </div>

          <div class="mb-3">
            <strong>Documentos requeridos:</strong>
            <div class="mt-2 d-flex flex-wrap gap-2">
              @foreach($required as $rt)
                @php
                  $ok = isset($approvedByType[$rt]);
                  $lbl = $typeLabels[$rt] ?? $rt;
                @endphp
                <span class="badge bg-{{ $ok ? 'success' : 'secondary' }}">
                  {{ $lbl }} {{ $ok ? '✓' : '—' }}
                </span>
              @endforeach
            </div>
          </div>

          @if(!$requiredOk)
            <div class="alert alert-warning mb-0">
              Faltan documentos requeridos aprobados.  
              El vehículo seguirá <strong>pendiente</strong> hasta que
              "Foto del vehículo", "Placas" y "Cédula / Transporte público" estén aprobados.
            </div>
          @elseif($statusLower !== 'verified')
            <div class="alert alert-info mb-0">
              Todos los documentos requeridos están aprobados.  
              Al aprobar/recalcular los documentos el sistema puede marcar este vehículo como
              <strong>verified</strong> automáticamente.
            </div>
          @else
            <div class="alert alert-success mb-0">
              Este vehículo está <strong>verificado</strong>.  
              Si rechazas algún documento requerido, se marcará como <strong>rejected</strong> o <strong>pending</strong>
              según corresponda.
            </div>
          @endif
        </div>
      </div>
    </div>

    {{-- Foto rápida si existe --}}
    <div class="col-12 col-lg-6">
      <div class="card h-100">
        <div class="card-header">
          <strong>Foto rápida del vehículo</strong>
        </div>
        <div class="card-body">
          @php
            $foto = null;
            if (!empty($v->foto_path))    { $foto = asset('storage/'.$v->foto_path); }
            elseif (!empty($v->photo_url)){ $foto = $v->photo_url; }
          @endphp

          @if($foto)
            <div class="text-center">
              <img src="{{ $foto }}" class="img-fluid rounded border"
                   style="max-height:260px;object-fit:contain;" alt="Foto vehículo">
            </div>
          @else
            <div class="alert alert-secondary mb-0">
              El tenant aún no ha subido una foto al campo principal del vehículo.
              Puedes revisar las imágenes en la tabla de documentos.
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- Tabla de documentos --}}
  <div class="card">
    <div class="card-header">
      <strong>Documentos del vehículo</strong>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>Tipo</th>
              <th>Status</th>
              <th>No. / Emisor</th>
              <th>Fechas</th>
              <th>Archivo</th>
              <th>Revisión</th>
            </tr>
          </thead>
          <tbody>
          @forelse($docs as $d)
            @php
              $status = $d->status ?? 'pending';
              $statusLower = strtolower($status);
              $badgeDoc = match($statusLower) {
                'approved' => 'success',
                'rejected' => 'danger',
                'expired'  => 'secondary',
                default    => 'warning',
              };
              $label = $typeLabels[$d->type] ?? $d->type;
              $url = $d->file_path ? asset('storage/'.$d->file_path) : null;
              $ext = $d->file_path ? strtolower(pathinfo($d->file_path, PATHINFO_EXTENSION)) : null;
              $isImage = in_array($ext, ['jpg','jpeg','png','gif','webp']);
              $isPdf   = $ext === 'pdf';
            @endphp
            <tr>
              <td>
                <div>{{ $label }}</div>
                <div class="small text-muted">Tipo: {{ $d->type }}</div>
              </td>
              <td>
                <span class="badge bg-{{ $badgeDoc }}">{{ $statusLower }}</span>
                @if($d->reviewed_at)
                  <div class="small text-muted mt-1">
                    Rev: {{ $d->reviewed_at }}
                  </div>
                @endif
              </td>
              <td class="small">
                @if($d->document_no)
                  <div><strong>No:</strong> {{ $d->document_no }}</div>
                @endif
                @if($d->issuer)
                  <div><strong>Emisor:</strong> {{ $d->issuer }}</div>
                @endif
                @if(!$d->document_no && !$d->issuer)
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td class="small">
                @if($d->issue_date)
                  <div><strong>Emisión:</strong> {{ $d->issue_date }}</div>
                @endif
                @if($d->expiry_date)
                  <div><strong>Vence:</strong> {{ $d->expiry_date }}</div>
                @endif
                @if(!$d->issue_date && !$d->expiry_date)
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td style="width:280px;">
                @if($url)
                  @if($isImage)
                    <a href="{{ $url }}" target="_blank" class="d-inline-block mb-1">
                      <img src="{{ $url }}" class="doc-preview-img border" alt="Doc">
                    </a>
                    <div class="small">
                      <a href="{{ route('sysadmin.vehicle-documents.download', $d->id) }}">
                        Descargar
                      </a>
                    </div>
                  @elseif($isPdf)
                    <iframe src="{{ $url }}" class="doc-preview-frame mb-1"></iframe>
                    <div class="small">
                      <a href="{{ route('sysadmin.vehicle-documents.download', $d->id) }}">
                        Descargar PDF
                      </a>
                    </div>
                  @else
                    <div class="small mb-1">
                      <i class="bi bi-file-earmark"></i> Archivo: {{ $ext ?: 'desconocido' }}
                    </div>
                    <a href="{{ route('sysadmin.vehicle-documents.download', $d->id) }}" class="btn btn-sm btn-outline-secondary">
                      Descargar
                    </a>
                  @endif
                @else
                  <span class="text-muted small">Sin archivo</span>
                @endif
              </td>
              <td style="width:320px;">
                <form method="POST"
                      action="{{ route('sysadmin.verifications.vehicle_docs.review', $d->id) }}">
                  @csrf
                  <div class="input-group input-group-sm mb-1">
                    <select name="action" class="form-select">
                      <option value="approve" {{ $statusLower === 'approved' ? 'selected' : '' }}>Aprobar</option>
                      <option value="reject"  {{ $statusLower === 'rejected' ? 'selected' : '' }}>Rechazar</option>
                    </select>
                    <button class="btn btn-primary" type="submit">
                      Guardar
                    </button>
                  </div>
                  <input type="text" name="notes"
                         class="form-control form-control-sm"
                         placeholder="Notas para el tenant (opcional)"
                         value="{{ old('notes', $d->review_notes) }}">
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-center text-muted py-3">
                Este vehículo aún no tiene documentos cargados.
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
