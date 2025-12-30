{{-- resources/views/sysadmin/verification/vehicle_show.blade.php --}}
@extends('layouts.sysadmin')
@section('title','Verificación de vehículo')

@push('styles')
<style>
  .doc-preview-img{max-width:200px;max-height:140px;object-fit:cover;border-radius:6px}
  .doc-preview-frame{width:100%;height:320px;border:1px solid #e9ecef;border-radius:6px}
  .pill{border-radius:999px;padding:.25rem .6rem;font-size:.75rem}
</style>
@endpush

@section('content')
<div class="container-fluid">

  {{-- Encabezado / Toolbar superior --}}
  <div class="page-header d-print-none mb-3">
    <div class="row align-items-center">
      <div class="col">
        @php
          $status = $v->verification_status ?? 'pending';
          $statusLower = strtolower($status);
          $badge = match($statusLower){ 'verified'=>'success','rejected'=>'danger', default=>'warning' };
        @endphp
        <h2 class="page-title mb-1">
          Verificación de vehículo
          <span class="badge bg-{{ $badge }} pill align-middle">{{ $statusLower }}</span>
        </h2>
        <div class="text-muted small">
          Tenant #{{ $v->tenant_id }} · Vehículo #{{ $v->id }}
        </div>
      </div>
      <div class="col-auto ms-auto">
        <div class="btn-list">
          <a href="{{ route('sysadmin.verifications.index') }}" class="btn btn-outline-secondary">
            Volver a la cola
          </a>
          <a href="{{ route('sysadmin.vehicles.documents.index', ['tenant'=>$v->tenant_id,'vehicle'=>$v->id]) }}"
             class="btn btn-outline-primary">
            Ver documentos (vista completa)
          </a>
        </div>
      </div>
    </div>
  </div>

  {{-- Flash --}}
  @if(session('ok')) <div class="alert alert-success">{{ session('ok') }}</div>@endif
  @if(session('status')) <div class="alert alert-success">{{ session('status') }}</div>@endif
  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  @php
    $typeLabels = [
      'foto_vehiculo'             => 'Foto del vehículo',
      'placas'                    => 'Foto de placas',
      'cedula_transporte_publico' => 'Cédula / Transporte público (Taxi)',
      'tarjeta_circulacion'       => 'Tarjeta de circulación',
      'seguro'                    => 'Póliza (opcional)',
    ];
    $approvedByType = collect($docs)->where('status','approved')->groupBy('type')->map(fn($g)=>$g->sortByDesc('id')->first());
    $requiredOk = collect($required)->every(fn($t)=>isset($approvedByType[$t]));
  @endphp

  <div class="row g-3 mb-3">
    {{-- Resumen --}}
    <div class="col-12 col-lg-6">
      <div class="card h-100">
        <div class="card-header">
          <strong>Resumen</strong>
        </div>
        <div class="card-body">
          <div class="mb-2 text-muted">
            Eco: <strong>{{ $v->economico }}</strong> · Placa: <strong>{{ $v->plate }}</strong><br>
            {{ $v->brand ?: '—' }} {{ $v->model ?: '' }} {{ $v->year ? '('.$v->year.')' : '' }}
          </div>

          @if(!empty($v->verification_notes))
            <div class="alert alert-outline-danger mb-3">
              <strong>Notas actuales:</strong> {{ $v->verification_notes }}
            </div>
          @endif

          <div class="mb-2"><strong>Documentos requeridos</strong></div>
          <div class="d-flex flex-wrap gap-2">
            @foreach($required as $rt)
              @php $ok = isset($approvedByType[$rt]); @endphp
              <span class="badge {{ $ok ? 'bg-success' : 'bg-secondary' }} pill">
                {{ $typeLabels[$rt] ?? $rt }} {{ $ok ? '✓' : '—' }}
              </span>
            @endforeach
          </div>

          <hr>

          @if(!$requiredOk)
            <div class="alert alert-warning mb-0">
              Faltan aprobaciones de requeridos. El vehículo seguirá <strong>pending</strong> hasta aprobar
              “Foto del vehículo”, “Placas” y “Cédula / Transporte público”.
            </div>
          @elseif($statusLower !== 'verified')
            <div class="alert alert-info mb-0">
              Requeridos completos. Al terminar la revisión, el sistema puede marcarlo como <strong>verified</strong>.
            </div>
          @else
            <div class="alert alert-success mb-0">
              El vehículo está <strong>verified</strong>. Si rechazas algún documento requerido, se actualizará a
              <strong>rejected</strong> o <strong>pending</strong> según corresponda.
            </div>
          @endif
        </div>
      </div>
    </div>

    {{-- Foto rápida --}}
    <div class="col-12 col-lg-6">
      <div class="card h-100">
        <div class="card-header">
          <strong>Foto rápida</strong>
        </div>
        <div class="card-body">
          @php
            $foto = null;
            if (!empty($v->foto_path))    $foto = asset('storage/'.$v->foto_path);
            elseif (!empty($v->photo_url)) $foto = $v->photo_url;
          @endphp

          @if($foto)
            <div class="text-center">
              <img src="{{ $foto }}" class="img-fluid rounded border"
                   style="max-height:260px;object-fit:contain" alt="Foto del vehículo">
            </div>
          @else
            <div class="alert alert-secondary mb-0">
              Sin imagen principal. Revisa la tabla de documentos para visualizar archivos cargados.
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- Documentos --}}
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <strong>Documentos del vehículo</strong>
      <span class="text-muted small">Total: {{ count($docs) }}</span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-vcenter table-striped mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>Tipo</th>
              <th>Estatus</th>
              <th>No./Emisor</th>
              <th>Fechas</th>
              <th>Archivo</th>
              <th class="text-end">Revisión</th>
            </tr>
          </thead>
          <tbody>
          @forelse($docs as $d)
            @php
              $st = strtolower($d->status ?? 'pending');
              $badgeDoc = match($st){ 'approved'=>'success','rejected'=>'danger','expired'=>'secondary', default=>'warning' };
              $label = $typeLabels[$d->type] ?? $d->type;
              $url = $d->file_path ? asset('storage/'.$d->file_path) : null;
              $ext = $d->file_path ? strtolower(pathinfo($d->file_path, PATHINFO_EXTENSION)) : null;
              $isImage = in_array($ext, ['jpg','jpeg','png','gif','webp']);
              $isPdf = $ext === 'pdf';
            @endphp
            <tr>
              <td>
                <div class="fw-semibold">{{ $label }}</div>
                <div class="text-muted small">Tipo: {{ $d->type }}</div>
              </td>
              <td>
                <span class="badge bg-{{ $badgeDoc }}">{{ $st }}</span>
                @if($d->reviewed_at)
                  <div class="text-muted small mt-1">Rev: {{ $d->reviewed_at }}</div>
                @endif
              </td>
              <td class="small">
                @if($d->document_no)<div><strong>No:</strong> {{ $d->document_no }}</div>@endif
                @if($d->issuer)<div><strong>Emisor:</strong> {{ $d->issuer }}</div>@endif
                @if(!$d->document_no && !$d->issuer)<span class="text-muted">—</span>@endif
              </td>
              <td class="small">
                @if($d->issue_date)<div><strong>Emisión:</strong> {{ $d->issue_date }}</div>@endif
                @if($d->expiry_date)<div><strong>Vence:</strong> {{ $d->expiry_date }}</div>@endif
                @if(!$d->issue_date && !$d->expiry_date)<span class="text-muted">—</span>@endif
              </td>
              <td style="width:280px">
                @if($url)
                  @if($isImage)
                    <a href="{{ $url }}" target="_blank" class="d-inline-block mb-1">
                      <img src="{{ $url }}" class="doc-preview-img border" alt="Doc">
                    </a>
                    <div class="small"><a href="{{ route('sysadmin.vehicle-documents.download', $d->id) }}">Descargar</a></div>
                  @elseif($isPdf)
                    <iframe src="{{ $url }}" class="doc-preview-frame mb-1"></iframe>
                    <div class="small"><a href="{{ route('sysadmin.vehicle-documents.download', $d->id) }}">Descargar PDF</a></div>
                  @else
                    <div class="small mb-1"><i class="bi bi-file-earmark"></i> Archivo: {{ $ext ?: 'desconocido' }}</div>
                    <a href="{{ route('sysadmin.vehicle-documents.download', $d->id) }}" class="btn btn-sm btn-outline-secondary">Descargar</a>
                  @endif
                @else
                  <span class="text-muted small">Sin archivo</span>
                @endif
              </td>
              <td class="text-end" style="width:340px">
                <form method="POST" action="{{ route('sysadmin.verifications.vehicle_docs.review', $d->id) }}"
                      class="d-inline-block text-start" style="max-width:320px">
                  @csrf
                  <div class="input-group input-group-sm mb-1">
                    <select name="action" class="form-select">
                      <option value="approve" @selected($st==='approved')>Aprobar</option>
                      <option value="reject"  @selected($st==='rejected')>Rechazar</option>
                    </select>
                    <button class="btn btn-primary" type="submit">Guardar</button>
                  </div>
                  <input type="text" name="notes" class="form-control form-control-sm"
                         placeholder="Notas para el tenant (opcional)"
                         value="{{ old('notes', $d->review_notes) }}">
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-4">Sin documentos cargados.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
@endsection
