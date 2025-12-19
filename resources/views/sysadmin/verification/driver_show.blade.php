
@extends('layouts.sysadmin')

@section('title','Verificación de conductor')

@section('content')
<div class="container-fluid">

  {{-- Header --}}
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">
        Conductor: {{ $d->name }}
        @php
          $vs = $d->verification_status ?? 'pending';
          $vsBadge = $vs === 'verified'
              ? 'success'
              : ($vs === 'rejected' ? 'danger' : 'warning');
        @endphp
        <span class="badge bg-{{ $vsBadge }} align-middle">
          {{ $vs }}
        </span>
      </h3>
      <div class="text-muted small">
        ID: #{{ $d->id }} · Tenant ID: {{ $d->tenant_id }}
      </div>
      <div class="text-muted small">
        Tel: {{ $d->phone ?: '—' }} · Email: {{ $d->email ?: '—' }}
      </div>
      @if(!empty($d->verification_notes))
        <div class="mt-1 text-danger small">
          <strong>Notas de verificación:</strong> {{ $d->verification_notes }}
        </div>
      @endif
    </div>

    <div class="d-flex gap-2">
    <a href="{{ route('sysadmin.verifications.index') }}" class="btn btn-outline-secondary">
  Volver a cola de verificación
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

  @php
    // Mapeo de tipos → etiqueta legible
    $types = [
      'foto_driver' => 'Foto del conductor',
      'ine'         => 'Identificación oficial (INE)',
      'licencia'    => 'Licencia de conducir',
      'comprobante_domicilio' => 'Comprobante de domicilio',
      'otro'        => 'Otro',
    ];

    $required = $required ?? ['foto_driver','ine','licencia'];

    // docs aprobados por tipo (último aprobado)
    $approvedByType = collect($docs)
      ->where('status','approved')
      ->groupBy('type')
      ->map(fn($g) => $g->sortByDesc('id')->first());

    $requiredOk = collect($required)->every(fn($t) => isset($approvedByType[$t]));
  @endphp

  {{-- Resumen de verificación --}}
  <div class="card mb-3">
    <div class="card-body">
      <div class="mb-2"><strong>Documentos requeridos para el conductor</strong></div>

      <div class="d-flex flex-wrap gap-2 mb-2">
        @foreach($required as $rt)
          @php $ok = isset($approvedByType[$rt]); @endphp
          <span class="badge bg-{{ $ok ? 'success' : 'secondary' }}">
            {{ $types[$rt] ?? $rt }} {{ $ok ? '✓' : '—' }}
          </span>
        @endforeach
      </div>

      @if(!$requiredOk)
        <div class="alert alert-warning mb-0">
          Faltan documentos requeridos o están pendientes/rechazados.
          Ajusta los estados de cada documento para avanzar la verificación.
        </div>
      @else
        <div class="alert alert-success mb-0">
          Todos los documentos requeridos tienen al menos una versión aprobada.
          Puedes dejar el conductor como <strong>verified</strong>.
        </div>
      @endif
    </div>
  </div>

  {{-- Tabla de documentos --}}
  <div class="card">
    <div class="card-header">
      <strong>Documentos cargados del conductor</strong>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped align-middle mb-0">
          <thead>
            <tr>
              <th>Tipo</th>
              <th>Status</th>
              <th>Notas de revisión</th>
              <th>Fechas</th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
          @forelse($docs as $doc)
            @php
              $badge = $doc->status === 'approved'
                ? 'success'
                : ($doc->status === 'rejected' ? 'danger' : 'warning');

          
            @endphp
            <tr>
              <td>{{ $types[$doc->type] ?? $doc->type }}</td>
              <td>
                <span class="badge bg-{{ $badge }}">{{ $doc->status }}</span>
              </td>
              <td class="text-muted small">
                {{ $doc->review_notes ?: '—' }}
              </td>
              <td class="small text-muted">
                @if(!empty($doc->issue_date))
                  <div>Emisión: {{ $doc->issue_date }}</div>
                @endif
                @if(!empty($doc->expiry_date))
                  <div>Vence: {{ $doc->expiry_date }}</div>
                @endif
                @if(!empty($doc->reviewed_at))
                  <div>Revisado: {{ $doc->reviewed_at }}</div>
                @endif
              </td>
             @php
  $viewRoute     = route('sysadmin.driver-documents.view', $doc->id);
  $downloadRoute = route('sysadmin.driver-documents.download', $doc->id);
@endphp

<td class="text-end">
  {{-- Ver inline en nueva pestaña --}}
  <a href="{{ $viewRoute }}" class="btn btn-sm btn-outline-secondary mb-1" target="_blank">
    Ver
  </a>

  {{-- Descargar archivo --}}
  <a href="{{ $downloadRoute }}" class="btn btn-sm btn-outline-secondary mb-1">
    Descargar
  </a>

  {{-- Form de aprobación/rechazo con notas inline --}}
  <form method="POST"
        action="{{ route('sysadmin.verifications.driver_docs.review', $doc->id) }}"
        class="d-inline-block text-start"
        style="max-width:260px">
    @csrf

    <input type="text"
           name="notes"
           class="form-control form-control-sm mb-1"
           placeholder="Notas (opcional)"
           value="{{ $doc->review_notes }}">

    <div class="btn-group btn-group-sm w-100" role="group">
      <button type="submit" name="action" value="approve" class="btn btn-success">
        Aprobar
      </button>
      <button type="submit" name="action" value="reject" class="btn btn-outline-danger">
        Rechazar
      </button>
    </div>
  </form>
</td>

            </tr>
          @empty
            <tr>
              <td colspan="5" class="text-center py-3 text-muted">
                Aún no hay documentos cargados para este conductor.
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
