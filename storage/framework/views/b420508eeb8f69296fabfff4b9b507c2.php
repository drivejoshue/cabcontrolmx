

<?php $__env->startSection('title', 'SysAdmin · Orbana'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-fluid">

    <div class="row mb-4">
        <div class="col">
            <h1 class="h3 mb-0">Panel SysAdmin</h1>
            <small class="text-muted">
                Control maestro de tenants, billing y documentación de vehículos.
            </small>
        </div>
    </div>

    
    <div class="row">
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Tenants totales</h6>
                    <h3 class="mb-0"><?php echo e($tenantsCount); ?></h3>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Conductores</h6>
                    <h3 class="mb-0"><?php echo e($driversCount); ?></h3>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Vehículos</h6>
                    <h3 class="mb-0"><?php echo e($vehiclesCount); ?></h3>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Viajes hoy</h6>
                    <h3 class="mb-0"><?php echo e($ridesToday); ?></h3>
                </div>
            </div>
        </div>
    </div>

    
    <div class="row mt-3">
        <div class="col-md-12 mb-3">
            <div class="card border-info shadow-sm">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1">Orbana Global</h5>
                        <?php if($globalTenant): ?>
                            <p class="mb-0 text-muted">
                                Tenant: <?php echo e($globalTenant->name); ?>

                                (slug: <?php echo e($globalTenant->slug); ?>)
                            </p>
                        <?php else: ?>
                            <p class="mb-0 text-muted">
                                Aún no se ha creado el tenant para Orbana Global.
                            </p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <a href="<?php echo e(route('sysadmin.tenants.index')); ?>" class="btn btn-sm btn-outline-info">
                            Ver tenants
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
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
                        <?php $__empty_1 = true; $__currentLoopData = $recentTenants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tenant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr>
                                <td><?php echo e($tenant->name); ?></td>
                                <td><?php echo e($tenant->slug); ?></td>
                                <td><?php echo e($tenant->created_at); ?></td>
                                <td class="text-end">
                                    <a href="<?php echo e(route('sysadmin.tenants.edit', $tenant)); ?>" class="btn btn-xs btn-outline-primary">
                                        Editar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">
                                    Sin tenants registrados aún.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        
        <?php if(isset($recentInvoices)): ?>
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
                        <?php $__empty_1 = true; $__currentLoopData = $recentInvoices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invoice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr>
                                <td><?php echo e($invoice->tenant->name ?? '#'); ?></td>
                                <td>$<?php echo e(number_format($invoice->amount, 2)); ?></td>
                                <td><?php echo e($invoice->status); ?></td>
                                <td><?php echo e($invoice->created_at); ?></td>
                                <td class="text-end">
                                    <a href="<?php echo e(route('sysadmin.invoices.show', $invoice)); ?>" class="btn btn-xs btn-outline-secondary">
                                        Ver
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">
                                    Aún no hay facturas registradas.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.sysadmin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\cabcontrolmx\resources\views/sysadmin/dashboard.blade.php ENDPATH**/ ?>