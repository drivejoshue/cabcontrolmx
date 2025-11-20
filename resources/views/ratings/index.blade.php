@extends('layouts.admin')

@section('title', 'Reporte de Calificaciones')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary">
                    <h3 class="card-title text-white">
                        <i class="fas fa-chart-bar mr-2"></i>
                        Reporte General de Calificaciones
                    </h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Resumen General -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card bg-gradient-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="text-white">{{ number_format($generalSummary->overall_avg_rating, 1) }}/5</h3>
                            <p class="text-white mb-0">Calificación Promedio</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-star fa-2x text-white-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card bg-gradient-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="text-white">{{ $generalSummary->total_ratings }}</h3>
                            <p class="text-white mb-0">Total Calificaciones</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-clipboard-list fa-2x text-white-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card bg-gradient-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="text-white">{{ $generalSummary->rated_drivers }}</h3>
                            <p class="text-white mb-0">Drivers Calificados</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-user-friends fa-2x text-white-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card bg-gradient-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="text-white">{{ $generalSummary->rated_passengers }}</h3>
                            <p class="text-white mb-0">Pasajeros Calificados</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-users fa-2x text-white-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Distribución de Estrellas -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-chart-pie mr-2"></i>
                        Distribución de Calificaciones
                    </h3>
                </div>
                <div class="card-body">
                    @php
                        $stars = [
                            5 => ['count' => $generalSummary->five_stars, 'color' => 'success', 'label' => '5 Estrellas'],
                            4 => ['count' => $generalSummary->four_stars, 'color' => 'info', 'label' => '4 Estrellas'],
                            3 => ['count' => $generalSummary->three_stars, 'color' => 'warning', 'label' => '3 Estrellas'],
                            2 => ['count' => $generalSummary->two_stars, 'color' => 'orange', 'label' => '2 Estrellas'],
                            1 => ['count' => $generalSummary->one_stars, 'color' => 'danger', 'label' => '1 Estrella']
                        ];
                    @endphp
                    
                    @foreach($stars as $starsCount => $data)
                        @php
                            $percentage = $generalSummary->total_ratings > 0 ? 
                                ($data['count'] / $generalSummary->total_ratings) * 100 : 0;
                        @endphp
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-sm">
                                    <i class="fas fa-star text-{{ $data['color'] }} mr-1"></i>
                                    {{ $data['label'] }}
                                </span>
                                <span class="text-sm font-weight-bold">
                                    {{ $data['count'] }} ({{ number_format($percentage, 1) }}%)
                                </span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-{{ $data['color'] }}" 
                                     style="width: {{ $percentage }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Alertas de Calificaciones Bajas -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-danger">
                    <h3 class="card-title text-white">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        Alertas de Calificaciones Bajas
                    </h3>
                </div>
                <div class="card-body p-0">
                    @if($lowRatingsAlerts->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Driver</th>
                                        <th>Calificación</th>
                                        <th>Bajas</th>
                                        <th>Total</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($lowRatingsAlerts as $alert)
                                        <tr>
                                            <td>
                                                <strong>{{ $alert->driver_name }}</strong>
                                            </td>
                                            <td>
                                                <span class="badge badge-danger">
                                                    {{ number_format($alert->avg_rating, 1) }}/5
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-warning">{{ $alert->low_ratings }}</span>
                                            </td>
                                            <td>
                                                <span class="badge badge-secondary">{{ $alert->total_ratings }}</span>
                                            </td>
                                            <td>
                                                <a href="{{ route('ratings.driver.show', $alert->driver_id) }}" 
                                                   class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center p-4">
                            <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                            <p class="text-muted mb-0">No hay alertas de calificaciones bajas</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Top Drivers -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-success">
                    <h3 class="card-title text-white">
                        <i class="fas fa-trophy mr-2"></i>
                        Top Drivers Mejor Calificados
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Driver</th>
                                    <th>Contacto</th>
                                    <th>Calificación</th>
                                    <th>Total</th>
                                    <th>Puntualidad</th>
                                    <th>Cortesía</th>
                                    <th>Vehículo</th>
                                    <th>Conducción</th>
                                    <th>5★</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($driverRatings as $driver)
                                    <tr>
                                        <td>
                                            <strong>{{ $driver->driver_name }}</strong>
                                        </td>
                                        <td>
                                            <small class="text-muted">{{ $driver->driver_phone }}</small>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                @for($i = 1; $i <= 5; $i++)
                                                    <i class="fas fa-star {{ $i <= $driver->avg_rating ? 'text-warning' : 'text-muted' }} mr-1"></i>
                                                @endfor
                                                <small class="ml-2 font-weight-bold">{{ number_format($driver->avg_rating, 1) }}</small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-primary">{{ $driver->total_ratings }}</span>
                                        </td>
                                        <td>{{ number_format($driver->avg_punctuality, 1) }}</td>
                                        <td>{{ number_format($driver->avg_courtesy, 1) }}</td>
                                        <td>{{ number_format($driver->avg_vehicle_condition, 1) }}</td>
                                        <td>{{ number_format($driver->avg_driving_skills, 1) }}</td>
                                        <td>
                                            <span class="badge bg-success">{{ $driver->five_stars }}</span>
                                        </td>
                                        <td>
                                            <a href="{{ route('ratings.show', $driver->driver_id) }}" 
                                               class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> Ver
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center py-4">
                                            <i class="fas fa-star fa-2x text-muted mb-2"></i>
                                            <p class="text-muted mb-0">No hay datos de calificaciones disponibles</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tendencias Mensuales -->
    @if($monthlyTrends->count() > 0)
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-chart-line mr-2"></i>
                        Tendencias Mensuales
                    </h3>
                </div>
                <div class="card-body">
                    <div class="chart">
                        <canvas id="monthlyTrendsChart" style="height: 300px;"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    @if($monthlyTrends->count() > 0)
    document.addEventListener('DOMContentLoaded', function() {
        var ctx = document.getElementById('monthlyTrendsChart').getContext('2d');
        var monthlyTrendsChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: {!! json_encode($monthlyTrends->pluck('month')) !!},
                datasets: [{
                    label: 'Calificación Promedio',
                    data: {!! json_encode($monthlyTrends->pluck('avg_rating')) !!},
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Total Calificaciones',
                    data: {!! json_encode($monthlyTrends->pluck('total_ratings')) !!},
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    });
    @endif
</script>
@endpush