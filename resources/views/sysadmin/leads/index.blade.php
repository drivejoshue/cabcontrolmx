@extends('layouts.sysadmin')

@section('title','Leads · Contacto')

@section('content')
<div class="container-fluid py-3">

  {{-- Encabezado --}}
  <div class="page-header d-print-none mb-3">
    <div class="row align-items-center">
      <div class="col">
        <h2 class="page-title">Leads de contacto</h2>
        <div class="text-muted">Mensajes recibidos desde orbana.mx</div>
      </div>
    </div>
  </div>

  {{-- Filtros --}}
  <form class="card mb-3" method="get" action="{{ route('sysadmin.leads.index') }}">
    <div class="card-body">
      <div class="row g-2">
        <div class="col-12 col-md-3">
          <label class="form-label">Estado</label>
          @php $st = request('status'); @endphp
          <select name="status" class="form-select">
            <option value="">Todos</option>
            <option value="new"       @selected($st==='new')>Nuevo</option>
            <option value="contacted" @selected($st==='contacted')>En seguimiento</option>
            <option value="closed"    @selected($st==='closed')>Cerrado</option>
          </select>
        </div>
        <div class="col-12 col-md-7">
          <label class="form-label">Buscar</label>
          <input type="text"
                 name="q"
                 value="{{ request('q') }}"
                 class="form-control"
                 placeholder="nombre, email, teléfono, central, ciudad, estado…">
        </div>
        <div class="col-12 col-md-2 d-flex align-items-end">
          <button class="btn btn-primary w-100">Filtrar</button>
        </div>
      </div>
    </div>
  </form>

  {{-- Tabla --}}
  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-vcenter table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th style="width:86px">#</th>
              <th>Contacto</th>
              <th>Central / Origen</th>
              <th>Ubicación</th>
              <th style="width:140px">Estado</th>
              <th style="width:190px">Recibido</th>
              <th class="text-end" style="width:110px">Acción</th>
            </tr>
          </thead>
          <tbody>
          @forelse($leads as $lead)
            @php
              $status = $lead->status ?: 'new';
              [$badgeClass,$label] = match($status) {
                'new'       => ['bg-danger','Nuevo'],
                'contacted' => ['bg-primary','En seguimiento'],
                'closed'    => ['bg-success','Cerrado'],
                default     => ['bg-secondary',$status],
              };
            @endphp
            <tr>
              <td class="text-muted">#{{ $lead->id }}</td>

              <td>
                <div class="fw-semibold">{{ $lead->contact_name ?: '—' }}</div>
                <div class="small text-muted">
                  @if($lead->contact_email)
                    <a href="mailto:{{ $lead->contact_email }}">{{ $lead->contact_email }}</a>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                  @if($lead->contact_phone)
                    <span class="text-muted"> · </span>
                    <a href="tel:{{ $lead->contact_phone }}">{{ $lead->contact_phone }}</a>
                  @endif
                </div>
                @if($lead->message)
                  <div class="small text-muted mt-1">
                    {{ \Illuminate\Support\Str::limit($lead->message, 90) }}
                  </div>
                @endif
              </td>

              <td>
                <div class="fw-semibold">{{ $lead->central_name ?: '—' }}</div>
                <div class="small text-muted">{{ $lead->source ?: 'landing' }}</div>
              </td>

              <td>
                <div>{{ $lead->city ?: '—' }}</div>
                <div class="small text-muted">{{ $lead->state ?: '—' }}</div>
              </td>

              <td>
                <span class="badge {{ $badgeClass }}">{{ $label }}</span>
              </td>

              <td>
                <div class="small">{{ optional($lead->created_at)->format('Y-m-d H:i') }}</div>
                <div class="small text-muted">
                  {{ optional($lead->created_at)->diffForHumans() }}
                </div>
              </td>

              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary"
                   href="{{ route('sysadmin.leads.show', $lead) }}">
                  Ver
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="p-4 text-center text-muted">
                No hay leads por ahora.
              </td>
            </tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>

    @if(method_exists($leads, 'links'))
      <div class="card-footer bg-white">
        {{ $leads->appends(request()->query())->links() }}
      </div>
    @endif
  </div>

</div>
@endsection
