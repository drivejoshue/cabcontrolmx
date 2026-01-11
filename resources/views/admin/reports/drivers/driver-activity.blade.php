@extends('layouts.admin')

@section('title', 'Reporte de Actividades del Conductor')

@push('styles')
<style>
.chart-container {
    position: relative;
    height: 300px;
    width: 100%;
}
.driver-card {
    transition: all 0.3s ease;
    border-left: 4px solid #0d6efd;
}
.driver-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}
.stat-card {
    border-radius: 10px;
    overflow: hidden;
}
.stat-icon {
    font-size: 2.5rem;
    opacity: 0.8;
}
.rating-stars {
    color: #ffc107;
    font-size: 1.2rem;
}
.efficiency-badge {
    font-size: 0.85rem;
    padding: 4px 8px;
}
</style>
@endpush

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1 class="h3 mb-3">
            <i class="bi bi-graph-up me-2"></i>Reporte de Actividades del Conductor
        </h1>
        
        <div class="card">
            <div class="card-body">
<form method="GET" action="{{ route('admin.reports.drivers.activity') }}" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Conductor</label>
                        <select name="driver_id" class="form-select">
                            <option value="">Todos los conductores</option>
                            @foreach($drivers as $driver)
                                <option value="{{ $driver->id }}" {{ $driverId == $driver->id ? 'selected' : '' }}>
                                    {{ $driver->name }} ({{ $driver->phone }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Fecha Inicio</label>
                        <input type="date" name="start_date" class="form-control" value="{{ $startDate }}">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Fecha Fin</label>
                        <input type="date" name="end_date" class="form-control" value="{{ $endDate }}">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Agrupar por</label>
                        <select name="group_by" class="form-select">
                            <option value="day" {{ $groupBy == 'day' ? 'selected' : '' }}>Día</option>
                            <option value="week" {{ $groupBy == 'week' ? 'selected' : '' }}>Semana</option>
                            <option value="month" {{ $groupBy == 'month' ? 'selected' : '' }}>Mes</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="bi bi-filter me-1"></i>Filtrar
                        </button>
                       
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Resumen General -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card stat-card">
            <div class="card-body">
                <h5 class="card-title mb-4">
                    <i class="bi bi-bar-chart me-2"></i>Resumen General
                </h5>
                <div class="row">
                    <div class="col-md-2 mb-3">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon text-primary me-3">
                                <i class="bi bi-people"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">Conductores</h6>
                                <h3 class="mb-0">{{ $reportData['summary']['total_drivers'] }}</h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon text-success me-3">
                                <i class="bi bi-car-front"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">Servicios</h6>
                                <h3 class="mb-0">{{ $reportData['summary']['total_rides'] }}</h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon text-warning me-3">
                                <i class="bi bi-cash-stack"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">Ingresos Totales</h6>
                                <h3 class="mb-0">${{ number_format($reportData['summary']['total_revenue'] ?? 0, 2) }}</h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon text-info me-3">
                                <i class="bi bi-clock-history"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">Turnos</h6>
                                <h3 class="mb-0">{{ $reportData['summary']['total_shifts'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon text-danger me-3">
                                <i class="bi bi-star"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">Rating Promedio</h6>
                                <h3 class="mb-0">{{ number_format($reportData['summary']['avg_rating'] ?? 0, 1) }}
                                    <span class="rating-stars">
                                        @php
                                            $avgRating = $reportData['summary']['avg_rating'] ?? 0;
                                            $roundedRating = round($avgRating);
                                        @endphp
                                        @for($i = 1; $i <= 5; $i++)
                                            <i class="bi bi-star{{ $i <= $roundedRating ? '-fill' : '' }}"></i>
                                        @endfor
                                    </span>
                                </h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if($driverId && $driverDetails)
<!-- Detalles del Conductor Seleccionado -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card driver-card">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-2">
                        @if($driverDetails->foto_path)
                            <img src="{{ asset('storage/' . $driverDetails->foto_path) }}" 
                                 class="rounded-circle img-thumbnail" width="100" height="100">
                        @else
                            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" 
                                 style="width: 100px; height: 100px;">
                                <i class="bi bi-person text-white" style="font-size: 3rem;"></i>
                            </div>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <h4 class="mb-1">{{ $driverDetails->name }}</h4>
                        <p class="text-muted mb-2">
                            <i class="bi bi-telephone me-1"></i>{{ $driverDetails->phone }}
                            @if($driverDetails->email)
                                <span class="mx-2">•</span>
                                <i class="bi bi-envelope me-1"></i>{{ $driverDetails->email }}
                            @endif
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-{{ $driverDetails->status == 'idle' ? 'success' : ($driverDetails->status == 'busy' ? 'warning' : 'secondary') }}">
                                {{ ucfirst($driverDetails->status) }}
                            </span>
                            @if($driverDetails->last_seen_at)
                                <span class="badge bg-info">
                                    <i class="bi bi-clock-history me-1"></i>
                                    Última vez: {{ $driverDetails->last_seen_at->diffForHumans() }}
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-4">
                        @if($driverDetails->vehicleAssignments && $driverDetails->vehicleAssignments->count() > 0)
                            @php $vehicle = $driverDetails->vehicleAssignments->first()->vehicle; @endphp
                            <div class="d-flex align-items-center">
                                <i class="bi bi-car-front-fill fs-4 text-primary me-2"></i>
                                <div>
                                    <small class="text-muted d-block">Vehículo asignado</small>
                                    <strong>{{ $vehicle->brand ?? '' }} {{ $vehicle->model ?? '' }}</strong>
                                    <span class="badge bg-light text-dark ms-2">{{ $vehicle->plate ?? '' }}</span>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Gráficos -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="bi bi-graph-up me-2"></i>Ingresos por Período
                </h5>
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="bi bi-pie-chart me-2"></i>Métodos de Pago
                </h5>
                <div class="chart-container">
                    <canvas id="paymentChart"></canvas>
                </div>
                <div class="mt-3">
                    @foreach($reportData['payment_methods'] as $method)
                        <div class="d-flex justify-content-between mb-2">
                            <span>{{ ucfirst($method->payment_method) }}</span>
                            <span class="fw-bold">${{ number_format($method->amount, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Estadísticas por Conductor -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-4">
                    <i class="bi bi-table me-2"></i>Estadísticas por Conductor
                </h5>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Conductor</th>
                                <th>Servicios</th>
                                <th>Ingresos Total</th>
                                <th>Efectivo</th>
                                <th>Transferencia</th>
                                <th>Tarjeta</th>
                                <th>Turnos</th>
                                <th>Horas</th>
                                <th>Rating</th>
                                <th>Eficiencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($reportData['driver_stats'] as $stat)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            @if($stat['driver']->foto_path)
                                                <img src="{{ asset('storage/' . $stat['driver']->foto_path) }}" 
                                                     class="rounded-circle me-2" width="32" height="32">
                                            @else
                                                <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center me-2" 
                                                     style="width: 32px; height: 32px;">
                                                    <i class="bi bi-person text-white"></i>
                                                </div>
                                            @endif
                                            <div>
                                                <strong>{{ $stat['driver']->name }}</strong>
                                                <div class="small text-muted">{{ $stat['driver']->phone }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <strong>{{ $stat['rides']['total'] ?? 0 }}</strong>
                                        <div class="small text-muted">
                                            {{ $stat['rides']['avg_distance'] ?? 0 }} km/serv
                                        </div>
                                    </td>
                                    <td>
                                        <strong class="text-success">${{ number_format($stat['rides']['revenue'] ?? 0, 2) }}</strong>
                                    </td>
                                    <td>${{ number_format($stat['rides']['cash'] ?? 0, 2) }}</td>
                                    <td>${{ number_format($stat['rides']['transfer'] ?? 0, 2) }}</td>
                                    <td>${{ number_format($stat['rides']['card'] ?? 0, 2) }}</td>
                                    <td>
                                        {{ $stat['shifts']['total'] ?? 0 }}
                                        <div class="small text-muted">
                                          {{ $stat['shifts']['avg_shift_minutes'] ?? 0 }} min/turno

                                        </div>
                                    </td>
                                    <td>{{ $stat['shifts']['total_hours'] ?? 0 }} hrs</td>
                                    <td>
                                        @if($stat['ratings'])
                                            <div class="d-flex align-items-center">
                                                <span class="me-2">{{ number_format($stat['ratings']['avg_rating'], 1) }}</span>
                                                <div class="rating-stars">
                                                    @php
                                                        $driverRating = round($stat['ratings']['avg_rating']);
                                                    @endphp
                                                    @for($i = 1; $i <= 5; $i++)
                                                        <i class="bi bi-star{{ $i <= $driverRating ? '-fill' : '' }}"></i>
                                                    @endfor
                                                </div>
                                            </div>
                                            <small class="text-muted">{{ $stat['ratings']['total'] }} ratings</small>
                                        @else
                                            <span class="text-muted">Sin ratings</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($stat['efficiency'])
                                            <span class="badge bg-success efficiency-badge">
                                                {{ $stat['efficiency']['rides_per_hour'] ?? 0 }} serv/hora
                                            </span>
                                            <br>
                                            <span class="badge bg-info efficiency-badge mt-1">
                                                ${{ $stat['efficiency']['revenue_per_hour'] ?? 0 }}/hora
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Movimientos de Wallet (si hay conductor específico) -->
@if($driverId && count($reportData['wallet_movements'] ?? []) > 0)
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="bi bi-wallet me-2"></i>Movimientos de Wallet
                </h5>
                
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Servicio #</th>
                                <th>Monto</th>
                                <th>Saldo Después</th>
                                <th>Descripción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($reportData['wallet_movements'] as $movement)
                                <tr>
                                    <td>{{ $movement->created_at->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <span class="badge bg-{{ $movement->direction == 'credit' ? 'success' : 'danger' }}">
                                            {{ ucfirst($movement->type) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($movement->ride_id)
                                            <a href="{{ route('admin.rides.show', $movement->ride_id) }}" class="text-decoration-none">

                                                #{{ $movement->ride_id }}
                                            </a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="{{ $movement->direction == 'credit' ? 'text-success' : 'text-danger' }}">
                                        {{ $movement->direction == 'credit' ? '+' : '-' }}${{ number_format($movement->amount, 2) }}
                                    </td>
                                    <td>${{ number_format($movement->balance_after, 2) }}</td>
                                    <td>{{ $movement->description }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Horas Pico -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="bi bi-clock-history me-2"></i>Distribución por Horas
                </h5>
                <div class="chart-container" style="height: 250px;">
                    <canvas id="peakHoursChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gráfico de Ingresos por Período
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    const revenueChart = new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: @json($reportData['revenue_by_period']->pluck('period')),
            datasets: [{
                label: 'Ingresos ($)',
                data: @json($reportData['revenue_by_period']->pluck('total_revenue')),
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        drawBorder: false
                    },
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toLocaleString();
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });

    // Gráfico de Métodos de Pago
    const paymentCtx = document.getElementById('paymentChart').getContext('2d');
    const paymentChart = new Chart(paymentCtx, {
        type: 'doughnut',
        data: {
            labels: @json($reportData['payment_methods']->pluck('payment_method')),
            datasets: [{
                data: @json($reportData['payment_methods']->pluck('amount')),
                backgroundColor: [
                    '#0d6efd', // cash - azul
                    '#198754', // transfer - verde
                    '#6f42c1', // card - morado
                    '#fd7e14', // corp - naranja
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // Gráfico de Horas Pico
    const peakHoursData = @json($reportData['peak_hours']->toArray());
    const peakHoursLabels = peakHoursData.map(item => item.hour + ':00');
    const peakHoursValues = peakHoursData.map(item => item.ride_count);
    
    const peakCtx = document.getElementById('peakHoursChart').getContext('2d');
    if (peakCtx) {
        const peakChart = new Chart(peakCtx, {
            type: 'bar',
            data: {
                labels: peakHoursLabels,
                datasets: [{
                    label: 'Servicios',
                    data: peakHoursValues,
                    backgroundColor: 'rgba(13, 110, 253, 0.7)',
                    borderColor: '#0d6efd',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Número de Servicios'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Hora del Día'
                        }
                    }
                }
            }
        });
    }
});
</script>
@endpush