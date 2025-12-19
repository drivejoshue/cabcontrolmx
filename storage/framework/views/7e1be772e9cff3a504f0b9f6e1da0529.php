

<?php $__env->startSection('title', 'Reporte de Actividades del Conductor'); ?>

<?php $__env->startPush('styles'); ?>
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
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div class="row mb-4">
    <div class="col-12">
        <h1 class="h3 mb-3">
            <i class="bi bi-graph-up me-2"></i>Reporte de Actividades del Conductor
        </h1>
        
        <div class="card">
            <div class="card-body">
<form method="GET" action="<?php echo e(route('reports.drivers.activity')); ?>" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Conductor</label>
                        <select name="driver_id" class="form-select">
                            <option value="">Todos los conductores</option>
                            <?php $__currentLoopData = $drivers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $driver): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($driver->id); ?>" <?php echo e($driverId == $driver->id ? 'selected' : ''); ?>>
                                    <?php echo e($driver->name); ?> (<?php echo e($driver->phone); ?>)
                                </option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Fecha Inicio</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo e($startDate); ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Fecha Fin</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo e($endDate); ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Agrupar por</label>
                        <select name="group_by" class="form-select">
                            <option value="day" <?php echo e($groupBy == 'day' ? 'selected' : ''); ?>>Día</option>
                            <option value="week" <?php echo e($groupBy == 'week' ? 'selected' : ''); ?>>Semana</option>
                            <option value="month" <?php echo e($groupBy == 'month' ? 'selected' : ''); ?>>Mes</option>
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
                                <h3 class="mb-0"><?php echo e($reportData['summary']['total_drivers']); ?></h3>
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
                                <h3 class="mb-0"><?php echo e($reportData['summary']['total_rides']); ?></h3>
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
                                <h3 class="mb-0">$<?php echo e(number_format($reportData['summary']['total_revenue'] ?? 0, 2)); ?></h3>
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
                                <h3 class="mb-0"><?php echo e($reportData['summary']['total_shifts'] ?? 0); ?></h3>
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
                                <h3 class="mb-0"><?php echo e(number_format($reportData['summary']['avg_rating'] ?? 0, 1)); ?>

                                    <span class="rating-stars">
                                        <?php
                                            $avgRating = $reportData['summary']['avg_rating'] ?? 0;
                                            $roundedRating = round($avgRating);
                                        ?>
                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi bi-star<?php echo e($i <= $roundedRating ? '-fill' : ''); ?>"></i>
                                        <?php endfor; ?>
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

