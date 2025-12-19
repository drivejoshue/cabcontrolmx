@extends('layouts.sysadmin')

@section('title', 'SysAdmin – Facturas a tenants')

@section('content')
<div class="container-fluid">

 <div class="d-flex justify-content-between align-items-center mb-3">
    <h1>Facturas a tenants</h1>

    <div class="d-flex gap-2">
        <a href="{{ route('sysadmin.invoices.export_csv') }}"
           class="btn btn-outline-secondary btn-sm">
            Exportar CSV
        </a>
    </div>
</div>


  {{-- Filtros --}}
  <form method="GET" class="card mb-3">
    <div class="card-body row g-3 align-items-end small">
      <div class="col-sm-4 col-md-3">
        <label class="form-label">Tenant</label>
        <select name="tenant_id" class="form-select form-select-sm">
          <option value="">Todos</option>
          @foreach($tenants as $t)
            <option value="{{ $t->id }}" @selected($filters['tenant_id']==$t->id)>
              #{{ $t->id }} · {{ $t->name }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="col-sm-3 col-md-2">
        <label class="form-label">Status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">Todos</option>
          @foreach($statuses as $st)
            <option value="{{ $st }}" @selected($filters['status']===$st)>{{ ucfirst($st) }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-sm-3 col-md-2">
        <label class="form-label">Emisión desde</label>
        <input type="date" name="issue_from"
               value="{{ $filters['issue_from'] }}"
               class="form-control form-control-sm">
      </div>

      <div class="col-sm-3 col-md-2">
        <label class="form-label">Emisión hasta</label>
        <input type="date" name="issue_to"
               value="{{ $filters['issue_to'] }}"
               class="form-control form-control-sm">
      </div>

      <div class="col-sm-3 col-md-2">
        <button class="btn btn-primary btn-sm w-100" type="submit">
          Filtrar
        </button>
      </div>
    </div>
  </form>

  {{-- Tabla --}}
  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle mb-0">
          <thead class="table-light">
          <tr>
            <th>ID</th>
            <th>Tenant</th>
            <th>Periodo</th>
            <th>Emisión</th>
            <th>Vence</th>
            <th>Status</th>
            <th class="text-end">Total</th>
            <th class="text-end">Acciones</th>
          </tr>
          </thead>
          <tbody>
          @forelse($invoices as $inv)
            @php
              $badge = match($inv->status) {
                'paid'    => 'success',
                'pending' => 'warning',
                'overdue' => 'danger',
                'canceled'=> 'secondary',
                default   => 'secondary',
              };
            @endphp
            <tr>
              <td>#{{ $inv->id }}</td>
              <td>
                {{ $inv->tenant?->name ?? '—' }}<br>
                <span class="text-muted small">Tenant ID: {{ $inv->tenant_id }}</span>
              </td>
              <td class="small">
                {{ $inv->period_start?->toDateString() }}<br>
                – {{ $inv->period_end?->toDateString() }}
              </td>
              <td>{{ $inv->issue_date?->toDateString() }}</td>
              <td>{{ $inv->due_date?->toDateString() }}</td>
              <td>
                <span class="badge bg-{{ $badge }}">{{ $inv->status }}</span>
              </td>
              <td class="text-end">
                {{ number_format($inv->total, 2) }} {{ $inv->currency ?? 'MXN' }}
              </td>
              <td class="text-end">
                <a href="{{ route('sysadmin.invoices.show', $inv) }}"
                   class="btn btn-sm btn-outline-primary">
                  Ver
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center py-4 text-muted">
                No hay facturas que coincidan con los filtros.
              </td>
            </tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>

    @if($invoices->hasPages())
      <div class="card-footer">
        {{ $invoices->links() }}
      </div>
    @endif
  </div>

</div>
@endsection
