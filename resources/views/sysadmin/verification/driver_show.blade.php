@extends('layouts.sysadmin')
@section('title','Verificación de conductor')

@push('styles')
<style>
  .driver-avatar {
    width: 84px; height: 84px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(0,0,0,.1);
  }
  .doc-thumb {
    max-width: 200px;
    max-height: 140px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid #e9ecef;
  }
  .doc-frame {
    width: 100%;
    height: 320px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
  }
</style>
@endpush

@section('content')
<div class="container-fluid">

  {{-- Header --}}
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-3">
      @php
        // Avatar desde drivers.foto_path (storage/app/public/*) o fallback opcional a photo_url si existiera
        $avatar = null;
        if (!empty($d->foto_path))      { $avatar = asset('storage/'.$d->foto_path); }
        elseif (!empty($d->photo_url))  { $avatar = $d->photo_url; } // solo si esa columna existe en tu schema
      @endphp

      @if($avatar)
        <img src="{{ $avatar }}" alt="Foto del conductor" class="driver-avatar">
      @endif

      <div>
        <h3 class="mb-0">
          Conductor: {{ $d->name }}
          @php
            $vs = $d->verification_status ?? 'pending';
            $vsBadge = $vs === 'verified' ? 'success' : ($vs === 'rejected' ? 'danger' : 'warning');
          @endphp
          <span class="badge bg-{{ $vsBadge }} align-middle">{{ $vs }}</span>
        </h3>
        <div class="text-muted small">
          ID: #{{ $d->id }} · Tenant ID: {{ $d->tenant_id }}
        </div>
        <div class="text-muted small">
          Tel: {{ $d->phone ?: '—' }} · Email: {{ $d->email ?: '—' }}
        </div>
        @if(!empty($d->verification_notes))
          <div class="mt-1 text-danger small"><strong>Notas de verificación:</strong> {{ $d->verification_notes }}</div>
        @endif
      </div>
    </div>

    <div class="d-flex gap-2">
      <a href="{{ route('sysadmin.verifications.index') }}" class="btn btn-outline-secondary">Volver a cola de verificación</a>
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

  @php
    $types = [
      'foto_driver'            => 'Foto del conductor',
      'ine'                    => 'Identificación oficial (INE)',
      'licencia'               => 'Licencia de conducir',
      'comprobante_domicilio'  => 'Comprobante de domicilio',
      'otro'                   => 'Otro',
    ];
    $required = $required ?? ['foto_driver','ine','licencia'];

    // Aprobados por tipo (último aprobado)
    $approvedByType = collect($docs)
      ->where('status','approved')
      ->groupBy('type')
      ->map(fn($g) => $g->sortByDesc('id')->first());

    $requiredOk = collect($required)->every(fn($t) => isset($approvedByType[$t]));
  @endphp

  <div class="row g-3 mb-3">
    <div class="col-12 col-lg-6">
      <div class="card h-100">
        <div class="card-header"><strong>Resumen de verificación</strong></div>
        <div class="card-body">
          <div class="mb-2">
            <strong>Documentos requeridos:</strong>
          </div>
          <div class="d-flex flex-wrap gap-2 mb-3">
            @foreach($required as $rt)
              @php $ok = isset($approvedByType[$rt]); @endphp
              <span class="badge bg-{{ $ok ? 'success' : 'secondary' }}">
                {{ $types[$rt] ?? $rt }} {{ $ok ? '✓' : '—' }}
              </span>
            @endforeach
          </div>

          @if(!$requiredOk)
            <div class="alert alert-warning mb-0">
              Faltan documentos requeridos o están pendientes/rechazados. Ajusta los estados para avanzar.
            </div>
          @else
            <div class="alert alert-success mb-0">
              Requeridos completos. Puedes dejar el conductor como <strong>verified</strong>.
            </div>
          @endif
        </div>
      </div>
    </div>

    {{-- Foto del conductor (desde driver o desde docs) --}}
    <div class="col-12 col-lg-6">
      <div class="card h-100">
        <div class="card-header"><strong>Foto del conductor</strong></div>
        <div class="card-body">
          @php
            // si no hay avatar directo, intenta con el doc más reciente tipo foto_driver
            $fotoDoc = null;
            if (!$avatar) {
              $fotoDoc = collect($docs)
                ->filter(fn($x) => $x->type === 'foto_driver' && $x->file_path)
                ->sortByDesc('id')
                ->first();
            }
            $fotoUrl = $avatar ?: ($fotoDoc ? asset('storage/'.$fotoDoc->file_path) : null);
          @endphp

          @if($fotoUrl)
            <div class="text-center">
              <img src="{{ $fotoUrl }}" class="img-fluid rounded border" style="max-height:260px;object-fit:contain;" alt="Foto conductor">
            </div>
          @else
            <div class="alert alert-secondary mb-0">
              No hay foto del conductor disponible aún.
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- Tabla de documentos --}}
  <div class="card">
    <div class="card-header"><strong>Documentos cargados del conductor</strong></div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped align-middle mb-0">
          <thead>
            <tr>
              <th>Tipo</th>
              <th>Status</th>
              <th>Notas de revisión</th>
              <th>Fechas</th>
              <th style="width:320px" class="text-end">Acciones / Archivo</th>
            </tr>
          </thead>
          <tbody>
          @forelse($docs as $doc)
            @php
              $badge = $doc->status === 'approved' ? 'success' : ($doc->status === 'rejected' ? 'danger' : 'warning');
              $viewRoute     = route('sysadmin.driver-documents.view', $doc->id);
              $downloadRoute = route('sysadmin.driver-documents.download', $doc->id);

              $url = $doc->file_path ? asset('storage/'.$doc->file_path) : null;
              $ext = $doc->file_path ? strtolower(pathinfo($doc->file_path, PATHINFO_EXTENSION)) : null;
              $isImage = in_array($ext, ['jpg','jpeg','png','gif','webp']);
              $isPdf   = $ext === 'pdf';
            @endphp
            <tr>
              <td>{{ $types[$doc->type] ?? $doc->type }}</td>
              <td><span class="badge bg-{{ $badge }}">{{ $doc->status }}</span></td>
              <td class="text-muted small">{{ $doc->review_notes ?: '—' }}</td>
              <td class="small text-muted">
                @if(!empty($doc->issue_date))   <div>Emisión: {{ $doc->issue_date }}</div>@endif
                @if(!empty($doc->expiry_date))  <div>Vence:   {{ $doc->expiry_date }}</div>@endif
                @if(!empty($doc->reviewed_at))  <div>Revisado: {{ $doc->reviewed_at }}</div>@endif
              </td>
              <td class="text-end">
                {{-- Archivo (preview si aplica) --}}
                @if($url)
                  @if($isImage)
                    <a href="{{ $url }}" target="_blank" class="d-inline-block me-2 align-middle">
                      <img src="{{ $url }}" alt="Doc" class="doc-thumb">
                    </a>
                  @elseif($isPdf)
                    <iframe src="{{ $url }}" class="doc-frame mb-2 d-block"></iframe>
                  @endif
                @endif

                {{-- Acciones --}}
                <div class="d-inline-block align-top" style="max-width:260px">
                  <div class="mb-1">
                    <a href="{{ $viewRoute }}" target="_blank" class="btn btn-sm btn-outline-secondary">Ver</a>
                    <a href="{{ $downloadRoute }}" class="btn btn-sm btn-outline-secondary">Descargar</a>
                  </div>

                  <form method="POST" action="{{ route('sysadmin.verifications.driver_docs.review', $doc->id) }}">
                    @csrf
                    <input type="text" name="notes" class="form-control form-control-sm mb-1"
                           placeholder="Notas (opcional)" value="{{ $doc->review_notes }}">
                    <div class="btn-group btn-group-sm w-100" role="group">
                      <button type="submit" name="action" value="approve" class="btn btn-success">Aprobar</button>
                      <button type="submit" name="action" value="reject"  class="btn btn-outline-danger">Rechazar</button>
                    </div>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center py-3 text-muted">Aún no hay documentos cargados.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
@endsection
