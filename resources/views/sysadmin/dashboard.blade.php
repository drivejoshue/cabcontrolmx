@extends('layouts.sysadmin')
@section('title','SysAdmin · Orbana')

@section('content')
<div class="container-xl">

  {{-- Encabezado --}}
  <div class="row mb-3">
    <div class="col">
      <h2 class="page-title">Panel SysAdmin</h2>
      <div class="text-muted">
        Control maestro de tenants, facturación, verificaciones y documentación.
      </div>
    </div>
    <div class="col-auto d-print-none">
      <div class="btn-list">
        {{-- Accesos rápidos principales --}}
        <a href="{{ route('sysadmin.tenants.create') }}" class="btn btn-primary">
          <i class="ti ti-circle-plus me-1"></i> Nuevo tenant
        </a>
        <a href="{{ route('sysadmin.tenants.index') }}" class="btn btn-outline-secondary">
          <i class="ti ti-layers me-1"></i> Tenants
        </a>
        <a href="{{ route('sysadmin.invoices.index') }}" class="btn btn-outline-secondary">
          <i class="ti ti-file-text me-1"></i> Facturas
        </a>
        <a href="{{ route('sysadmin.verifications.index') }}" class="btn btn-outline-secondary">
          <i class="ti ti-checkbox me-1"></i> Verificación
        </a>
        <a href="{{ route('sysadmin.leads.index') }}" class="btn btn-outline-secondary">
          <i class="ti ti-briefcase me-1"></i> Leads
        </a>
      </div>
    </div>
  </div>

  {{-- KPIs --}}
  <div class="row row-cards">
    <div class="col-sm-6 col-lg-3">
      <div class="card card-sm">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <span class="text-secondary me-3">
              <i class="ti ti-building-bank ti-md"></i>
            </span>
            <div class="d-flex align-items-baseline">
              <h3 class="mb-0">{{ $tenantsCount ?? 0 }}</h3>
              <span class="ms-2 text-muted">Tenants</span>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card card-sm">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <span class="text-secondary me-3">
              <i class="ti ti-id ti-md"></i>
            </span>
            <div class="d-flex align-items-baseline">
              <h3 class="mb-0">{{ $driversCount ?? 0 }}</h3>
              <span class="ms-2 text-muted">Conductores</span>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card card-sm">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <span class="text-secondary me-3">
              <i class="ti ti-car ti-md"></i>
            </span>
            <div class="d-flex align-items-baseline">
              <h3 class="mb-0">{{ $vehiclesCount ?? 0 }}</h3>
              <span class="ms-2 text-muted">Vehículos</span>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card card-sm">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <span class="text-secondary me-3">
              <i class="ti ti-route ti-md"></i>
            </span>
            <div class="d-flex align-items-baseline">
              <h3 class="mb-0">{{ $ridesToday ?? 0 }}</h3>
              <span class="ms-2 text-muted">Viajes hoy</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Orbana Global + accesos --}}
  <div class="row row-cards mt-1">
    <div class="col-12">
      <div class="card border-0">
        <div class="card-status-top bg-blue"></div>
        <div class="card-body d-flex justify-content-between align-items-center">
          <div>
            <div class="h4 mb-1">Orbana Global</div>
            @if(!empty($globalTenant))
              <div class="text-muted">
                Tenant: {{ $globalTenant->name }} <span class="text-secondary">(slug: {{ $globalTenant->slug }})</span>
              </div>
            @else
              <div class="text-muted">Aún no se ha creado el tenant para Orbana Global.</div>
            @endif
          </div>
          <div class="btn-list">
            <a href="{{ route('sysadmin.tenants.index') }}" class="btn btn-outline-primary">
              <i class="ti ti-layers me-1"></i> Ver tenants
            </a>
            @if(!empty($globalTenant))
              <a href="{{ route('sysadmin.tenants.billing.show',$globalTenant->id) }}" class="btn btn-outline-info">
                <i class="ti ti-wallet me-1"></i> Billing
              </a>
            @endif
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Últimos tenants + Facturas recientes --}}
  <div class="row row-cards">
    <div class="col-lg-6">
      <div class="card">
        <div class="card-header">
          <div class="card-title">Últimos tenants</div>
          <div class="ms-auto">
            <a href="{{ route('sysadmin.tenants.index') }}" class="btn btn-sm btn-outline-secondary">
              Ver todos
            </a>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead>
              <tr>
                <th>Nombre</th>
                <th>Slug</th>
                <th>Creado</th>
                <th class="w-1"></th>
              </tr>
            </thead>
            <tbody>
              @forelse($recentTenants ?? [] as $t)
                <tr>
                  <td class="text-nowrap">{{ $t->name }}</td>
                  <td class="text-muted">{{ $t->slug }}</td>
                  <td class="text-muted">{{ $t->created_at }}</td>
                  <td class="text-end">
                    <div class="btn-list">
                      <a href="{{ route('sysadmin.tenants.edit',$t->id) }}" class="btn btn-icon btn-outline-primary btn-sm" title="Editar">
                        <i class="ti ti-pencil"></i>
                      </a>
                      <a href="{{ route('sysadmin.tenants.billing.show',$t->id) }}" class="btn btn-icon btn-outline-info btn-sm" title="Billing">
                        <i class="ti ti-wallet"></i>
                      </a>
                      <a href="{{ route('sysadmin.tenants.reports.commissions',$t->id) }}" class="btn btn-icon btn-outline-secondary btn-sm" title="Reporte comisiones">
                        <i class="ti ti-chart-bar"></i>
                      </a>
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="4" class="text-center text-muted py-4">Sin tenants registrados.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    @isset($recentInvoices)
    <div class="col-lg-6">
      <div class="card">
        <div class="card-header">
          <div class="card-title">Últimas facturas</div>
          <div class="ms-auto">
            <a href="{{ route('sysadmin.invoices.index') }}" class="btn btn-sm btn-outline-secondary">
              Ver todas
            </a>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead>
              <tr>
                <th>Tenant</th>
                <th>Monto</th>
                <th>Estado</th>
                <th>Fecha</th>
                <th class="w-1"></th>
              </tr>
            </thead>
            <tbody>
              @forelse($recentInvoices as $inv)
                <tr>
                  <td class="text-nowrap">{{ $inv->tenant->name ?? '#' }}</td>
                  <td class="text-nowrap">${{ number_format($inv->amount, 2) }}</td>
                  <td><span class="badge @class([
                        'bg-success' => ($inv->status ?? '') === 'paid',
                        'bg-warning' => ($inv->status ?? '') === 'pending',
                        'bg-danger'  => ($inv->status ?? '') === 'failed',
                        'bg-secondary' => !in_array(($inv->status ?? ''), ['paid','pending','failed']),
                      ])">
                    {{ $inv->status ?? '-' }}
                  </span></td>
                  <td class="text-muted">{{ $inv->created_at }}</td>
                  <td class="text-end">
                    <a href="{{ route('sysadmin.invoices.show',$inv->id) }}" class="btn btn-icon btn-outline-secondary btn-sm" title="Ver">
                      <i class="ti ti-eye"></i>
                    </a>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="5" class="text-center text-muted py-4">Aún no hay facturas registradas.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
    @endisset
  </div>

  {{-- Verificación y documentos --}}
  <div class="row row-cards">
    <div class="col-lg-6">
      <div class="card">
        <div class="card-header">
          <div class="card-title">Cola de verificación</div>
          <div class="ms-auto">
            <a href="{{ route('sysadmin.verifications.index') }}" class="btn btn-sm btn-outline-primary">
              Abrir cola
            </a>
          </div>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-6">
              <div class="d-flex align-items-center">
                <span class="avatar me-3 bg-warning-lt"><i class="ti ti-id"></i></span>
                <div>
                  <div class="strong">Drivers pendientes</div>
                  <div class="text-muted">{{ $pendingDriverDocs ?? 0 }} documentos</div>
                </div>
              </div>
            </div>
            <div class="col-6">
              <div class="d-flex align-items-center">
                <span class="avatar me-3 bg-info-lt"><i class="ti ti-car"></i></span>
                <div>
                  <div class="strong">Vehículos pendientes</div>
                  <div class="text-muted">{{ $pendingVehicleDocs ?? 0 }} documentos</div>
                </div>
              </div>
            </div>
          </div>

          {{-- Acciones contextualizadas (rutas POST para review se usan en los detalles/cola) --}}
          <div class="mt-3">
            <a href="{{ route('sysadmin.verifications.index') }}#drivers" class="btn btn-outline-secondary btn-sm me-2">
              <i class="ti ti-user-check me-1"></i> Revisar drivers
            </a>
            <a href="{{ route('sysadmin.verifications.index') }}#vehicles" class="btn btn-outline-secondary btn-sm">
              <i class="ti ti-steering-wheel me-1"></i> Revisar vehículos
            </a>
          </div>
        </div>
      </div>
    </div>

    {{-- Leads --}}
    <div class="col-lg-6">
      <div class="card">
        <div class="card-header">
          <div class="card-title">Leads</div>
          <div class="ms-auto">
            <a href="{{ route('sysadmin.leads.index') }}" class="btn btn-sm btn-outline-secondary">
              Ver todos
            </a>
          </div>
        </div>
        <div class="card-body">
          <div class="text-muted">
            Gestiona solicitudes de contacto y oportunidades comerciales desde el sitio.
          </div>
          {{-- Si quieres listar últimos leads, pásalos como $recentLeads y agrégales tabla aquí --}}
        </div>
      </div>
    </div>
  </div>

</div>
@endsection
