<?php
// database/migrations/2026_01_18_0002_create_partner_daily_charges_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('partner_daily_charges', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('partner_id');

            $t->date('charge_date'); // fecha local tenant (YYYY-MM-DD)

            $t->unsignedInteger('vehicles_count')->default(0);
            $t->decimal('daily_rate', 10, 4)->default(0); // ppv/days
            $t->decimal('amount', 10, 2)->default(0);     // total del día
            $t->decimal('paid_amount', 10, 2)->default(0);
            $t->decimal('unpaid_amount', 10, 2)->default(0);

            $t->string('currency', 10)->default('MXN');

            $t->timestamp('settled_at')->nullable(); // cuando se debitó tenant_wallet por lo pagado
            $t->timestamps();

            $t->unique(['tenant_id','partner_id','charge_date'], 'uq_partner_daily_charge');
            $t->index(['tenant_id','charge_date'], 'ix_partner_daily_charge_tenant_date');

            // FKs si ya están listas:
            // $t->foreign('partner_id')->references('id')->on('partners')->onDelete('cascade');
            // $t->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_daily_charges');
    }
};
