<?php 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('ride_issues', function (Blueprint $table) {
            $table->unsignedBigInteger('resolved_by_user_id')->nullable()->after('resolved_at');
            $table->text('resolution_notes')->nullable()->after('resolved_by_user_id');
            $table->timestamp('closed_at')->nullable()->after('resolution_notes');

            $table->index(['tenant_id', 'severity', 'status'], 'ride_issues_tenant_sev_status_idx');
            $table->foreign('resolved_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('ride_issues', function (Blueprint $table) {
            $table->dropForeign(['resolved_by_user_id']);
            $table->dropIndex('ride_issues_tenant_sev_status_idx');
            $table->dropColumn(['resolved_by_user_id', 'resolution_notes', 'closed_at']);
        });
    }
};