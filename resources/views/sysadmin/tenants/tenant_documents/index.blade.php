@extends('layouts.sysadmin')

@section('title','Documentos de Tenants - SysAdmin')

@section('content')
@php
  $filters = $filters ?? [];
  $fTenant = $filters['tenant_id'] ?? '';
  $fStatus = $filters['status'] ?? 'pending';
  $fType   = $filters['type'] ?? '';

  $typeLabel = [
    'id_official'     => 'Identificación oficial',
    'proof_address'   => 'Comprobante de domicilio',
    'tax_certificate' => 'Constancia fiscal',
  ];

  $typeIcon = [
    'id_official'     => 'ti ti-id',
    'proof_address'   => 'ti ti-home',
    'tax_certificate' => 'ti ti-receipt-tax',
  ];

  $statusLabel = [
    'pending'  => 'Pendiente',
    'approved' => 'Aprobado',
    'rejected' => 'Rechazado',
  ];

  $badgeStatus = function($st) {
    return match($st) {
      'approved' => 'bg-success-lt text-success',
      'rejected' => 'bg-danger-lt text-danger',
      'pending'  => 'bg-warning-lt text-warning',
      default    => 'bg-secondary-lt text-secondary',
    };
  };
@endphp

<div class="container-fluid">

  <div class="d-flex align-items-start justify-content-between gap-2 flex-wrap mb-3">
    <div>
      <h1 class="h3 mb-1">Documentos de Tenants</h1>
      <div class="text-muted">
        Validación SysAdmin. Archivos privados (no públicos). Recomendación: revisar pendientes primero.
      </div>
    </div>
  </div>

  @if(session('ok'))
    <div class="alert alert-success">{{ session('ok') }}</div>
  @endif
  @if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  @if($errors->any())
    <div class="alert alert-danger">
      <div class="fw-bold mb-1">Revisa:</div>
      <ul class="mb-0">
        @foreach($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  {{-- Filtros --}}
  <div class="card mb-3">
    <div class="card-body">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
          <label class="form-label">Tenant</label>
          <input type="text" name="tenant_id" value="{{ $fTenant }}" class="form-control" placeholder="Ej. 1">
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label">Tipo</label>
          <select name="type" class="form-select">
            <option value="">Todos</option>
            @foreach($typeLabel as $k=>$lbl)
              <option value="{{ $k }}" @selected((string)$fType === (string)$k)>{{ $lbl }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="">Todos</option>
            @foreach($statusLabel as $k=>$lbl)
              <option value="{{ $k }}" @selected((string)$fStatus === (string)$k)>{{ $lbl }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-12 col-md-3 d-flex gap-2">
          <button class="btn btn-primary w-100">
            <i class="ti ti-filter"></i> Aplicar
          </button>
          <a href="{{ route('sysadmin.tenant-documents.index') }}" class="btn btn-outline-secondary w-100">
            Limpiar
          </a>
        </div>
      </form>
    </div>
  </div>

  {{-- Tabla --}}
  <div class="card">
    <div class="card-header">
      <div class="fw-semibold">Listado</div>
      <div class="text-muted small">
        Tip: entra al detalle del tenant para aprobar/rechazar rápido en bloque.
      </div>
    </div>

    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-vcenter table-striped mb-0">
          <thead>
            <tr>
              <th style="width:110px;">Tenant</th>
              <th>Central</th>
              <th style="width:220px;">Tipo</th>
              <th style="width:140px;">Status</th>
              <th style="width:220px;">Subido</th>
              <th style="width:160px;">Tamaño</th>
              <th style="width:160px;" class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
            @forelse(($docs ?? []) as $d)
              @php
                $tId = (int)$d->tenant_id;
                $type = $d->type ?? '';
                $st = $d->status ?? '';
                $uploaded = !empty($d->uploaded_at) ? \Illuminate\Support\Carbon::parse($d->uploaded_at)->format('Y-m-d H:i') : '—';
                $sizeKb = $d->size_bytes ? round(((int)$d->size_bytes)/1024) : 0;

                $typeLbl = $typeLabel[$type] ?? $type;
                $icon = $typeIcon[$type] ?? 'ti ti-file-text';
              @endphp

              <tr>
                <td class="fw-semibold">#{{ $tId }}</td>
                <td class="text-muted">
                  <div class="fw-semibold text-body">{{ $d->tenant_name ?? '—' }}</div>
                  <div class="small">Slug: <span class="text-muted">{{ $d->tenant_slug ?? '—' }}</span></div>
                </td>

                <td>
                  <span class="badge bg-secondary-lt text-secondary">
                    <i class="{{ $icon }}"></i> {{ $typeLbl }}
                  </span>
                </td>

                <td>
                  <span class="badge {{ $badgeStatus($st) }}">
                    {{ $statusLabel[$st] ?? $st }}
                  </span>
                  @if($st === 'rejected' && !empty($d->review_notes))
                    <div class="small text-danger mt-1 text-truncate" style="max-width:260px;">
                      <i class="ti ti-info-circle"></i> {{ $d->review_notes }}
                    </div>
                  @endif
                </td>

                <td class="text-muted">{{ $uploaded }}</td>
                <td class="text-muted">{{ $sizeKb ? number_format($sizeKb) . ' KB' : '—' }}</td>

                <td class="text-end">
                  <a class="btn btn-sm btn-outline-secondary"
                     href="{{ route('sysadmin.tenant-documents.show', $tId) }}">
                    <i class="ti ti-eye"></i> Ver tenant
                  </a>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="text-muted text-center p-3">Sin documentos con esos filtros.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    @if(method_exists($docs, 'links'))
      <div class="card-footer">
        {{ $docs->links() }}
      </div>
    @endif
  </div>

</div>
@endsection
