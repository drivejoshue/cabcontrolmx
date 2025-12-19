

<?php $__env->startSection('title', 'Reporte de Calificaciones y Viajes'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-gradient-primary text-white">
                    <h3 class="card-title mb-0">
                        <i class="bi bi-graph-up me-2"></i>
                        Reporte General de Calificaciones y Viajes
                    </h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Resumen General Mejorado -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-gradient-info text-white shadow">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="text-white mb-1"><?php echo e(number_format($generalSummary->overall_avg_rating, 1)); ?>/5</h3>
                            <p class="text-white-50 mb-0">Calificación Promedio</p>
                            <small class="text-white-50"><?php echo e($generalSummary->total_ratings); ?> calificaciones</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-star-fill fa-2x text-white-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-gradient-success text-white shadow">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="text-white mb-1"><?php echo e($ridesStats->total_rides ?? 0); ?></h3>
                            <p class="text-white-50 mb-0">Total Viajes</p>
                            <small class="text-white-50"><?php echo e($ridesStats->completed_rides ?? 0); ?> completados</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-car-front-fill fa-2x text-white-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-gradient-warning text-white shadow">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="text-white mb-1"><?php echo e($generalSummary->rated_drivers); ?></h3>
                            <p class="text-white-50 mb-0">Drivers Calificados</p>
                            <small class="text-white-50"><?php echo e($generalSummary->rated_rides); ?> viajes calificados</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-person-badge-fill fa-2x text-white-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-gradient-purple text-white shadow">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="text-white mb-1"><?php echo e(number_format($ridesStats->completion_rate ?? 0, 1)); ?>%</h3>
                            <p class="text-white-50 mb-0">Tasa de Completación</p>
                            <small class="text-white-50">Viajes exitosos</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-check-circle-fill fa-2x text-white-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Distribución de Estrellas Mejorada -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-pie-chart-fill text-primary me-2"></i>
                        Distribución de Calificaciones
                    </h5>
                </div>
                <div class="card-body">
                    <?php
                        $stars = [
                            5 => ['count' => $generalSummary->five_stars, 'color' => 'success', 'icon' => 'bi-star-fill'],
                            4 => ['count' => $generalSummary->four_stars, 'color' => 'info', 'icon' => 'bi-star-fill'],
                            3 => ['count' => $generalSummary->three_stars, 'color' => 'warning', 'icon' => 'bi-star-fill'],
                            2 => ['count' => $generalSummary->two_stars, 'color' => 'orange', 'icon' => 'bi-star-fill'],
                            1 => ['count' => $generalSummary->one_stars, 'color' => 'danger', 'icon' => 'bi-star-fill']
                        ];
                    ?>
                    
                    <?php $__currentLoopData = $stars; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $starsCount => $data): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php
                            $percentage = $generalSummary->total_ratings > 0 ? 
                                ($data['count'] / $generalSummary->total_ratings) * 100 : 0;
                        ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-sm fw-medium">
                                    <?php for($i = 0; $i < $starsCount; $i++): ?>
                                        <i class="<?php echo e($data['icon']); ?> text-<?php echo e($data['color']); ?> me-1"></i>
                                    <?php endfor; ?>
                                    <?php echo e($starsCount); ?> Estrellas
                                </span>
                                <span class="text-sm fw-bold">
                                    <?php echo e($data['count']); ?> (<?php echo e(number_format($percentage, 1)); ?>%)
                                </span>
                            </div>
                            <div class="progress" style="height: 12px; border-radius: 6px;">
                                <div class="progress-bar bg-<?php echo e($data['color']); ?>" 
                                     style="width: <?php echo e($percentage); ?>%; border-radius: 6px;"></div>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>
        </div>

        <!-- Alertas de Calificaciones Bajas Mejoradas -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-danger text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Alertas de Calificaciones Bajas
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if($lowRatingsAlerts->count() > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th><i class="bi bi-person me-1"></i> Driver</th>
                                        <th><i class="bi bi-star me-1"></i> Calificación</th>
                                        <th><i class="bi bi-arrow-down me-1"></i> Bajas</th>
                                        <th><i class="bi bi-list-ol me-1"></i> Total</th>
                                        <th><i class="bi bi-gear me-1"></i> Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $__currentLoopData = $lowRatingsAlerts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $alert): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <tr class="align-middle">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if($alert->driver_foto_path): ?>
                                                        <img src="<?php echo e(asset('storage/' . $alert->driver_foto_path)); ?>" 
                                                             alt="<?php echo e($alert->driver_name); ?>" 
                                                             class="rounded-circle me-2" 
                                                             style="width: 40px; height: 40px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center me-2" 
                                                             style="width: 40px; height: 40px;">
                                                            <i class="bi bi-person text-white"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <strong class="d-block"><?php echo e($alert->driver_name); ?></strong>
                                                        <small class="text-muted"><?php echo e($alert->driver_phone); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-danger rounded-pill">
                                                    <i class="bi bi-star-fill me-1"></i><?php echo e(number_format($alert->avg_rating, 1)); ?>

                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning text-dark rounded-pill">
                                                    <?php echo e($alert->low_ratings); ?>

                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary rounded-pill">
                                                    <?php echo e($alert->total_ratings); ?>

                                                </span>
                                            </td>
                                            <td>
                                                <a href="<?php echo e(route('ratings.show', $alert->driver_id)); ?>" 
                                                   class="btn btn-sm btn-outline-primary rounded-pill">
                                                    <i class="bi bi-eye me-1"></i> Ver
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center p-5">
                            <i class="bi bi-check-circle text-success display-4 mb-3"></i>
                            <p class="text-muted mb-0">No hay alertas de calificaciones bajas</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Drivers Mejorado -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-trophy-fill me-2"></i>
                        Top Drivers Mejor Calificados
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th><i class="bi bi-person me-1"></i> Driver</th>
                                    <th><i class="bi bi-telephone me-1"></i> Contacto</th>
                                    <th><i class="bi bi-star me-1"></i> Calificación</th>
                                    <th><i class="bi bi-car-front me-1"></i> Viajes</th>
                                    <th><i class="bi bi-check-circle me-1"></i> Completados</th>
                                    <th><i class="bi bi-graph-up me-1"></i> Métricas</th>
                                    <th><i class="bi bi-star-fill me-1"></i> 5★</th>
                                    <th><i class="bi bi-activity me-1"></i> Estado</th>
                                    <th><i class="bi bi-gear me-1"></i> Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $__empty_1 = true; $__currentLoopData = $driverRatings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $driver): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                    <tr class="align-middle">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if($driver->driver_foto_path): ?>
                                                    <img src="<?php echo e(asset('storage/' . $driver->driver_foto_path)); ?>" 
                                                         alt="<?php echo e($driver->driver_name); ?>" 
                                                         class="rounded-circle me-2" 
                                                         style="width: 40px; height: 40px; object-fit: cover;">
                                                <?php else: ?>
                                                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-2" 
                                                         style="width: 40px; height: 40px;">
                                                        <i class="bi bi-person text-white"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <strong class="d-block"><?php echo e($driver->driver_name); ?></strong>
                                                    <small class="text-muted">ID: <?php echo e($driver->driver_id); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo e($driver->driver_phone); ?></small>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php for($i = 1; $i <= 5; $i++): ?>
                                                    <i class="bi bi-star-fill <?php echo e($i <= $driver->avg_rating ? 'text-warning' : 'text-muted'); ?> me-1"></i>
                                                <?php endfor; ?>
                                                <small class="ms-2 fw-bold"><?php echo e(number_format($driver->avg_rating, 1)); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary rounded-pill">
                                                <i class="bi bi-car-front me-1"></i><?php echo e($driver->total_rides ?? 0); ?>

                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success rounded-pill">
                                                <i class="bi bi-check-circle me-1"></i><?php echo e($driver->completed_rides ?? 0); ?>

                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                <span class="badge bg-info" title="Puntualidad">
                                                    P:<?php echo e(number_format($driver->avg_punctuality, 1)); ?>

                                                </span>
                                                <span class="badge bg-success" title="Cortesía">
                                                    C:<?php echo e(number_format($driver->avg_courtesy, 1)); ?>

                                                </span>
                                                <span class="badge bg-warning" title="Vehículo">
                                                    V:<?php echo e(number_format($driver->avg_vehicle_condition, 1)); ?>

                                                </span>
                                                <span class="badge bg-primary" title="Conducción">
                                                    CD:<?php echo e(number_format($driver->avg_driving_skills, 1)); ?>

                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-success rounded-pill">
                                                <i class="bi bi-star-fill me-1"></i><?php echo e($driver->five_stars); ?>

                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                                $statusColors = [
                                                    'idle' => 'success',
                                                    'busy' => 'warning', 
                                                    'offline' => 'secondary',
                                                    'on_ride' => 'primary'
                                                ];
                                            ?>
                                            <span class="badge bg-<?php echo e($statusColors[$driver->driver_status] ?? 'secondary'); ?> rounded-pill">
                                                <?php echo e(ucfirst($driver->driver_status)); ?>

                                            </span>
                                        </td>
                                        <td>
                                            <a href="<?php echo e(route('ratings.show', $driver->driver_id)); ?>" 
                                               class="btn btn-sm btn-outline-primary rounded-pill">
                                                <i class="bi bi-eye me-1"></i> Detalles
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-5">
                                            <i class="bi bi-star display-4 text-muted mb-3"></i>
                                            <p class="text-muted mb-0">No hay datos de calificaciones disponibles</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tendencias Mensuales -->
    <?php if($monthlyTrends->count() > 0): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-graph-up text-primary me-2"></i>
                        Tendencias Mensuales
                    </h5>
                </div>
                <div class="card-body">
                    <div class="chart">
                        <canvas id="monthlyTrendsChart" style="height: 300px;"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    <?php if($monthlyTrends->count() > 0): ?>
    document.addEventListener('DOMContentLoaded', function() {
        var ctx = document.getElementById('monthlyTrendsChart').getContext('2d');
        var monthlyTrendsChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($monthlyTrends->pluck('month')); ?>,
                datasets: [{
                    label: 'Calificación Promedio',
                    data: <?php echo json_encode($monthlyTrends->pluck('avg_rating')); ?>,
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Total Calificaciones',
                    data: <?php echo json_encode($monthlyTrends->pluck('total_ratings')); ?>,
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
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    });
    <?php endif; ?>
</script>
<?php $__env->stopPush(); ?>

<?php $__env->startPush('styles'); ?>
<style>
.bg-gradient-purple {
    background: linear-gradient(135deg, #6f42c1, #e83e8c) !important;
}
.card {
    border-radius: 12px;
}
.progress {
    border-radius: 10px;
}
.badge {
    font-size: 0.75em;
}
.table th {
    border-top: none;
    font-weight: 600;
    color: #495057;
}
.avatar-placeholder {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}
</style>
<?php $__env->stopPush(); ?>
<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/ratings/index.blade.php ENDPATH**/ ?>