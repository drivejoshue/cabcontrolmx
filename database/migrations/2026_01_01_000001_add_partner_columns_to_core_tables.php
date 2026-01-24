<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Si aún no existe partners, evita romper la corrida.
        // (Ideal: correr primero la migración que crea partners.)
        if (!Schema::hasTable('partners')) {
            return;
        }

        // =====================================================
        // USERS: partner por defecto para scoping rápido del dashboard
        // =====================================================
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'default_partner_id')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'tenant_id')) {
                    $table->unsignedBigInteger('default_partner_id')->nullable()->after('tenant_id');
                } else {
                    $table->unsignedBigInteger('default_partner_id')->nullable();
                }

                $table->index(['tenant_id', 'default_partner_id'], 'ix_users_tenant_default_partner');
                $table->foreign('default_partner_id')->references('id')->on('partners')->nullOnDelete();
            });
        }

        // =====================================================
        // DRIVERS: asignación a partner (actual + origen)
        // =====================================================
        if (Schema::hasTable('drivers') && !Schema::hasColumn('drivers', 'partner_id')) {
            Schema::table('drivers', function (Blueprint $table) {
                if (Schema::hasColumn('drivers', 'tenant_id')) {
                    $table->unsignedBigInteger('partner_id')->nullable()->after('tenant_id');
                } else {
                    $table->unsignedBigInteger('partner_id')->nullable();
                }

                $table->unsignedBigInteger('recruited_by_partner_id')->nullable()->after('partner_id');
                $table->dateTime('partner_assigned_at')->nullable()->after('recruited_by_partner_id');
                $table->dateTime('partner_left_at')->nullable()->after('partner_assigned_at');
                $table->string('partner_notes', 255)->nullable()->after('partner_left_at');

                if (Schema::hasColumn('drivers', 'status')) {
                    $table->index(['tenant_id','partner_id','status'], 'ix_drivers_tenant_partner_status');
                } else {
                    $table->index(['tenant_id','partner_id'], 'ix_drivers_tenant_partner_status');
                }

                $table->index(['tenant_id','recruited_by_partner_id'], 'ix_drivers_tenant_partner_recruited');

                $table->foreign('partner_id')->references('id')->on('partners')->nullOnDelete();
                $table->foreign('recruited_by_partner_id')->references('id')->on('partners')->nullOnDelete();
            });
        }

        // =====================================================
        // VEHICLES: asignación a partner (actual + origen)
        // =====================================================
        if (Schema::hasTable('vehicles') && !Schema::hasColumn('vehicles', 'partner_id')) {
            Schema::table('vehicles', function (Blueprint $table) {
                if (Schema::hasColumn('vehicles', 'tenant_id')) {
                    $table->unsignedBigInteger('partner_id')->nullable()->after('tenant_id');
                } else {
                    $table->unsignedBigInteger('partner_id')->nullable();
                }

                $table->unsignedBigInteger('recruited_by_partner_id')->nullable()->after('partner_id');
                $table->dateTime('partner_assigned_at')->nullable()->after('recruited_by_partner_id');
                $table->dateTime('partner_left_at')->nullable()->after('partner_assigned_at');
                $table->string('partner_notes', 255)->nullable()->after('partner_left_at');

                if (Schema::hasColumn('vehicles', 'active')) {
                    $table->index(['tenant_id','partner_id','active'], 'ix_vehicles_tenant_partner_active');
                } else {
                    $table->index(['tenant_id','partner_id'], 'ix_vehicles_tenant_partner_active');
                }

                $table->index(['tenant_id','recruited_by_partner_id'], 'ix_vehicles_tenant_partner_recruited');

                $table->foreign('partner_id')->references('id')->on('partners')->nullOnDelete();
                $table->foreign('recruited_by_partner_id')->references('id')->on('partners')->nullOnDelete();
            });
        }

        // =====================================================
        // DRIVER SHIFTS: snapshot del partner durante el turno
        // =====================================================
        if (Schema::hasTable('driver_shifts') && !Schema::hasColumn('driver_shifts', 'partner_id')) {
            Schema::table('driver_shifts', function (Blueprint $table) {
                if (Schema::hasColumn('driver_shifts', 'tenant_id')) {
                    $table->unsignedBigInteger('partner_id')->nullable()->after('tenant_id');
                } else {
                    $table->unsignedBigInteger('partner_id')->nullable();
                }

                if (Schema::hasColumn('driver_shifts', 'status')) {
                    $table->index(['tenant_id','partner_id','status'], 'ix_driver_shifts_tenant_partner_status');
                } else {
                    $table->index(['tenant_id','partner_id'], 'ix_driver_shifts_tenant_partner_status');
                }

                $table->foreign('partner_id')->references('id')->on('partners')->nullOnDelete();
            });
        }

        // =====================================================
        // ASSIGNMENTS: snapshot del partner durante la asignación
        // =====================================================
        if (Schema::hasTable('driver_vehicle_assignments') && !Schema::hasColumn('driver_vehicle_assignments', 'partner_id')) {
            Schema::table('driver_vehicle_assignments', function (Blueprint $table) {
                if (Schema::hasColumn('driver_vehicle_assignments', 'tenant_id')) {
                    $table->unsignedBigInteger('partner_id')->nullable()->after('tenant_id');
                } else {
                    $table->unsignedBigInteger('partner_id')->nullable();
                }

                if (Schema::hasColumn('driver_vehicle_assignments', 'end_at')) {
                    $table->index(['tenant_id','partner_id','end_at'], 'ix_dva_tenant_partner_open');
                } elseif (Schema::hasColumn('driver_vehicle_assignments', 'ended_at')) {
                    $table->index(['tenant_id','partner_id','ended_at'], 'ix_dva_tenant_partner_open');
                } else {
                    $table->index(['tenant_id','partner_id'], 'ix_dva_tenant_partner_open');
                }

                $table->foreign('partner_id')->references('id')->on('partners')->nullOnDelete();
            });
        }

        // =====================================================
        // TAXI FEES/CHARGES/RECEIPTS: para cobrar por partner (wallet partner)
        // =====================================================
        if (Schema::hasTable('tenant_taxi_fees') && !Schema::hasColumn('tenant_taxi_fees', 'partner_id')) {
            Schema::table('tenant_taxi_fees', function (Blueprint $table) {
                if (Schema::hasColumn('tenant_taxi_fees', 'tenant_id')) {
                    $table->unsignedBigInteger('partner_id')->nullable()->after('tenant_id');
                } else {
                    $table->unsignedBigInteger('partner_id')->nullable();
                }

                if (Schema::hasColumn('tenant_taxi_fees', 'active') && Schema::hasColumn('tenant_taxi_fees', 'period_type')) {
                    $table->index(['tenant_id','partner_id','active','period_type'], 'ix_ttf_tenant_partner_active_period');
                } else {
                    $table->index(['tenant_id','partner_id'], 'ix_ttf_tenant_partner_active_period');
                }

                $table->foreign('partner_id')->references('id')->on('partners')->nullOnDelete();
            });
        }

        if (Schema::hasTable('tenant_taxi_charges') && !Schema::hasColumn('tenant_taxi_charges', 'partner_id')) {
            Schema::table('tenant_taxi_charges', function (Blueprint $table) {
                if (Schema::hasColumn('tenant_taxi_charges', 'tenant_id')) {
                    $table->unsignedBigInteger('partner_id')->nullable()->after('tenant_id');
                } else {
                    $table->unsignedBigInteger('partner_id')->nullable();
                }

                if (
                    Schema::hasColumn('tenant_taxi_charges', 'status') &&
                    Schema::hasColumn('tenant_taxi_charges', 'period_start') &&
                    Schema::hasColumn('tenant_taxi_charges', 'period_end')
                ) {
                    $table->index(['tenant_id','partner_id','status','period_start','period_end'], 'ix_ttc_tenant_partner_status_period');
                } else {
                    $table->index(['tenant_id','partner_id'], 'ix_ttc_tenant_partner_status_period');
                }

                $table->foreign('partner_id')->references('id')->on('partners')->nullOnDelete();
            });
        }

        if (Schema::hasTable('tenant_taxi_receipts') && !Schema::hasColumn('tenant_taxi_receipts', 'partner_id')) {
            Schema::table('tenant_taxi_receipts', function (Blueprint $table) {
                if (Schema::hasColumn('tenant_taxi_receipts', 'tenant_id')) {
                    $table->unsignedBigInteger('partner_id')->nullable()->after('tenant_id');
                } else {
                    $table->unsignedBigInteger('partner_id')->nullable();
                }

                if (Schema::hasColumn('tenant_taxi_receipts', 'issued_at')) {
                    $table->index(['tenant_id','partner_id','issued_at'], 'ix_ttr_tenant_partner_issued');
                } else {
                    $table->index(['tenant_id','partner_id'], 'ix_ttr_tenant_partner_issued');
                }

                $table->foreign('partner_id')->references('id')->on('partners')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Reversión completa (segura) por si la ocupas en local.
        // En VPS normalmente no conviene dropear historial, pero aquí está listo.

        if (Schema::hasTable('tenant_taxi_receipts') && Schema::hasColumn('tenant_taxi_receipts', 'partner_id')) {
            Schema::table('tenant_taxi_receipts', function (Blueprint $table) {
                $table->dropForeign(['partner_id']);
                $table->dropIndex('ix_ttr_tenant_partner_issued');
                $table->dropColumn('partner_id');
            });
        }

        if (Schema::hasTable('tenant_taxi_charges') && Schema::hasColumn('tenant_taxi_charges', 'partner_id')) {
            Schema::table('tenant_taxi_charges', function (Blueprint $table) {
                $table->dropForeign(['partner_id']);
                $table->dropIndex('ix_ttc_tenant_partner_status_period');
                $table->dropColumn('partner_id');
            });
        }

        if (Schema::hasTable('tenant_taxi_fees') && Schema::hasColumn('tenant_taxi_fees', 'partner_id')) {
            Schema::table('tenant_taxi_fees', function (Blueprint $table) {
                $table->dropForeign(['partner_id']);
                $table->dropIndex('ix_ttf_tenant_partner_active_period');
                $table->dropColumn('partner_id');
            });
        }

        if (Schema::hasTable('driver_vehicle_assignments') && Schema::hasColumn('driver_vehicle_assignments', 'partner_id')) {
            Schema::table('driver_vehicle_assignments', function (Blueprint $table) {
                $table->dropForeign(['partner_id']);
                $table->dropIndex('ix_dva_tenant_partner_open');
                $table->dropColumn('partner_id');
            });
        }

        if (Schema::hasTable('driver_shifts') && Schema::hasColumn('driver_shifts', 'partner_id')) {
            Schema::table('driver_shifts', function (Blueprint $table) {
                $table->dropForeign(['partner_id']);
                $table->dropIndex('ix_driver_shifts_tenant_partner_status');
                $table->dropColumn('partner_id');
            });
        }

        if (Schema::hasTable('vehicles') && Schema::hasColumn('vehicles', 'partner_id')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->dropForeign(['partner_id']);
                $table->dropForeign(['recruited_by_partner_id']);
                $table->dropIndex('ix_vehicles_tenant_partner_active');
                $table->dropIndex('ix_vehicles_tenant_partner_recruited');
                $table->dropColumn([
                    'partner_id',
                    'recruited_by_partner_id',
                    'partner_assigned_at',
                    'partner_left_at',
                    'partner_notes',
                ]);
            });
        }

        if (Schema::hasTable('drivers') && Schema::hasColumn('drivers', 'partner_id')) {
            Schema::table('drivers', function (Blueprint $table) {
                $table->dropForeign(['partner_id']);
                $table->dropForeign(['recruited_by_partner_id']);
                $table->dropIndex('ix_drivers_tenant_partner_status');
                $table->dropIndex('ix_drivers_tenant_partner_recruited');
                $table->dropColumn([
                    'partner_id',
                    'recruited_by_partner_id',
                    'partner_assigned_at',
                    'partner_left_at',
                    'partner_notes',
                ]);
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'default_partner_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['default_partner_id']);
                $table->dropIndex('ix_users_tenant_default_partner');
                $table->dropColumn('default_partner_id');
            });
        }
    }
};
