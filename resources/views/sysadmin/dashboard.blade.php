@extends('layouts.sysadmin')

@section('title', 'SysAdmin · Orbana')

@section('content')
<div class="container-fluid">

    <div class="row mb-4">
        <div class="col">
            <h1 class="h3 mb-0">Panel SysAdmin</h1>
            <small class="text-muted">
                Control maestro de tenants, billing y documentación de vehículos.
            </small>
        </div>
    </div>

    {{-- KPIs principales --}}
    <div class="row">
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Tenants totales</h6>
                    <h3 class="mb-0">{{ $tenantsCount }}</h3>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Conductores</h6>
                    <h3 class="mb-0">{{ $driversCount }}</h3>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Vehículos</h6>
                    <h3 class="mb-0">{{ $vehiclesCount }}</h3>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Viajes hoy</h6>
                    <h3 class="mb-0">{{ $ridesToday }}</h3>
                </div>
            </div>
        </div>
    </div>

    {{-- Bloque Orbana Global --}}
    <div class="row mt-3">
        <div class="col-md-12 mb-3">
            <div class="card border-info shadow-sm">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1">Orbana Global</h5>
                        @if($globalTenant)
                            <p class="mb-0 text-muted">
                                Tenant: {{ $globalTenant->name }}
                                (slug: {{ $globalTenant->slug }})
                            </p>
                        @else
                            <p class="mb-0 text-muted">
                                Aún no se ha creado el tenant para Orbana Global.
                            </p>
                        @endif
                    </div>
                    <div>
                        <a href="{{ route('sysadmin.tenants.index') }}" class="btn btn-sm btn-outline-info">
                            Ver tenants
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Últimos tenants --}}
    <div class="row mt-3">
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm">
                <div class="card-header">
                    Últimos tenants creados
                </div>
                <div class="card-body p-0">
                    <table class="table mb-0 table-sm">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Slug</th>
                                <th>Creado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($recentTenants as $tenant)
                            <tr>
                                <td>{{ $tenant->name }}</td>
                                <td>{{ $tenant->slug }}</td>
                                <td>{{ $tenant->created_at }}</td>
                                <td class="text-end">
                                    <a href="{{ route('sysadmin.tenants.edit', $tenant) }}" class="btn btn-xs btn-outline-primary">
                                        Editar
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">
                                    Sin tenants registrados aún.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Últimas facturas (si aplica) --}}
        @if(isset($recentInvoices))
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm">
                <div class="card-header">
                    Últimas facturas a tenants
                </div>
                <div class="card-body p-0">
                    <table class="table mb-0 table-sm">
                        <thead>
                            <tr>
                                <th>Tenant</th>
                                <th>Monto</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($recentInvoices as $invoice)
                            <tr>
                                <td>{{ $invoice->tenant->name ?? '#' }}</td>
                                <td>${{ number_format($invoice->amount, 2) }}</td>
                                <td>{{ $invoice->status }}</td>
                                <td>{{ $invoice->created_at }}</td>
                                <td class="text-end">
                                    <a href="{{ route('sysadmin.invoices.show', $invoice) }}" class="btn btn-xs btn-outline-secondary">
                                        Ver
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">
                                    Aún no hay facturas registradas.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>

</div>
@endsection
