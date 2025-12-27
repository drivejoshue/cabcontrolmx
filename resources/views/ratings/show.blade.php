@extends('layouts.admin')

@section('title', 'Calificaciones del Driver - ' . ($driverInfo->name ?? 'Driver'))

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="bi bi-person-badge me-2"></i>
                        Detalles de Calificaciones - Driver
                    </h3>
                    <div class="card-tools">
                        <a href="{{ route('ratings.index') }}" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-arrow-left me-1"></i> Volver al Reporte
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Información del Driver Mejorada -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        Información del Driver
                    </h5>
                </div>
                <div class="card-body text-center">
                    @if($driverInfo)
                        <div class="mb-4">
                            @if($driverInfo->foto_path)
                                <img src="{{ asset('storage/' . $driverInfo->foto_path) }}" 
                                     alt="{{ $driverInfo->name }}" 
                                     class="rounded-circle mb-3" 
                                     style="width: 100px; height: 100px; object-fit: cover;">
                            @else
                                <div class="bg-blue rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" 
                                     style="width: 100px; height: 100px;">
                                    <i class="bi bi-person text-white" style="font-size: 2.5rem;"></i>
                                </div>
                            @endif
                            <h4 class="mb-1">{{ $driverInfo->name }}</h4>
                            <p class="text-muted mb-2">
                                <i class="bi bi-telephone me-1"></i>{{ $driverInfo->phone }}
                            </p>
                            
                            @php
                                $statusColors = [
                                    'idle' => 'green',
                                    'busy' => 'yellow',
                                    'offline' => 'secondary',
                                    'on_ride' => 'blue'
                                ];
                            @endphp
                            <span class="badge bg-{{ $statusColors[$driverInfo->status] ?? 'secondary' }} rounded-pill mb-3">
                                <i class="bi bi-circle-fill me-1"></i>{{ ucfirst($driverInfo->status) }}
                            </span>
                            
                            <div class="star-rating mb-3">
                                @for($i = 1; $i <= 5; $i++)
                                    <i class="bi bi-star-fill display-6 {{ $i <= $driverSummary->avg_rating ? 'text-yellow' : 'text-muted' }} me-1"></i>
                                @endfor
                                <div class="mt-2">
                                    <h3 class="text-blue">{{ number_format($driverSummary->avg_rating, 1) }}/5</h3>
                                    <small class="text-muted">{{ $driverSummary->total_ratings }} calificaciones</small>
                                </div>
                            </div>
                        </div>

                        <!-- Estadísticas de Viajes -->
                        <div class="border-top pt-3">
                            <h6 class="text-center mb-3">
                                <i class="bi bi-graph-up me-1"></i>Estadísticas de Viajes
                            </h6>
                            <div class="row text-center">
                                <div class="col-6 mb-3">
                                    <div class="border rounded p-2">
                                        <small class="text-muted d-block">Total Viajes</small>
                                        <strong class="text-blue fs-5">{{ $driverRidesStats->total_rides ?? 0 }}</strong>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="border rounded p-2">
                                        <small class="text-muted d-block">Completados</small>
                                        <strong class="text-green fs-5">{{ $driverRidesStats->completed_rides ?? 0 }}</strong>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded p-2">
                                        <small class="text-muted d-block">Tasa Éxito</small>
                                        <strong class="text-teal fs-5">{{ number_format($driverRidesStats->completion_rate ?? 0, 1) }}%</strong>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded p-2">
                                        <small class="text-muted d-block">Distancia Prom.</small>
                                        <strong class="text-orange fs-5">{{ number_format(($driverRidesStats->avg_distance ?? 0) / 1000, 1) }} km</strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Métricas Detalladas -->
                        <div class="border-top pt-3 mt-3">
                            <h6 class="text-center mb-3">
                                <i class="bi bi-bar-chart me-1"></i>Métricas de Calificación
                            </h6>
                            <div class="row text-center">
                                <div class="col-6 mb-3">
                                    <div class="border rounded p-2">
                                        <small class="text-muted d-block">Puntualidad</small>
                                        <strong class="text-teal">{{ number_format($driverSummary->avg_punctuality, 1) }}</strong>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="border rounded p-2">
                                        <small class="text-muted d-block">Cortesía</small>
                                        <strong class="text-green">{{ number_format($driverSummary->avg_courtesy, 1) }}</strong>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded p-2">
                                        <small class="text-muted d-block">Vehículo</small>
                                        <strong class="text-orange">{{ number_format($driverSummary->avg_vehicle_condition, 1) }}</strong>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded p-2">
                                        <small class="text-muted d-block">Conducción</small>
                                        <strong class="text-blue">{{ number_format($driverSummary->avg_driving_skills, 1) }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Distribución de Estrellas -->
                        <div class="border-top pt-3 mt-3">
                            <h6 class="text-center mb-3">
                                <i class="bi bi-pie-chart me-1"></i>Distribución de Calificaciones
                            </h6>
                            @php
                                $starsDistribution = [
                                    5 => ['count' => $driverSummary->five_stars, 'color' => 'green'],
                                    4 => ['count' => $driverSummary->four_stars, 'color' => 'teal'],
                                    3 => ['count' => $driverSummary->three_stars, 'color' => 'yellow'],
                                    2 => ['count' => $driverSummary->two_stars, 'color' => 'orange'],
                                    1 => ['count' => $driverSummary->one_stars, 'color' => 'red']
                                ];
                            @endphp
                            
                            @foreach($starsDistribution as $stars => $data)
                                @php
                                    $percentage = $driverSummary->total_ratings > 0 ? 
                                        ($data['count'] / $driverSummary->total_ratings) * 100 : 0;
                                @endphp
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-sm">
                                            @for($i = 0; $i < $stars; $i++)
                                                <i class="bi bi-star-fill text-{{ $data['color'] }} me-1"></i>
                                            @endfor
                                        </span>
                                        <span class="text-sm fw-bold">{{ $data['count'] }}</span>
                                    </div>
                                    <div class="progress" style="height: 8px; border-radius: 4px;">
                                        <div class="progress-bar bg-{{ $data['color'] }}" 
                                             style="width: {{ $percentage }}%; border-radius: 4px;"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-person-x display-4 mb-3"></i>
                            <p>Driver no encontrado</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Historial de Calificaciones Mejorado -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-clock-history me-2"></i>
                        Historial de Calificaciones
                        <span class="badge bg-blue rounded-pill ms-2">{{ $ratings->total() }}</span>
                    </h5>
                </div>
                <div class="card-body p-0">
                    @if($ratings->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th><i class="bi bi-calendar me-1"></i> Fecha</th>
                                        <th><i class="bi bi-person me-1"></i> Pasajero</th>
                                        <th><i class="bi bi-star me-1"></i> Calificación</th>
                                        <th><i class="bi bi-chat-left me-1"></i> Comentario</th>
                                        <th><i class="bi bi-graph-up me-1"></i> Detalles</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($ratings as $rating)
                                        <tr class="align-middle">
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <small class="text-muted fw-medium">
                                                        {{ \Carbon\Carbon::parse($rating->created_at)->format('d/m/Y') }}
                                                    </small>
                                                    <small class="text-muted">
                                                        {{ \Carbon\Carbon::parse($rating->created_at)->format('H:i') }}
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <strong class="d-block">{{ $rating->passenger_name ?? 'N/A' }}</strong>
                                                    <small class="text-muted">{{ $rating->passenger_phone ?? 'N/A' }}</small>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    @for($i = 1; $i <= 5; $i++)
                                                        <i class="bi bi-star-fill {{ $i <= $rating->rating ? 'text-yellow' : 'text-muted' }} me-1"></i>
                                                    @endfor
                                                    <span class="badge bg-secondary rounded-pill ms-2">{{ $rating->rating }}/5</span>
                                                </div>
                                            </td>
                                            <td>
                                                @if($rating->comment)
                                                    <div class="comment-tooltip" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $rating->comment }}">
                                                        <p class="mb-0 text-sm" style="max-width: 200px;">
                                                            {{ \Illuminate\Support\Str::limit($rating->comment, 60) }}
                                                        </p>
                                                    </div>
                                                @else
                                                    <span class="text-muted text-sm">
                                                        <i class="bi bi-dash-circle me-1"></i>Sin comentario
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-1">
                                                    @if($rating->punctuality)
                                                        <span class="badge bg-teal text-white rounded-pill" title="Puntualidad">
                                                            <i class="bi bi-clock me-1"></i>{{ $rating->punctuality }}
                                                        </span>
                                                    @endif
                                                    @if($rating->courtesy)
                                                        <span class="badge bg-green text-white rounded-pill" title="Cortesía">
                                                            <i class="bi bi-hand-thumbs-up me-1"></i>{{ $rating->courtesy }}
                                                        </span>
                                                    @endif
                                                    @if($rating->vehicle_condition)
                                                        <span class="badge bg-orange text-white rounded-pill" title="Vehículo">
                                                            <i class="bi bi-car-front me-1"></i>{{ $rating->vehicle_condition }}
                                                        </span>
                                                    @endif
                                                    @if($rating->driving_skills)
                                                        <span class="badge bg-blue text-white rounded-pill" title="Conducción">
                                                            <i class="bi bi-speedometer2 me-1"></i>{{ $rating->driving_skills }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center p-5">
                            <i class="bi bi-star display-4 text-muted mb-3"></i>
                            <p class="text-muted mb-0">No hay calificaciones para este driver</p>
                        </div>
                    @endif
                </div>
                @if($ratings->hasPages())
                    <div class="card-footer">
                        {{ $ratings->links('pagination::simple-bootstrap-4') }}
                    </div>
                @endif
            </div>

            <!-- Gráfico de Evolución -->
            @if($monthlyDriverRatings->count() > 1)
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-graph-up me-2"></i>
                            Evolución Mensual
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="driverMonthlyChart" style="height: 250px;"></canvas>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Inicializar tooltips
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    });

    @if($monthlyDriverRatings->count() > 1)
    document.addEventListener('DOMContentLoaded', function() {
        var ctx = document.getElementById('driverMonthlyChart').getContext('2d');
        var driverMonthlyChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: {!! json_encode($monthlyDriverRatings->pluck('month')) !!},
                datasets: [{
                    label: 'Calificación General',
                    data: {!! json_encode($monthlyDriverRatings->pluck('avg_rating')) !!},
                    borderColor: '#206bc4', // Azul Tabler
                    backgroundColor: 'rgba(32, 107, 196, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Puntualidad',
                    data: {!! json_encode($monthlyDriverRatings->pluck('avg_punctuality')) !!},
                    borderColor: '#18a997', // Teal Tabler
                    backgroundColor: 'rgba(24, 169, 151, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.4
                }, {
                    label: 'Cortesía',
                    data: {!! json_encode($monthlyDriverRatings->pluck('avg_courtesy')) !!},
                    borderColor: '#2fb344', // Verde Tabler
                    backgroundColor: 'rgba(47, 179, 68, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        min: 0,
                        max: 5,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    });
    @endif
</script>
@endpush

@push('styles')
<style>
/* Colores de Tabler para mejor compatibilidad */
.text-blue { color: #206bc4 !important; }
.text-teal { color: #18a997 !important; }
.text-green { color: #2fb344 !important; }
.text-yellow { color: #f59f00 !important; }
.text-red { color: #d63939 !important; }
.text-orange { color: #f76707 !important; }
.text-azure { color: #4299e1 !important; }

.bg-blue { background-color: #206bc4 !important; }
.bg-teal { background-color: #18a997 !important; }
.bg-green { background-color: #2fb344 !important; }
.bg-yellow { background-color: #f59f00 !important; }
.bg-red { background-color: #d63939 !important; }
.bg-orange { background-color: #f76707 !important; }
.bg-azure { background-color: #4299e1 !important; }

/* Asegurar contraste adecuado para los badges */
.bg-blue, .bg-teal, .bg-green, .bg-red {
    color: white !important;
}

.bg-yellow, .bg-orange {
    color: #212529 !important;
}

/* Corregir el paginado de Bootstrap 4 para AdminKit */
.pagination {
    display: flex;
    padding-left: 0;
    list-style: none;
    border-radius: 0.25rem;
    margin-bottom: 0;
    flex-wrap: wrap;
    justify-content: center;
}

.page-item:first-child .page-link {
    margin-left: 0;
    border-top-left-radius: 0.25rem;
    border-bottom-left-radius: 0.25rem;
}

.page-item:last-child .page-link {
    border-top-right-radius: 0.25rem;
    border-bottom-right-radius: 0.25rem;
}

.page-item.active .page-link {
    z-index: 1;
    color: #fff;
    background-color: #206bc4;
    border-color: #206bc4;
}

.page-link {
    position: relative;
    display: block;
    padding: 0.5rem 0.75rem;
    margin-left: -1px;
    line-height: 1.25;
    color: #206bc4;
    background-color: #fff;
    border: 1px solid var(--tblr-border-color);
    font-size: 0.875rem;
}

.page-link:hover {
    z-index: 2;
    color: #165ba3;
    text-decoration: none;
    background-color: var(--tblr-bg-surface-secondary);
    border-color: var(--tblr-border-color);
}

.page-item.disabled .page-link {
    color: #adb5bd;
    pointer-events: none;
    cursor: auto;
    background-color: #fff;
    border-color: var(--tblr-border-color);
}

/* Hacer las flechas más pequeñas */
.page-link i.bi {
    font-size: 0.75rem;
    vertical-align: middle;
}

/* Para pantallas pequeñas */
@media (max-width: 768px) {
    .pagination {
        font-size: 0.8rem;
    }
    
    .page-link {
        padding: 0.375rem 0.5rem;
        margin: 1px;
    }
}

/* Estilos existentes que mantienen */
.star-rating {
    line-height: 1;
}
.progress {
    border-radius: 4px;
}
.badge {
    font-size: 0.75em;
}
.comment-tooltip {
    cursor: help;
}
.card {
    border-radius: 8px;
}
.avatar-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Asegurar que los badges de Bootstrap Icons se vean bien */
.badge i.bi {
    font-size: 0.8em;
    vertical-align: text-top;
}

/* Estilos para la tabla */
.table th {
    border-top: none;
    font-weight: 600;
    color: var(--tblr-body-color);
    background-color: var(--tblr-bg-surface-tertiary);
}

.table-hover tbody tr:hover {
    background-color: var(--tblr-bg-surface-secondary);
}

.card-header {
    background-color: var(--tblr-card-cap-bg);
    border-bottom: 1px solid var(--tblr-border-color);
    padding: 1rem 1.25rem;
}

.card-footer {
    background-color: var(--tblr-card-cap-bg);
    border-top: 1px solid var(--tblr-border-color);
    padding: 0.75rem 1.25rem;
}
</style>
@endpush