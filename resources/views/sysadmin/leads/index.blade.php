@extends('layouts.sysadmin')

@section('title','Leads · Contacto')

@section('content')
<div class="container-fluid py-3">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h1 class="h4 mb-0">Leads de contacto</h1>
      <div class="text-muted small">Mensajes recibidos desde orbana.mx</div>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body p-0">

      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th style="width:90px">ID</th>
              <th>Contacto</th>
              <th>Central</th>
              <th>Ubicación</th>
              <th style="width:140px">Estado</th>
              <th style="width:170px">Fecha</th>
              <th style="width:110px" class="text-end">Acción</th>
            </tr>
          </thead>
          <tbody>
          @forelse($leads as $lead)
            <tr>
              <td class="text-muted">#{{ $lead->id }}</td>

              <td>
                <div class="fw-semibold">{{ $lead->contact_name }}</div>
                <div class="small text-muted">
                  <span class="me-2">{{ $lead->contact_email }}</span>
                  @if($lead->contact_phone)
                    <span class="text-muted">· {{ $lead->contact_phone }}</span>
                  @endif
                </div>
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
                @php
                  $status = $lead->status ?: 'new';
                  $badge = match($status) {
                    'new' => 'bg-danger',
                    'contacted' => 'bg-primary',
                    'closed' => 'bg-success',
                    default => 'bg-secondary'
                  };
                  $label = match($status) {
                    'new' => 'Nuevo',
                    'contacted' => 'En seguimiento',
                    'closed' => 'Cerrado',
                    default => $status
                  };
                @endphp
                <span class="badge {{ $badge }}">{{ $label }}</span>
              </td>

              <td>
                <div class="small">{{ optional($lead->created_at)->format('Y-m-d') }}</div>
                <div class="small text-muted">{{ optional($lead->created_at)->format('H:i') }}</div>
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
        {{ $leads->links() }}
      </div>
    @endif
  </div>

</div>
@endsection
