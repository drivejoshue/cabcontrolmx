@extends('layouts.sysadmin') {{-- ajusta al layout que uses --}}

@section('content')
    <div class="container-fluid">
        <h1>Reporte de comisiones sugeridas</h1>

        <p>
            <strong>Tenant:</strong> {{ $tenant->id }} - {{ $tenant->name }}
        </p>

        {{-- Filtros --}}
        <form method="GET" class="row g-3 mb-3">
            <div class="col-md-3">
                <label for="from" class="form-label">Desde</label>
                <input type="date" id="from" name="from" class="form-control"
                       value="{{ $filters['from'] ?? '' }}">
            </div>
            <div class="col-md-3">
                <label for="to" class="form-label">Hasta</label>
                <input type="date" id="to" name="to" class="form-control"
                       value="{{ $filters['to'] ?? '' }}">
            </div>
            <div class="col-md-3">
                <label for="percent" class="form-label">% Comisión sugerida</label>
                <input type="number" step="0.01" id="percent" name="percent" class="form-control"
                       value="{{ $filters['percent'] ?? 15 }}">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Aplicar filtros</button>
            </div>
        </form>

        {{-- Resumen global --}}
        <div class="mb-3">
            <h4>Resumen</h4>
            <ul>
                <li>Viajes terminados: <strong>{{ $totalRides }}</strong></li>
                <li>Monto base total: <strong>${{ number_format($totalBase, 2) }}</strong></li>
                <li>Comisión sugerida total ({{ $filters['percent'] }}%):
                    <strong>${{ number_format($totalCommission, 2) }}</strong>
                </li>
            </ul>
        </div>

        {{-- Totales por driver --}}
        <div class="mb-4">
            <h4>Totales por conductor</h4>
            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>Driver</th>
                        <th>Servicios</th>
                        <th>Monto base</th>
                        <th>Comisión sugerida</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($totalsByDriver as $d)
                        <tr>
                            <td>{{ $d->driver_name }} (ID: {{ $d->driver_id }})</td>
                            <td>{{ $d->rides_count }}</td>
                            <td>${{ number_format($d->base_sum, 2) }}</td>
                            <td>${{ number_format($d->commission_sum, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">Sin viajes en el periodo seleccionado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Detalle por viaje --}}
        <div class="mb-4">
            <h4>Detalle de viajes</h4>
            <table class="table table-sm table-bordered">
                <thead>
                    <tr>
                        <th>ID Viaje</th>
                        <th>Fecha</th>
                        <th>Driver</th>
                        <th>Vehículo</th>
                        <th>Monto base</th>
                        <th>Comisión sugerida</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                        <tr>
                            <td>{{ $r->ride_id }}</td>
                            <td>{{ $r->finished_at }}</td>
                            <td>{{ $r->driver_name }} ({{ $r->driver_id }})</td>
                            <td>
                                @if($r->vehicle_id)
                                    {{ $r->economico }} / {{ $r->plate }}
                                @else
                                    -
                                @endif
                            </td>
                            <td>${{ number_format($r->base_amount, 2) }}</td>
                            <td>${{ number_format($r->commission, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">Sin resultados para este rango de fechas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
