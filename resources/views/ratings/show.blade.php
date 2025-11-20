@extends('layouts.admin')

@section('title', 'Calificaciones del Driver')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-info">
                    <h3 class="card-title text-white">
                        <i class="fas fa-user-tie mr-2"></i>
                        Detalles de Calificaciones - Driver
                    </h3>
                    <div class="card-tools">
                        <a href="{{ route('ratings.index') }}" class="btn btn-light btn-sm">
                            <i class="fas fa-arrow-left mr-1"></i> Volver
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Información del Driver -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-primary">
                    <h3 class="card-title text-white">
                        <i class="fas fa-info-circle mr-2"></i>
                        Información del Driver
                    </h3>
                </div>
                <div class="card-body text-center">
                    @if($driverInfo)
                        <div class="mb-4">
                            <i class="fas fa-user-circle fa-5x text-primary mb-3"></i>
                            <h4 class="mb-1">{{ $driverInfo->name }}</h4>
                            <p class="text-muted mb-3">{{ $driverInfo->phone }}</p>
                            
                            <div class="star-rating mb-3">
                                @for($i = 1; $i <= 5; $i++)
                                    <i class="fas fa-star fa-2x {{ $i <= $driverSummary->avg_rating ? 'text-warning' : 'text-muted' }} mr-1"></i>
                                @endfor
                                <div class="mt-2">
                                    <h3 class="text-primary">{{ number_format($driverSummary->avg_rating, 1) }}/5</h3>
                                    <small class="text-muted">{{ $driverSummary->total_ratings }} calificaciones</small>
                                </div>
                            </div>
                        </div>

                        <!-- Métricas Detalladas -->
                        <div class="border-top pt-3">
                            <h6 class="text-center mb-3">Métricas Específicas</h6>
                            <div class="row text-center">
                                <div class="col-6 mb-3">
                                    <div class="border rounded p-2">
                                        <small class="text-muted d-block">Puntualidad</small>
                                        <strong class="text-info">{{ number_format($driverSummary->avg_punctuality, 1) }}</strong>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="border rounded p-2">
                                        <small class="text-muted d-block">Cortesía</small>
                                        <strong class="text-success">{{ number_format($driverSummary->avg_courtesy, 1) }}</strong>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded p-2">
                                        <small class="text-muted d-block">Vehículo</small>
                                        <strong class="text-warning">{{ number_format($driverSummary->avg_vehicle_condition, 1) }}</strong>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded p-2">
                                        <small class="text-muted d-block">Conducción</small>
                                        <strong class="text-primary">{{ number_format($driverSummary->avg_driving_skills, 1) }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Distribución de Estrellas -->
                        <div class="border-top pt-3 mt-3">
                            <h6 class="text-center mb-3">Distribución de Calificaciones</h6>
                            @php
                                $starsDistribution = [
                                    5 => ['count' => $driverSummary->five_stars, 'color' => 'success'],
                                    4 => ['count' => $driverSummary->four_stars, 'color' => 'info'],
                                    3 => ['count' => $driverSummary->three_stars, 'color' => 'warning'],
                                    2 => ['count' => $driverSummary->two_stars, 'color' => 'orange'],
                                    1 => ['count' => $driverSummary->one_stars, 'color' => 'danger']
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
                                                <i class="fas fa-star text-{{ $data['color'] }} mr-1"></i>
                                            @endfor
                                        </span>
                                        <span class="text-sm font-weight-bold">{{ $data['count'] }}</span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-{{ $data['color'] }}" 
                                             style="width: {{ $percentage }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-user-slash fa-3x mb-3"></i>
                            <p>Driver no encontrado</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Historial de Calificaciones -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-history mr-2"></i>
                        Historial de Calificaciones
                        <span class="badge badge-primary ml-2">{{ $ratings->total() }}</span>
                    </h3>
                </div>
                <div class="card-body p-0">
                    @if($ratings->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Pasajero</th>
                                        <th>Calificación</th>
                                        <th>Comentario</th>
                                        <th>Detalles</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($ratings as $rating)
                                        <tr>
                                            <td>
                                                <small class="text-muted d-block">
                                                    {{ \Carbon\Carbon::parse($rating->created_at)->format('d/m/Y') }}
                                                </small>
                                                <small class="text-muted">
                                                    {{ \Carbon\Carbon::parse($rating->created_at)->format('H:i') }}
                                                </small>
                                            </td>
                                            <td>
                                                <strong class="d-block">{{ $rating->passenger_name ?? 'N/A' }}</strong>
                                                <small class="text-muted">{{ $rating->passenger_phone ?? 'N/A' }}</small>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    @for($i = 1; $i <= 5; $i++)
                                                        <i class="fas fa-star {{ $i <= $rating->rating ? 'text-warning' : 'text-muted' }} mr-1"></i>
                                                    @endfor
                                                    <span class="badge badge-secondary ml-2">{{ $rating->rating }}/5</span>
                                                </div>
                                            </td>
                                            <td>
                                                @if($rating->comment)
                                                    <p class="mb-0 text-sm" style="max-width: 200px;">
                                                        {{ \Illuminate\Support\Str::limit($rating->comment, 60) }}
                                                    </p>
                                                @else
                                                    <span class="text-muted text-sm">Sin comentario</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-1">
                                                    @if($rating->punctuality)
                                                        <span class="badge badge-info" title="Puntualidad">
                                                            P:{{ $rating->punctuality }}
                                                        </span>
                                                    @endif
                                                    @if($rating->courtesy)
                                                        <span class="badge badge-success" title="Cortesía">
                                                            C:{{ $rating->courtesy }}
                                                        </span>
                                                    @endif
                                                    @if($rating->vehicle_condition)
                                                        <span class="badge badge-warning" title="Vehículo">
                                                            V:{{ $rating->vehicle_condition }}
                                                        </span>
                                                    @endif
                                                    @if($rating->driving_skills)
                                                        <span class="badge badge-primary" title="Conducción">
                                                            CD:{{ $rating->driving_skills }}
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
                            <i class="fas fa-star fa-3x text-muted mb-3"></i>
                            <p class="text-muted mb-0">No hay calificaciones para este driver</p>
                        </div>
                    @endif
                </div>
                @if($ratings->hasPages())
                    <div class="card-footer">
                        {{ $ratings->links() }}
                    </div>
                @endif
            </div>

            <!-- Gráfico de Evolución -->
            @if($monthlyDriverRatings->count() > 1)
                <div class="card mt-4">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-line mr-2"></i>
                            Evolución Mensual
                        </h3>
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
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Puntualidad',
                    data: {!! json_encode($monthlyDriverRatings->pluck('avg_punctuality')) !!},
                    borderColor: '#17a2b8',
                    backgroundColor: 'rgba(23, 162, 184, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.4
                }, {
                    label: 'Cortesía',
                    data: {!! json_encode($monthlyDriverRatings->pluck('avg_courtesy')) !!},
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
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
.star-rating {
    line-height: 1;
}
.progress {
    border-radius: 4px;
}
.badge {
    font-size: 0.75em;
}
</style>
@endpush