<?php if($driverId && $driverDetails): ?>
<!-- Detalles del Conductor Seleccionado -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card driver-card">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-2">
                        <?php if($driverDetails->foto_path): ?>
                            <img src="<?php echo e(asset('storage/' . $driverDetails->foto_path)); ?>" 
                                 class="rounded-circle img-thumbnail" width="100" height="100">
                        <?php else: ?>
                            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" 
                                 style="width: 100px; height: 100px;">
                                <i class="bi bi-person text-white" style="font-size: 3rem;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <h4 class="mb-1"><?php echo e($driverDetails->name); ?></h4>
                        <p class="text-muted mb-2">
                            <i class="bi bi-telephone me-1"></i><?php echo e($driverDetails->phone); ?>

                            <?php if($driverDetails->email): ?>
                                <span class="mx-2">•</span>
                                <i class="bi bi-envelope me-1"></i><?php echo e($driverDetails->email); ?>

                            <?php endif; ?>
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-<?php echo e($driverDetails->status == 'idle' ? 'success' : ($driverDetails->status == 'busy' ? 'warning' : 'secondary')); ?>">
                                <?php echo e(ucfirst($driverDetails->status)); ?>

                            </span>
                            <?php if($driverDetails->last_seen_at): ?>
                                <span class="badge bg-info">
                                    <i class="bi bi-clock-history me-1"></i>
                                    Última vez: <?php echo e($driverDetails->last_seen_at->diffForHumans()); ?>

                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <?php if($driverDetails->vehicleAssignments && $driverDetails->vehicleAssignments->count() > 0): ?>
                            <?php $vehicle = $driverDetails->vehicleAssignments->first()->vehicle; ?>
                            <div class="d-flex align-items-center">
                                <i class="bi bi-car-front-fill fs-4 text-primary me-2"></i>
                                <div>
                                    <small class="text-muted d-block">Vehículo asignado</small>
                                    <strong><?php echo e($vehicle->brand ?? ''); ?> <?php echo e($vehicle->model ?? ''); ?></strong>
                                    <span class="badge bg-light text-dark ms-2"><?php echo e($vehicle->plate ?? ''); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

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
                    <?php $__currentLoopData = $reportData['payment_methods']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $method): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span><?php echo e(ucfirst($method->payment_method)); ?></span>
                            <span class="fw-bold">$<?php echo e(number_format($method->amount, 2)); ?></span>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
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
                            <?php $__currentLoopData = $reportData['driver_stats']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if($stat['driver']->foto_path): ?>
                                                <img src="<?php echo e(asset('storage/' . $stat['driver']->foto_path)); ?>" 
                                                     class="rounded-circle me-2" width="32" height="32">
                                            <?php else: ?>
                                                <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center me-2" 
                                                     style="width: 32px; height: 32px;">
                                                    <i class="bi bi-person text-white"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <strong><?php echo e($stat['driver']->name); ?></strong>
                                                <div class="small text-muted"><?php echo e($stat['driver']->phone); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo e($stat['rides']['total'] ?? 0); ?></strong>
                                        <div class="small text-muted">
                                            <?php echo e($stat['rides']['avg_distance'] ?? 0); ?> km/serv
                                        </div>
                                    </td>
                                    <td>
                                        <strong class="text-success">$<?php echo e(number_format($stat['rides']['revenue'] ?? 0, 2)); ?></strong>
                                    </td>
                                    <td>$<?php echo e(number_format($stat['rides']['cash'] ?? 0, 2)); ?></td>
                                    <td>$<?php echo e(number_format($stat['rides']['transfer'] ?? 0, 2)); ?></td>
                                    <td>$<?php echo e(number_format($stat['rides']['card'] ?? 0, 2)); ?></td>
                                    <td>
                                        <?php echo e($stat['shifts']['total'] ?? 0); ?>

                                        <div class="small text-muted">
                                          <?php echo e($stat['shifts']['avg_shift_minutes'] ?? 0); ?> min/turno

                                        </div>
                                    </td>
                                    <td><?php echo e($stat['shifts']['total_hours'] ?? 0); ?> hrs</td>
                                    <td>
                                        <?php if($stat['ratings']): ?>
                                            <div class="d-flex align-items-center">
                                                <span class="me-2"><?php echo e(number_format($stat['ratings']['avg_rating'], 1)); ?></span>
                                                <div class="rating-stars">
                                                    <?php
                                                        $driverRating = round($stat['ratings']['avg_rating']);
                                                    ?>
                                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                                        <i class="bi bi-star<?php echo e($i <= $driverRating ? '-fill' : ''); ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                            <small class="text-muted"><?php echo e($stat['ratings']['total']); ?> ratings</small>
                                        <?php else: ?>
                                            <span class="text-muted">Sin ratings</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($stat['efficiency']): ?>
                                            <span class="badge bg-success efficiency-badge">
                                                <?php echo e($stat['efficiency']['rides_per_hour'] ?? 0); ?> serv/hora
                                            </span>
                                            <br>
                                            <span class="badge bg-info efficiency-badge mt-1">
                                                $<?php echo e($stat['efficiency']['revenue_per_hour'] ?? 0); ?>/hora
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Movimientos de Wallet (si hay conductor específico) -->
<?php if($driverId && count($reportData['wallet_movements'] ?? []) > 0): ?>
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
                            <?php $__currentLoopData = $reportData['wallet_movements']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $movement): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr>
                                    <td><?php echo e($movement->created_at->format('d/m/Y H:i')); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo e($movement->direction == 'credit' ? 'success' : 'danger'); ?>">
                                            <?php echo e(ucfirst($movement->type)); ?>

                                        </span>
                                    </td>
                                    <td>
                                        <?php if($movement->ride_id): ?>
                                            <a href="<?php echo e(route('admin.rides.show', $movement->ride_id)); ?>" class="text-decoration-none">

                                                #<?php echo e($movement->ride_id); ?>

                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="<?php echo e($movement->direction == 'credit' ? 'text-success' : 'text-danger'); ?>">
                                        <?php echo e($movement->direction == 'credit' ? '+' : '-'); ?>$<?php echo e(number_format($movement->amount, 2)); ?>

                                    </td>
                                    <td>$<?php echo e(number_format($movement->balance_after, 2)); ?></td>
                                    <td><?php echo e($movement->description); ?></td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

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
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gráfico de Ingresos por Período
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    const revenueChart = new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($reportData['revenue_by_period']->pluck('period'), 15, 512) ?>,
            datasets: [{
                label: 'Ingresos ($)',
                data: <?php echo json_encode($reportData['revenue_by_period']->pluck('total_revenue'), 15, 512) ?>,
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
            labels: <?php echo json_encode($reportData['payment_methods']->pluck('payment_method'), 15, 512) ?>,
            datasets: [{
                data: <?php echo json_encode($reportData['payment_methods']->pluck('amount'), 15, 512) ?>,
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
    const peakHoursData = <?php echo json_encode($reportData['peak_hours']->toArray(), 15, 512) ?>;
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
<?php $__env->stopPush(); ?>
<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/admin/reports/drivers/driver-activity.blade.php ENDPATH**/ ?>