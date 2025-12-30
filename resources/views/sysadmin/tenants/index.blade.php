@extends('layouts.sysadmin')

@section('title', 'SysAdmin – Tenants')

@push('styles')
<style>
  .kpi-card .kpi-label{opacity:.7;font-size:.75rem}
  .kpi-card .kpi-value{font-weight:700;font-size:1.25rem}
  .table td, .table th{vertical-align: middle}
</style>
@endpush

@section('content')
<div class="container-fluid">

  {{-- Header / Toolbar --}}
  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h3 mb-0">Tenants</h1>
      <div class="text-muted small">Administración de centrales, marketplace y facturación.</div>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('sysadmin.tenants.create') }}" class="btn btn-primary">
        <i class="ti ti-plus me-1"></i> Nuevo tenant
      </a>
    </div>
  </div>

  {{-- KPIs rápidos --}}
  <div class="row g-3 mb-3">
    <div class="col-sm-6 col-lg-3">
      <div class="card kpi-card">
        <div class="card-body">
          <div class="kpi-label">Total tenants</div>
          <div class="kpi-value">{{ number_format($tenants->total()) }}</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card kpi-card">
        <div class="card-body">
          <div class="kpi-label">Con marketplace</div>
          <div class="kpi-value">{{ number_format($tenants->where('allow_marketplace',1)->count()) }}</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card kpi-card">
        <div class="card-body">
          <div class="kpi-label">Hoy</div>
          <div class="kpi-value">
            {{ $tenants->filter(fn($t)=>optional($t->created_at)->isToday())->count() }}
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Filtros / búsqueda --}}
  <form method="GET" class="card mb-3">
    <div class="card-body row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label">Buscar</label>
        <div class="input-icon">
          <span class="input-icon-addon">
            <i class="ti ti-search"></i>
          </span>
          <input type="search" class="form-control" name="q" value="{{ request('q') }}" placeholder="Nombre, slug, ciudad…">
        </div>
      </div>
      <div class="col-md-3">
        <label class="form-label">Marketplace</label>
        <select class="form-select" name="marketplace" id="filterMarketplace">
          <option value="">Todos</option>
          <option value="1" @selected(request('marketplace')==='1')>Sí</option>
          <option value="0" @selected(request('marketplace')==='0')>No</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Ordenar por</label>
        <select class="form-select" name="sort" id="filterSort">
          <option value="created_desc" @selected(request('sort','created_desc')==='created_desc')>Recientes primero</option>
          <option value="created_asc"  @selected(request('sort')==='created_asc')>Antiguos primero</option>
          <option value="name_asc"     @selected(request('sort')==='name_asc')>Nombre A–Z</option>
          <option value="name_desc"    @selected(request('sort')==='name_desc')>Nombre Z–A</option>
        </select>
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-outline-secondary w-100" type="submit">
          <i class="ti ti-filter me-1"></i> Aplicar
        </button>
        <a href="{{ route('sysadmin.tenants.index') }}" class="btn btn-link">Limpiar</a>
      </div>
    </div>
  </form>

  {{-- Tabla en card --}}
  <div class="card">
    <div class="card-header">
      <div class="card-title">Listado</div>
      <div class="ms-auto text-muted small">
        Página {{ $tenants->currentPage() }} de {{ $tenants->lastPage() }}
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover table-striped mb-0 align-middle">
        <thead>
          <tr>
            <th style="width:80px">ID</th>
            <th>Nombre</th>
            <th>Slug</th>
            <th>Timezone</th>
            <th class="text-center">Marketplace</th>
            <th>Creado</th>
            <th class="text-end" style="width:220px">Acciones</th>
          </tr>
        </thead>
        <tbody>
        @forelse($tenants as $tenant)
          <tr>
            <td>
              <span class="badge bg-secondary">{{ $tenant->id }}</span>
            </td>

            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="avatar avatar-sm rounded" style="background:#0ea5e9">{{ strtoupper(substr($tenant->name,0,1)) }}</div>
                <div>
                  <div class="fw-semibold">{{ $tenant->name }}</div>
                  <div class="text-muted small">{{ $tenant->city ?? '—' }}</div>
                </div>
              </div>
            </td>

            <td>
              <span class="badge bg-dark">{{ $tenant->slug }}</span>
            </td>

            <td class="text-nowrap">
              {{ $tenant->timezone ?: 'America/Mexico_City' }}
            </td>

            <td class="text-center">
              @if($tenant->allow_marketplace)
                <span class="status status-green me-1"></span>
                <span class="badge text-bg-success">Sí</span>
              @else
                <span class="status status-red me-1"></span>
                <span class="badge text-bg-secondary">No</span>
              @endif
            </td>

            <td class="text-nowrap">
              {{ optional($tenant->created_at)->format('Y-m-d H:i') }}
            </td>

            <td class="text-end">
              <div class="btn-group btn-group-sm" role="group">
                <a href="{{ route('sysadmin.tenants.edit', $tenant) }}" class="btn btn-outline-secondary">
                  <i class="ti ti-edit me-1"></i> Editar
                </a>
                <a href="{{ route('sysadmin.tenants.billing.show', $tenant) }}" class="btn btn-outline-primary">
                  <i class="ti ti-currency-dollar me-1"></i> Billing
                </a>

                {{-- Menú extra opcional --}}
                <div class="dropdown">
                  <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" type="button">
                    Más
                  </button>
                  <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="{{ route('sysadmin.invoices.index', ['tenant_id' => $tenant->id]) }}">
                      <i class="ti ti-file-invoice me-2"></i> Ver facturas
                    </a>
                    <a class="dropdown-item" href="{{ route('sysadmin.verifications.index', ['tenant_id' => $tenant->id]) }}">
                      <i class="ti ti-checkbox me-2"></i> Cola verificación
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger" href="#" onclick="alert('Acción futura');return false;">
                      <i class="ti ti-alert-triangle me-2"></i> Suspender (próx.)
                    </a>
                  </div>
                </div>
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="7">
              <div class="text-center py-5">
                <div class="mb-2">
                  <i class="ti ti-database-off" style="font-size:2rem"></i>
                </div>
                <div class="fw-semibold">Sin tenants</div>
                <div class="text-muted mb-3">Aún no has registrado ninguna central.</div>
                <a href="{{ route('sysadmin.tenants.create') }}" class="btn btn-primary">
                  Crear primer tenant
                </a>
              </div>
            </td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>

    @if($tenants->hasPages())
      <div class="card-footer d-flex justify-content-between align-items-center">
        <div class="text-muted small">
          Mostrando {{ $tenants->firstItem() }}–{{ $tenants->lastItem() }} de {{ $tenants->total() }}
        </div>
        <div>
          {{ $tenants->withQueryString()->links() }}
        </div>
      </div>
    @endif
  </div>
</div>
@endsection

@push('scripts')
<script>
  // Auto-aplicar filtros al cambiar selects
  document.getElementById('filterMarketplace')?.addEventListener('change', e => e.target.form.submit());
  document.getElementById('filterSort')?.addEventListener('change', e => e.target.form.submit());
</script>
@endpush
